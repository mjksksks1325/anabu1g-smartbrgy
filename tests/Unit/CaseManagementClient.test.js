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
  const bpo = requests.find(request => request.url.startsWith('/admin/protection-orders?'));
  bpo.resolve({ data: [], last_page: 1 });
  await second;
  requests[0].resolve({ remarks: 'Wrong case', history: [], restrictions: [] });
  await first;
  assert.match(field('incident-case-history').innerHTML, /&lt;script&gt;latest notes/);
  assert.ok(!field('incident-case-history').innerHTML.includes('Wrong case'));
});

test('restriction review disables duplicate actions and uses the authorized review endpoint', async () => {
  let requests = 0;
  let release;
  const { context } = caseClient(async (url, method) => {
    if (method === 'POST') {
      requests++;
      assert.equal(url, '/admin/request-restrictions/7/review');
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
