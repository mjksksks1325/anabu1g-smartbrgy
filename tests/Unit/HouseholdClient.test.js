import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/admin.js', import.meta.url), 'utf8');
const householdCode = source.slice(source.indexOf('let householdLookupTimer ='), source.indexOf('async function populateManualResidentDropdown()'));
const payloadCode = source.slice(source.indexOf('function residentFormPayload('), source.indexOf('async function saveResident('));
const mappingCode = source.slice(source.indexOf('function residentFromApi('), source.indexOf('async function loadResidents('));

class FakeOption {
  constructor(text, value) { this.textContent = text; this.value = value; }
}

class FakeSelect {
  options = [];
  value = '';
  get selectedOptions() { return this.options.filter(option => option.value === this.value); }
  replaceChildren(...options) { this.options = options; this.value = options[0]?.value || ''; }
  add(option) { this.options.push(option); }
}

function householdClient(fetch) {
  const fields = new Map();
  const getField = id => {
    if (!fields.has(id)) fields.set(id, { value: '', checked: false, innerHTML: '', textContent: '', dataset: {} });
    return fields.get(id);
  };
  const toasts = [];
  const context = vm.createContext({
    document: { getElementById: getField }, Option: FakeOption,
    URLSearchParams, setTimeout, clearTimeout, fetch, confirm: () => true,
    PUROK_DATA: [], getCheckedSpecialGroups: () => [], csrfRequestHeaders: () => ({ 'X-CSRF-TOKEN': 'test-token' }),
    showToast: message => toasts.push(message),
    escapeText: value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]))
  });
  vm.runInContext(householdCode + payloadCode + mappingCode, context);
  return { context, fields, getField, toasts };
}

test('resident forms keep household selection optional and include inline data only when requested', () => {
  const { context, getField } = householdClient();
  assert.equal(context.residentFormPayload().household_id, null);
  assert.equal(context.residentFormPayload().is_household_head, false);
  assert.equal('new_household' in context.residentFormPayload(), false);

  getField('res-household-id').value = '14';
  getField('res-household-relationship').value = ' Spouse ';
  getField('res-household-head').checked = true;
  assert.equal(context.residentFormPayload().household_id, 14);
  assert.equal(context.residentFormPayload().relationship_to_household_head, 'Spouse');
  assert.equal(context.residentFormPayload().is_household_head, true);

  getField('res-create-household').checked = true;
  getField('res-household-address').value = ' Shared address ';
  getField('res-household-purok').value = '3';
  assert.equal(context.residentFormPayload().new_household.address, 'Shared address');
  assert.equal(context.residentFormPayload().new_household.purok_id, 3);
});

test('new household creation stages existing residents and submits their assignments in one request', async () => {
  const requests = [];
  const residents = [
    { id: 1, full_name: 'Maria Santos', resident_number: 'RES-1', status: 'active' },
    { id: 2, full_name: 'Jose Garcia', resident_number: 'RES-2', status: 'active' }
  ];
  const { context, fields, getField } = householdClient(async (url, options) => {
    requests.push({ url, options });
    return { ok: true, json: async () => options.method === 'POST' ? { message: 'Created', household: { id: 4 } } : { data: residents, current_page: 1, last_page: 1 } };
  });
  ['household-head', 'household-resident-id', 'household-purok'].forEach(id => fields.set(id, new FakeSelect()));
  context.loadManagedHouseholds = async () => {};
  context.loadResidents = async () => {};
  context.editManagedHousehold = async () => {};
  context.refreshHouseholdDemographics = async () => {};
  vm.runInContext('residentCurrentPage = 1', context);
  context.newManagedHousehold();
  await new Promise(resolve => setImmediate(resolve));
  getField('household-resident-id').value = '1';
  getField('household-resident-head').checked = true;
  await context.addManagedHouseholdMember();
  await new Promise(resolve => setImmediate(resolve));
  getField('household-resident-id').value = '2';
  getField('household-resident-relationship').value = 'Spouse';
  await context.addManagedHouseholdMember();
  await new Promise(resolve => setImmediate(resolve));

  assert.equal(requests.filter(request => request.options.method !== 'GET').length, 0);
  assert.equal(getField('household-head').value, '1');
  getField('household-address').value = 'Shared address';
  getField('household-name').value = '  Santos Family  ';
  getField('household-number').value = 'HH-000042';
  await context.saveManagedHousehold();

  const writes = requests.filter(request => request.options.method !== 'GET');
  assert.equal(writes.length, 1);
  assert.equal(writes[0].url, '/admin/households');
  assert.deepEqual(JSON.parse(writes[0].options.body), { household_name: 'Santos Family', address: 'Shared address', purok_id: null, household_number: 'HH-000042', household_head_resident_id: 1, members: [{ resident_id: 2, relationship_to_household_head: 'Spouse' }] });
});

test('new household save requires a head and keeps the draft for correction', async () => {
  let writes = 0;
  const { context, fields, getField, toasts } = householdClient(async () => { writes++; });
  fields.set('household-head', new FakeSelect());

  await context.saveManagedHousehold();

  assert.equal(writes, 0);
  assert.equal(getField('household-save').disabled, false);
  assert.match(toasts[0], /existing active resident/);
});

test('new households list existing active residents as heads before adding any members', async () => {
  const requests = [];
  const { context, fields, getField } = householdClient(async (url, options) => {
    requests.push({ url, options });
    return { ok: true, json: async () => options.method === 'POST' ? { message: 'Created', household: { id: 9 } } : { data: [{ id: 1, full_name: 'Maria Santos', resident_number: 'RES-1', status: 'active' }, { id: 2, full_name: 'Inactive Resident', resident_number: 'RES-2', status: 'inactive' }], current_page: 1, last_page: 1 } };
  });
  ['household-head', 'household-resident-id', 'household-purok'].forEach(id => fields.set(id, new FakeSelect()));
  context.newManagedHousehold();
  await new Promise(resolve => setImmediate(resolve));

  assert.equal(getField('household-head').options[1].textContent, 'Maria Santos');
  assert.equal(getField('household-head').options.length, 2);
  context.selectManagedHouseholdHead('1');
  assert.equal(getField('household-head').value, '1');
  assert.match(getField('household-members').innerHTML, /Maria Santos/);
  assert.equal(requests.filter(request => request.options.method !== 'GET').length, 0);
  context.loadManagedHouseholds = context.loadResidents = context.editManagedHousehold = context.refreshHouseholdDemographics = async () => {};
  getField('household-address').value = 'Shared address';
  vm.runInContext('residentCurrentPage = 1', context);
  await context.saveManagedHousehold();
  const writes = requests.filter(request => request.options.method === 'POST');
  assert.equal(writes.length, 1);
  assert.equal(JSON.parse(writes[0].options.body).household_head_resident_id, 1);
  assert.deepEqual(JSON.parse(writes[0].options.body).members, []);
});

test('selected household heads remain available when resident search results change', async () => {
  let request = 0;
  const { context, fields, getField } = householdClient(async () => ({ ok: true, json: async () => ({ data: [{ id: ++request, full_name: `Resident ${request}`, resident_number: `RES-${request}`, status: 'active' }], current_page: 1, last_page: 1 }) }));
  ['household-head', 'household-resident-id', 'household-purok'].forEach(id => fields.set(id, new FakeSelect()));
  context.newManagedHousehold();
  await new Promise(resolve => setImmediate(resolve));
  context.selectManagedHouseholdHead('1');
  getField('household-resident-search').value = 'Another resident';
  await context.loadHouseholdResidents();
  assert.equal(getField('household-head').value, '1');
  assert.equal(getField('household-head').options.some(option => option.value === '1'), true);
  assert.equal(getField('household-head').options.some(option => option.value === '2'), true);
  context.removeDraftHouseholdMember(1);
  assert.equal(getField('household-head').value, '');
});

test('cancelling a head move leaves the draft unassigned', async () => {
  const { context, fields, getField } = householdClient(async () => ({ ok: true, json: async () => ({ data: [{ id: 1, full_name: 'Existing Head', resident_number: 'RES-1', status: 'active', household_id: 8 }], current_page: 1, last_page: 1 }) }));
  ['household-head', 'household-resident-id', 'household-purok'].forEach(id => fields.set(id, new FakeSelect()));
  context.confirm = () => false;
  context.newManagedHousehold();
  await new Promise(resolve => setImmediate(resolve));
  context.selectManagedHouseholdHead('1');
  assert.equal(getField('household-head').value, '');
  assert.match(getField('household-members').innerHTML, /No residents selected/);
});

test('demographics displays database totals without the removed average card', () => {
  const { context, getField, fields } = householdClient();
  context.RESIDENTS = [];
  context.isSenior = () => false;
  context.DEMOGRAPHIC_SUMMARY = { total: 3, seniors: 1, households: { total_residents: 4, male: 1, female: 3, total_households: 2, registered_voters: 2, average_household_size: 1.5 } };
  const statsCode = source.slice(source.indexOf('function renderDemographicsStats()'), source.indexOf('function renderDemographics()'));
  vm.runInContext(statsCode, context);

  context.renderDemographicsStats();

  assert.equal(getField('demo-stat-total').textContent, '4');
  assert.equal(getField('demo-stat-households').textContent, '2');
  assert.equal(getField('demo-stat-voters').textContent, '2');
  assert.equal(fields.has('demo-stat-household-average'), false);
  assert.equal(getField('demo-stat-seniors').textContent, '1');
});

test('API mapping retains household information for resident edits', () => {
  const { context } = householdClient();
  const resident = context.residentFromApi({ id: 2, household_id: 4, household: { household_number: 'HH-000004' }, relationship_to_household_head: 'Child', is_household_head: false });
  assert.equal(resident.householdId, 4);
  assert.equal(resident.household.household_number, 'HH-000004');
  assert.equal(resident.relationshipToHead, 'Child');
  assert.equal(resident.isHouseholdHead, false);
});

test('household lookup preserves the selected household across search and pagination', async () => {
  const { context, fields, getField } = householdClient(async () => ({ ok: true, json: async () => ({ data: [{ id: 2, household_number: 'HH-000002', head: null, address: 'Other address' }], current_page: 2, last_page: 3 }) }));
  const select = new FakeSelect();
  select.add(new FakeOption('HH-000001', '1'));
  select.value = '1';
  fields.set('res-household-id', select);
  getField('res-household-search').value = 'Other';

  await context.loadResidentHouseholds(2);

  assert.equal(select.value, '1');
  assert.equal(select.options.length, 3);
  assert.match(select.options[2].textContent, /No household head assigned/);
  assert.match(getField('res-household-pagination').innerHTML, /Page 2 of 3/);
});

test('out of order household searches cannot replace newer lookup results', async () => {
  const pending = [];
  const { context, fields } = householdClient(() => new Promise(resolve => pending.push(resolve)));
  fields.set('res-household-id', new FakeSelect());
  const older = context.loadResidentHouseholds(1);
  const newer = context.loadResidentHouseholds(2);
  const response = number => ({ ok: true, json: async () => ({ data: [{ id: number, household_number: `HH-${number}`, address: 'Address' }], current_page: number, last_page: 2 }) });
  pending[1](response(2));
  await newer;
  pending[0](response(1));
  await older;

  assert.equal(fields.get('res-household-id').options[1].value, '2');
});

test('household member markup escapes names and relationships and labels inactive residents', () => {
  const { context } = householdClient();
  const html = context.householdMembersMarkup({ id: 1, members: [{ id: 2, full_name: '<script>alert(1)</script>', relationship_to_household_head: '<img src=x>', gender: 'Female', registered_voter: true, status: 'inactive' }] });
  assert.match(html, /&lt;script&gt;/);
  assert.match(html, /&lt;img src=x&gt;/);
  assert.match(html, /Inactive/);
  assert.match(html, /Yes/);
  assert.doesNotMatch(html, /<script>|<img/);
});

test('household write requests include CSRF headers and show validation messages', async () => {
  let request;
  const { context } = householdClient(async (url, options) => {
    request = { url, options };
    return { ok: false, json: async () => ({ errors: { is_household_head: ['The resident must be active.'] } }) };
  });

  await assert.rejects(context.householdApi('/admin/households/1/members/2', 'PATCH', { is_household_head: true }), /The resident must be active/);
  assert.equal(request.options.credentials, 'same-origin');
  assert.equal(request.options.headers['X-CSRF-TOKEN'], 'test-token');
  assert.deepEqual(JSON.parse(request.options.body), { is_household_head: true });
});
