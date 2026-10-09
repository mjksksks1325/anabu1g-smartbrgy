import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/case-management.js', import.meta.url), 'utf8');
const adminSource = readFileSync(new URL('../../public/js/admin.js', import.meta.url), 'utf8');
function caseClient(api) {
  const fields = new Map();
  const field = id => {
    if (!fields.has(id)) fields.set(id, { value: '', innerHTML: '', textContent: '' });
    return fields.get(id);
  };
  const context = vm.createContext({
    document: { getElementById: id => id === 'inc-assigned-to' ? null : field(id) },
    window: { AUTHENTICATED_USER: { role: 'admin' } },
    URLSearchParams, setTimeout, clearTimeout, householdApi: api, confirm: () => true,
    showToast() {}, closeModal() {}, openModal() {},
    escapeText: value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])),
  });
  vm.runInContext(source, context);
  return { context, field };
}

test('restriction list escapes internal decision text and shows explicit review actions only to admins', async () => {
  const { context, field } = caseClient(async () => ({ data: [{ id: 1, resident: { full_name: '<script>name</script>' }, status: 'pending_review', reason_category: '<img src=x>', internal_reason: '<svg onload=alert(1)>' }], total: 1, current_page: 1, last_page: 1 }));
  await context.loadRestrictions();
  assert.match(field('restrictions-tbody').innerHTML, /Review &amp; Activate/);
  assert.match(field('restrictions-tbody').innerHTML, /&lt;script&gt;/);
  assert.ok(!field('restrictions-tbody').innerHTML.includes('<svg'));
  context.window.AUTHENTICATED_USER.role = 'staff';
  await context.loadRestrictions();
  assert.ok(!field('restrictions-tbody').innerHTML.includes('reviewRestriction('));
});

test('case history escapes narrative and a slower previous case does not replace the selected case', async () => {
  const requests = [];
  const { context, field } = caseClient(url => new Promise(resolve => requests.push({ url, resolve })));
  const first = context.loadCaseHistory(1);
  const second = context.loadCaseHistory(2);
  requests[1].resolve({ remarks: '<script>latest notes</script>', history: [], restrictions: [] });
  await new Promise(resolve => setImmediate(resolve));
  const bpo = requests.find(request => request.url.startsWith('/staff/protection-orders?'));
  bpo.resolve({ data: [], last_page: 1 });
  await second;
  requests[0].resolve({ remarks: 'Wrong case', history: [], restrictions: [] });
  await first;
  assert.match(field('incident-case-history').innerHTML, /&lt;script&gt;latest notes/);
  assert.ok(!field('incident-case-history').innerHTML.includes('Wrong case'));
});

test('case history shows Philippine time and readable status changes without raw separators', async () => {
  const { context, field } = caseClient(async url => url.startsWith('/staff/incidents/') ? {
    history: [{ created_at: '2026-10-09T08:55:56.000000Z', actor: '<img src=x>', previous_status: null, status: 'under_review' }],
    restrictions: [{ id: 2, affected_document_type: '<script>test</script>', status: 'active' }],
  } : { data: [], last_page: 1 });
  await context.loadCaseHistory(1);
  const html = field('incident-case-history').innerHTML;
  assert.match(html, /Oct 9, 2026, 4:55 PM \(PHT\)/);
  assert.match(html, /datetime="2026-10-09T08:55:56.000Z"/);
  assert.match(html, /New <span aria-label="changed to">&rarr;<\/span> Under Review/);
  assert.match(html, /&lt;img src=x&gt;/);
  assert.match(html, /Restriction #2: &lt;script&gt;test&lt;\/script&gt;/);
  assert.ok(!html.includes(' ? '));
  assert.ok(!html.includes('<img'));
});

test('missing case history and malformed timestamps have understandable fallbacks', async () => {
  const { context, field } = caseClient(async url => url.startsWith('/staff/incidents/') ? { history: [] } : { data: [], last_page: 1 });
  await context.loadCaseHistory(1);
  assert.match(field('incident-case-history').innerHTML, /No case history yet/);
  const html = context.renderCaseHistoryEvent({ created_at: 'invalid', status: 'open' });
  assert.match(html, /Date unavailable/);
  assert.match(html, /System/);
  assert.ok(!html.includes('Invalid Date'));
});

test('restriction review disables duplicate actions and uses the authorized review endpoint', async () => {
  let requests = 0;
  let release;
  const { context } = caseClient(async (url, method) => {
    if (method === 'POST') {
      requests++;
      assert.equal(url, '/staff/request-restrictions/7/review');
      await new Promise(resolve => { release = resolve; });
      return { message: 'Reviewed restriction activated.' };
    }
    return { data: [], current_page: 1, last_page: 1, total: 0 };
  });
  const button = { disabled: false };
  const first = context.reviewRestriction(7, button);
  await context.reviewRestriction(7, button);
  assert.equal(requests, 1);
  release();
  await first;
  assert.equal(button.disabled, false);
});

test('incident filters send server-side category date and assignment criteria', async () => {
  let url;
  const fields = new Map([
    ['incidents-tbody', { innerHTML: '' }], ['incident-category-filter', { value: 'Noise Complaint' }],
    ['incident-date-from', { value: '2026-10-01' }], ['incident-date-to', { value: '2026-10-04' }], ['incident-assignee-filter', { value: '7' }],
  ]);
  const context = vm.createContext({ document: { getElementById: id => fields.get(id) }, URLSearchParams, INCIDENTS: [],
    fetch: async requested => { url = requested; return { ok: true, json: async () => ({ data: [], current_page: 1, last_page: 1 }) }; }, renderIncidents() {}, updateIncidentSummary() {}, escapeText: value => value,
  });
  vm.runInContext('let incidentCurrentPage = 1; let incidentLastPage = 1;\n' + adminSource.slice(adminSource.indexOf('async function loadIncidents('), adminSource.indexOf('function renderIncidents(')), context);
  await context.loadIncidents(2);
  const params = new URL(url, 'https://example.test').searchParams;
  assert.equal(params.get('incident_type'), 'Noise Complaint');
  assert.equal(params.get('date_from'), '2026-10-01');
  assert.equal(params.get('date_to'), '2026-10-04');
  assert.equal(params.get('assigned_to'), '7');
  assert.equal(params.get('page'), '2');
});

test('ordinary staff creation omits restricted flags while authorized restricted creation and editing retain them', async () => {
  for (const [id, allowed, checkbox, expected] of [['', false, null, null], ['', false, { checked: false }, null], ['', true, { checked: true }, '1'], ['1', false, { checked: false }, '0']]) {
    let payload;
    const context = vm.createContext({ FormData, incidentCurrentPage: 1,
      document: { getElementById: name => name === 'inc-edit-id' ? { value: id } : name === 'inc-sensitive' ? checkbox : null, querySelector: () => null },
      hasStaffPermission: () => allowed, csrfRequestHeaders: () => ({}), closeModal() {}, showToast() {}, loadIncidents() {}, refreshDashboardStats() {},
      fetch: async (url, options) => { payload = options.body; return { ok: true, json: async () => ({ message: 'Submitted' }) }; },
    });
    vm.runInContext(adminSource.slice(adminSource.indexOf('async function saveIncident('), adminSource.indexOf('async function archiveIncident(')), context);
    await context.saveIncident();
    assert.equal(payload.get('is_sensitive'), expected);
  }
});

test('authorized editing retains a custom submitted category which is absent from the preset list', () => {
  const category = 'Traffic obstruction <reported>';
  const fields = new Map();
  const select = { options: [{ value: 'Noise Complaint' }], value: '', add(option) { this.options.push(option); } };
  const field = id => {
    if (id === 'inc-type') return select;
    if (!fields.has(id)) fields.set(id, { value: '', style: {}, textContent: '', innerHTML: '' });
    return fields.get(id);
  };
  const context = vm.createContext({ INCIDENTS: [{ id: 1, incident_type: category }],
    document: { getElementById: field, querySelector: () => ({ textContent: '' }) },
    Option: class { constructor(text, value) { this.text = text; this.value = value; } }, toggleIncidentResolution() {}, openModal() {},
  });
  vm.runInContext(adminSource.slice(adminSource.indexOf('function openEditIncident('), adminSource.indexOf('function openAddIncident(')), context);
  context.openEditIncident(1);
  assert.equal(select.value, category);
  assert.equal(select.options[1].text, category);
  context.openEditIncident(1);
  assert.equal(select.options.length, 2);
});
