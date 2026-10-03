import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/household-profiling.js', import.meta.url), 'utf8');
const adminSource = readFileSync(new URL('../../public/js/admin.js', import.meta.url), 'utf8');

function profilingClient(fetch) {
  const fields = new Map();
  const field = id => {
    if (!fields.has(id)) fields.set(id, { value: '', disabled: false, textContent: '', innerHTML: '' });
    return fields.get(id);
  };
  const context = vm.createContext({
    document: { getElementById: field, querySelectorAll: () => [] }, fetch, FormData, URLSearchParams,
    csrfRequestHeaders: () => ({ 'X-CSRF-TOKEN': 'test-token' }), confirm: () => true,
    escapeText: value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])),
  });
  vm.runInContext(source, context);
  vm.runInContext(`profilingPreview = { token: 'preview-token', fields: [], rows: [{ id: 0, source_row: 2, fields: { household_group: 'Family', first_name: 'Juan', last_name: 'Cruz', purok: 'Purok 1' }, matches: [], errors: [], is_head: true, action: 'create' }], summary: { invalid: 0, ready: 1 } }; profilingValidated = true;`, context);
  context.setProfilingBusy(false);
  return { context, field };
}

test('editing a reviewed row disables save until a successful validation', async () => {
  const { context, field } = profilingClient(async () => ({ ok: true, json: async () => ({ summary: { invalid: 0, ready: 1 } }) }));
  assert.equal(field('profiling-save').disabled, false);
  context.updateProfilingRow(0, 'address', 'Reviewed address');
  assert.equal(field('profiling-save').disabled, true);
  await context.reviewHouseholdProfiling();
  assert.equal(field('profiling-save').disabled, false);
});

test('a failed validation keeps save disabled and shows the server error', async () => {
  const { context, field } = profilingClient(async () => ({ ok: false, json: async () => ({ errors: { file: ['The preview expired.'] } }) }));
  context.updateProfilingRow(0, 'first_name', 'Changed');
  await context.reviewHouseholdProfiling();
  assert.equal(field('profiling-save').disabled, true);
  assert.equal(field('profiling-errors').textContent, 'The preview expired.');
});

test('duplicate save clicks send one request while preserving the draft on failure', async () => {
  let requests = 0;
  let release;
  const { context, field } = profilingClient(async () => {
    requests++;
    await new Promise(resolve => { release = resolve; });
    return { ok: false, json: async () => ({ message: 'Review the changed record.' }) };
  });
  const pending = context.saveHouseholdProfiling();
  await context.saveHouseholdProfiling();
  assert.equal(requests, 1);
  release();
  await pending;
  assert.match(field('profiling-errors').textContent, /changed record/);
  assert.equal(vm.runInContext('profilingPreview.token', context), 'preview-token');
});

test('import preview escapes uploaded names headers and household markers', () => {
  const { context, field } = profilingClient();
  vm.runInContext(`profilingPreview.fields = ['first_name']; profilingPreview.headers = ['<img src=x onerror=alert(1)>']; profilingPreview.mapping = {}; profilingPreview.rows[0].fields.first_name = '<script>alert(1)</script>'; profilingPreview.rows[0].fields.household_group = '<svg onload=alert(1)>';`, context);
  context.renderProfilingMapping();
  context.renderProfilingPreview();
  assert.ok(!field('profiling-mapping').innerHTML.includes('<img'));
  assert.ok(!field('profiling-households').innerHTML.includes('<script>'));
  assert.ok(!field('profiling-households').innerHTML.includes('<svg'));
});

test('changing column mapping rebuilds rows rather than reusing prior mapped fields', async () => {
  let payload;
  const { context } = profilingClient(async (_url, options) => {
    payload = JSON.parse(options.body);
    return { ok: true, json: async () => ({ summary: { invalid: 0, ready: 1 } }) };
  });
  context.changeProfilingMapping();
  await context.reviewHouseholdProfiling();
  assert.deepEqual(payload.rows, []);
});

test('selecting another upload clears the previous preview and disables save', () => {
  const { context, field } = profilingClient();
  context.clearProfilingUpload();
  assert.equal(vm.runInContext('profilingPreview', context), null);
  assert.equal(field('profiling-save').disabled, true);
  assert.equal(field('profiling-review').disabled, true);
});

test('clicking a purok opens its filtered household list in a modal', async () => {
  const { context, field } = profilingClient();
  const opened = [];
  context.PUROK_DATA = [{ databaseId: 7, label: 'Purok 7' }];
  context.closeModal = () => {};
  context.openModal = id => opened.push(id);
  context.householdApi = async url => {
    assert.equal(new URL(url, 'https://example.test').searchParams.get('purok_id'), '7');
    return { data: [{ id: 8, household_name: 'Cruz <Family>', household_number: 'HH-8', address: '<img src=x>', head: { full_name: 'Juan Cruz' }, household_size: 3 }], last_page: 1, current_page: 1 };
  };

  await context.openPurokHouseholds(7);

  assert.deepEqual(opened, ['modal-purok-households']);
  assert.equal(field('purok-households-title').textContent, 'Purok 7 - Registered Households');
  assert.match(field('purok-households-tbody').innerHTML, /HH-8/);
  assert.match(field('purok-households-tbody').innerHTML, /Cruz &lt;Family&gt;/);
  assert.match(field('purok-households-tbody').innerHTML, /viewDemographicHousehold\(8\)/);
  assert.ok(!field('purok-households-tbody').innerHTML.includes('<img'));
  assert.equal(field('purok-households-pagination').innerHTML, '');
});

test('switching puroks ignores the previous household response', async () => {
  const { context, field } = profilingClient();
  context.PUROK_DATA = [{ databaseId: 1, label: 'First' }, { databaseId: 2, label: 'Second' }];
  context.closeModal = context.openModal = () => {};
  const pending = [];
  context.householdApi = () => new Promise(resolve => pending.push(resolve));
  const first = context.openPurokHouseholds(1);
  const second = context.openPurokHouseholds(2);
  pending[1]({ data: [], last_page: 1, current_page: 1 });
  await second;
  pending[0]({ data: [{ id: 1, household_number: 'Wrong purok' }], last_page: 1, current_page: 1 });
  await first;
  assert.equal(field('purok-households-title').textContent, 'Second - Registered Households');
  assert.match(field('purok-households-tbody').innerHTML, /No households registered/);
  assert.ok(!field('purok-households-tbody').innerHTML.includes('Wrong purok'));
});

test('family search resets pagination and preserves the submitted search on subsequent pages', async () => {
  const { context, field } = profilingClient();
  context.PUROK_DATA = [{ databaseId: 7, label: 'Purok 7' }];
  context.closeModal = context.openModal = () => {};
  const queries = [];
  context.householdApi = async url => {
    queries.push(new URL(url, 'https://example.test').searchParams);
    return { data: [{ id: 8, household_name: 'Pamilya Manalac', household_size: 3 }], total: 16, last_page: 2, current_page: Number(queries.at(-1).get('page')) };
  };
  await context.openPurokHouseholds(7);
  field('purok-households-search').value = '  Manalac & Cruz  ';

  await context.searchPurokHouseholds();
  field('purok-households-search').value = 'Unsubmitted edit';
  await context.loadPurokHouseholds(7, 2);

  assert.equal(queries[1].get('search'), 'Manalac & Cruz');
  assert.equal(queries[1].get('page'), '1');
  assert.equal(queries[2].get('search'), 'Manalac & Cruz');
  assert.equal(queries[2].get('page'), '2');
  assert.equal(queries[2].get('purok_id'), '7');
  assert.equal(field('purok-households-results').textContent, '16 matching households');
});

test('an empty family search shows helpful feedback and clearing it restores the purok list', async () => {
  const { context, field } = profilingClient();
  context.PUROK_DATA = [{ databaseId: 7, label: 'Purok 7' }];
  context.closeModal = context.openModal = () => {};
  const queries = [];
  context.householdApi = async url => {
    queries.push(new URL(url, 'https://example.test').searchParams);
    return { data: [], total: 0, last_page: 1, current_page: 1 };
  };
  await context.openPurokHouseholds(7);
  field('purok-households-search').value = 'Unknown family';

  await context.searchPurokHouseholds();

  assert.match(field('purok-households-tbody').innerHTML, /No households match your search/);
  await context.clearPurokHouseholdSearch();
  assert.equal(field('purok-households-search').value, '');
  assert.equal(queries.at(-1).has('search'), false);
  assert.match(field('purok-households-tbody').innerHTML, /No households registered/);
});

test('a slower previous family search cannot replace the latest matching households', async () => {
  const { context, field } = profilingClient();
  context.PUROK_DATA = [{ databaseId: 7, label: 'Purok 7' }];
  context.closeModal = context.openModal = () => {};
  context.householdApi = async () => ({ data: [], last_page: 1, current_page: 1 });
  await context.openPurokHouseholds(7);
  const pending = [];
  context.householdApi = () => new Promise(resolve => pending.push(resolve));
  field('purok-households-search').value = 'Old family';
  const old = context.searchPurokHouseholds();
  field('purok-households-search').value = 'New family';
  const latest = context.searchPurokHouseholds();

  pending[1]({ data: [{ id: 2, household_name: 'New family', household_size: 2 }], total: 1, last_page: 1, current_page: 1 });
  await latest;
  pending[0]({ data: [{ id: 1, household_name: 'Old family', household_size: 1 }], total: 1, last_page: 1, current_page: 1 });
  await old;

  assert.match(field('purok-households-tbody').innerHTML, /New family/);
  assert.ok(!field('purok-households-tbody').innerHTML.includes('Old family'));
});

test('household list failures show a readable escaped error', async () => {
  const { context, field } = profilingClient();
  context.PUROK_DATA = [{ databaseId: 1, label: 'First' }];
  context.closeModal = context.openModal = () => {};
  context.householdApi = async () => { throw new Error('<script>Failed</script>'); };
  await context.openPurokHouseholds(1);
  assert.match(field('purok-households-tbody').innerHTML, /&lt;script&gt;Failed/);
});

test('clicking a household shows its family members in the detail modal', async () => {
  const { context, field } = profilingClient();
  const opened = [];
  const closed = [];
  context.openModal = id => opened.push(id);
  context.closeModal = id => closed.push(id);
  context.householdMembersMarkup = household => household.members.map(member => member.full_name).join(', ');
  context.householdApi = async url => {
    assert.equal(url, '/admin/households/8');
    return { household_number: 'HH-8', address: 'Block 1', purok: 'Purok 7', head: { full_name: 'Juan Cruz' }, members: [{ full_name: 'Juan Cruz' }, { full_name: 'Maria Cruz' }] };
  };
  vm.runInContext('let demographicHouseholdVersion = 0;\n' + adminSource.slice(adminSource.indexOf('async function viewDemographicHousehold('), adminSource.indexOf('async function refreshHouseholdDemographics(')), context);

  await context.viewDemographicHousehold(8);

  assert.deepEqual(closed, ['modal-purok-households']);
  assert.deepEqual(opened, ['modal-household-readonly']);
  assert.match(field('household-readonly-content').innerHTML, /Juan Cruz, Maria Cruz/);
});

test('purok cards show household counts and support click and keyboard access without inline lists', () => {
  const { context, field } = profilingClient();
  context.syncPurokSelects = () => {};
  context.RESIDENTS = [];
  context.DEMOGRAPHIC_SUMMARY = { total: 0 };
  context.PUROK_DATA = [{ databaseId: 7, label: 'Purok 7', key: 'Purok 7', color: '#fff', householdsCount: 4 }];
  vm.runInContext(adminSource.slice(adminSource.indexOf('function renderPurokCards()'), adminSource.indexOf('function renderAgeDistribution()')), context);
  context.renderPurokCards();
  const html = field('demo-purok-grid').innerHTML;
  assert.match(html, /4 households/);
  assert.ok(html.indexOf('of total population</div>') < html.indexOf('>4 households</div>'));
  assert.match(html, /openPurokHouseholds\(7\)/);
  assert.match(html, /tabindex="0"/);
  assert.ok(!html.includes('<details'));
});

test('selecting a CSV uploads multipart data and shows the returned preview', async () => {
  let requests = 0;
  const { context, field } = profilingClient(async (url, options) => {
    requests++;
    assert.equal(url, '/admin/resident-profiling-imports/preview');
    assert.ok(options.body instanceof FormData);
    assert.equal(options.headers['X-CSRF-TOKEN'], 'test-token');
    assert.equal(options.headers['Content-Type'], undefined);
    assert.equal(await options.body.get('file').text(), 'first_name\nJuan');
    return { ok: true, json: async () => ({ token: 'new-token', fields: ['first_name'], headers: ['first_name'], mapping: { first_name: 0 }, rows: [{ id: 0, source_row: 2, fields: { first_name: 'Juan', last_name: 'Cruz', household_group: 'Family 1' }, matches: [], errors: [], action: 'create', is_head: true }], summary: { households: 1, residents: 1, heads: 1, duplicates: 0, invalid: 0, ready: 1 } }) };
  });
  field('profiling-file').files = [new Blob(['first_name\nJuan'], { type: 'text/csv' })];
  await context.selectHouseholdProfilingFile();
  assert.equal(requests, 1);
  assert.equal(field('profiling-preview').hidden, false);
  assert.match(field('profiling-households').innerHTML, /Juan Cruz/);
  assert.match(field('profiling-status').textContent, /Preview ready/);
  assert.equal(field('profiling-upload').disabled, false);
  assert.equal(field('profiling-save').disabled, false);
});

test('a rejected CSV upload shows validation errors and allows retry without stale preview', async () => {
  const { context, field } = profilingClient(async () => ({ ok: false, json: async () => ({ errors: { file: ['The file must contain a header row.'] } }) }));
  field('profiling-file').files = [new Blob(['invalid'])];
  await context.selectHouseholdProfilingFile();
  assert.match(field('profiling-errors').textContent, /header row/);
  assert.equal(field('profiling-upload').disabled, false);
  assert.equal(field('profiling-save').disabled, true);
  assert.equal(field('profiling-preview').hidden, true);
});

test('expired HTML session responses show a useful message instead of a JSON parse error', async () => {
  const { context, field } = profilingClient(async () => ({ ok: false, status: 419, json: async () => { throw new SyntaxError('Unexpected token'); } }));
  field('profiling-file').files = [new Blob(['first_name\nJuan'])];
  await context.selectHouseholdProfilingFile();
  assert.match(field('profiling-errors').textContent, /session expired/);
  assert.equal(field('profiling-upload').disabled, false);
});
