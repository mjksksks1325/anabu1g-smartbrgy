import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/admin.js', import.meta.url), 'utf8');
const householdCode = source.slice(source.indexOf('let householdLookupTimer ='), source.indexOf('async function populateManualResidentDropdown()'));
const payloadCode = source.slice(source.indexOf('function residentFormPayload('), source.indexOf('async function saveResident('));
const mappingCode = source.slice(source.indexOf('function residentFromApi('), source.indexOf('async function loadResidents('));
const pagerCode = source.slice(source.indexOf('function renderPager('), source.indexOf('function renderIssuedCertificatesPagination('));

class FakeOption {
  constructor(text, value) { this.textContent = text; this.value = value; }
}

/** The DOM methods the household dialog uses; attributes are kept so tests can read aria-invalid and friends. */
class FakeElement {
  constructor(id = '') { this.id = id; this.attributes = {}; this.dataset = {}; this.style = {}; this.value = ''; this.checked = false; this.innerHTML = ''; this.textContent = ''; this.hidden = false; this.disabled = false; this.inserted = []; }
  setAttribute(name, value) { this.attributes[name] = String(value); }
  getAttribute(name) { return this.attributes[name] ?? null; }
  removeAttribute(name) { delete this.attributes[name]; }
  insertAdjacentElement(position, element) { this.inserted.push(element); }
  remove() { this.removed = true; }
  focus() { this.focused = true; }
  scrollIntoView() {}
  closest() { return null; }
}

class FakeSelect extends FakeElement {
  options = [];
  value = '';
  get selectedOptions() { return this.options.filter(option => option.value === this.value); }
  replaceChildren(...options) { this.options = options; this.value = options[0]?.value || ''; }
  add(option) { this.options.push(option); }
}

function householdClient(fetch) {
  const fields = new Map();
  const getField = id => {
    if (!fields.has(id)) fields.set(id, new FakeElement(id));
    return fields.get(id);
  };
  const toasts = [];
  const context = vm.createContext({
    document: { getElementById: id => (fields.get(id)?.removed ? null : getField(id)), createElement: () => new FakeElement(), querySelector: () => null }, Option: FakeOption,
    URLSearchParams, setTimeout, clearTimeout, fetch, confirmAction: async () => true, adminText: text => text,
    PUROK_DATA: [], getCheckedSpecialGroups: () => [], csrfRequestHeaders: () => ({ 'X-CSRF-TOKEN': 'test-token' }),
    showToast: message => toasts.push(message), hasStaffPermission: () => true,
    escapeText: value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]))
  });
  vm.runInContext(householdCode + payloadCode + mappingCode + pagerCode, context);
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
  ['household-head', 'household-resident-id', 'household-purok'].forEach(id => fields.set(id, new FakeSelect(id)));
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
  assert.equal(writes[0].url, '/staff/households');
  assert.deepEqual(JSON.parse(writes[0].options.body), { household_name: 'Santos Family', address: 'Shared address', purok_id: null, household_number: 'HH-000042', household_head_resident_id: 1, members: [{ resident_id: 2, relationship_to_household_head: 'Spouse' }] });
});

test('new household save requires a head and keeps the draft for correction', async () => {
  let writes = 0;
  const { context, fields, getField, toasts } = householdClient(async () => { writes++; });
  fields.set('household-head', new FakeSelect('household-head'));

  await context.saveManagedHousehold();

  assert.equal(writes, 0);
  assert.equal(getField('household-save').disabled, false);
  // The message now sits in the form: at the top, and under the household head field it concerns.
  assert.equal(toasts.length, 0);
  assert.equal(getField('household-editor-error').hidden, false);
  assert.match(getField('household-editor-error').textContent, /existing active resident/);
  assert.equal(getField('household-head').getAttribute('aria-invalid'), 'true');
  assert.equal(getField('household-head').getAttribute('aria-describedby'), 'household-head-error');
  assert.match(getField('household-head').inserted[0].textContent, /existing active resident/);
});

test('new households list existing active residents as heads before adding any members', async () => {
  const requests = [];
  const { context, fields, getField } = householdClient(async (url, options) => {
    requests.push({ url, options });
    return { ok: true, json: async () => options.method === 'POST' ? { message: 'Created', household: { id: 9 } } : { data: [{ id: 1, full_name: 'Maria Santos', resident_number: 'RES-1', status: 'active' }, { id: 2, full_name: 'Inactive Resident', resident_number: 'RES-2', status: 'inactive' }], current_page: 1, last_page: 1 } };
  });
  ['household-head', 'household-resident-id', 'household-purok'].forEach(id => fields.set(id, new FakeSelect(id)));
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
  ['household-head', 'household-resident-id', 'household-purok'].forEach(id => fields.set(id, new FakeSelect(id)));
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
  ['household-head', 'household-resident-id', 'household-purok'].forEach(id => fields.set(id, new FakeSelect(id)));
  context.confirmAction = async () => false;
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
  context.adminText = text => text;
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

test('household lookup in the resident form keeps the selected household and lists matches without pagination', async () => {
  const requested = [];
  const { context, fields, getField } = householdClient(async url => { requested.push(url); return { ok: true, json: async () => ({ data: [{ id: 2, household_number: 'HH-000002', head: null, address: 'Other address' }], total: 150, current_page: 1, last_page: 2 }) }; });
  const select = new FakeSelect();
  select.add(new FakeOption('HH-000001', '1'));
  select.value = '1';
  fields.set('res-household-id', select);
  getField('res-household-search').value = 'Other';

  await context.loadResidentHouseholds();

  assert.equal(select.value, '1');
  assert.equal(select.options.length, 3);
  assert.match(select.options[2].textContent, /No household head assigned/);
  const params = new URL(requested[0], 'http://localhost').searchParams;
  assert.equal(params.get('per_page'), '100');
  assert.equal(params.get('page'), '1');
  assert.equal(getField('res-household-hint').hidden, false);
  assert.match(getField('res-household-hint').textContent, /Showing the first 1 of 150 households/);
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
  assert.match(html, /Registered voter/);
  assert.doesNotMatch(html, /<script>|<img/);
});

test('household members show the voter record status and never read a missing record as unregistered', () => {
  const { context } = householdClient();
  const member = (id, extra) => ({ id, full_name: `Member ${id}`, relationship_to_household_head: 'Child', gender: 'Male', status: 'active', ...extra });
  const html = context.householdMembersMarkup({ id: 1, members: [
    member(1, { registered_voter: true, voter_status: 'active' }),
    member(2, { registered_voter: false, voter_status: 'transferred' }),
    member(3, { registered_voter: false, voter_status: null }),
  ] });

  assert.match(html, /<th>Voter registration<\/th>/);
  assert.match(html, /badge-green">Registered voter/);
  assert.match(html, /badge-gray">Transferred/);
  assert.match(html, /voter-status-none">No voter record/);
  assert.doesNotMatch(html, />No</);
});

test('household write requests include CSRF headers and show validation messages', async () => {
  let request;
  const { context } = householdClient(async (url, options) => {
    request = { url, options };
    return { ok: false, json: async () => ({ errors: { is_household_head: ['The resident must be active.'] } }) };
  });

  await assert.rejects(context.householdApi('/staff/households/1/members/2', 'PATCH', { is_household_head: true }), /The resident must be active/);
  assert.equal(request.options.credentials, 'same-origin');
  assert.equal(request.options.headers['X-CSRF-TOKEN'], 'test-token');
  assert.deepEqual(JSON.parse(request.options.body), { is_household_head: true });
});

test('View / Edit replaces the household list with the record, and Back returns to the same row', () => {
  const { context, getField } = householdClient();
  const rowButton = new FakeElement('row-button');
  rowButton.dataset.householdId = '7';
  rowButton.isConnected = true;
  getField('household-editor').hidden = true;

  context.showHouseholdEditor(rowButton);

  assert.equal(getField('household-browser').hidden, true, 'the list is hidden while a record is open');
  assert.equal(getField('household-editor').hidden, false);
  assert.equal(getField('household-editor-title').focused, true, 'focus moves to the record heading');

  rowButton.focused = false;
  context.showHouseholdList();

  assert.equal(getField('household-browser').hidden, false);
  assert.equal(getField('household-editor').hidden, true);
  assert.equal(rowButton.focused, true, 'focus returns to the row that opened the record');
});

test('staff without households.update see the record read-only', () => {
  const { context, getField } = householdClient();

  context.setHouseholdEditable(false);
  for (const id of ['household-name', 'household-address', 'household-purok', 'household-head']) {
    assert.equal(getField(id).disabled, true, `${id} is read-only`);
  }
  assert.equal(getField('household-readonly-note').hidden, false);
  assert.equal(getField('household-add-member').hidden, true);

  context.setHouseholdEditable(true);
  assert.equal(getField('household-name').disabled, false);
  assert.equal(getField('household-readonly-note').hidden, true);
});

test('a rejected save shows each server error under its own field and clears it on the next attempt', () => {
  const { context, getField } = householdClient();

  context.showHouseholdErrors('The address field is required.', { address: ['The address field is required.'], purok_id: ['The selected purok is invalid.'] });

  assert.equal(getField('household-editor-error').hidden, false);
  assert.equal(getField('household-address').getAttribute('aria-invalid'), 'true');
  assert.equal(getField('household-address').getAttribute('aria-describedby'), 'household-address-error');
  assert.equal(getField('household-purok').inserted[0].textContent, 'The selected purok is invalid.');
  assert.equal(getField('household-address').focused, true, 'the first invalid field takes focus');

  context.clearHouseholdErrors();
  assert.equal(getField('household-editor-error').hidden, true);
  assert.equal(getField('household-address').getAttribute('aria-invalid'), null);
});

test('a household list refresh keeps keyboard focus on the same household row', async () => {
  const fetch = async () => ({ ok: true, status: 200, json: async () => ({ data: [{ id: 3, household_name: 'Pamilya Santos', household_number: 'HH-3', address: 'Purok 1', household_size: 2, head: { full_name: 'Jane Santos' } }], current_page: 1, last_page: 1, total: 1, per_page: 15 }) });
  const { context, getField } = householdClient(fetch);
  Object.assign(context, { workspaceState: text => text, workspaceStateRow: (columns, text) => text, householdPagination: () => {}, workspaceError: () => 'error' });
  const list = getField('household-list');
  const oldButton = new FakeElement();
  oldButton.dataset.householdId = '3';
  const newButton = new FakeElement();
  list.contains = element => element === oldButton;
  list.querySelector = selector => (selector === '[data-household-id="3"]' ? newButton : null);
  context.document.activeElement = oldButton;
  getField('household-search').value = 'Santos';

  await context.loadManagedHouseholds();

  assert.match(list.innerHTML, /data-household-id="3"/);
  assert.equal(newButton.focused, true, 'focus moves to the re-rendered button for the same household');
  assert.notEqual(getField('household-search').focused, true);
});
