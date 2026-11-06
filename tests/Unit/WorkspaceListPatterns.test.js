import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const read = path => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const script = read('public/js/admin.js');
const HOSTILE = `<img src=x onerror=alert(1)>'"`;

/** A fake element that records listeners and the dataset its renderer writes. */
function element(extra = {}) {
  const listeners = {};
  return {
    innerHTML: '', dataset: {}, listeners, classes: new Set(), attributes: {},
    classList: { add(name) { this.owner?.classes.add(name); } },
    addEventListener(type, handler) { (listeners[type] ||= []).push(handler); },
    setAttribute(name, value) { this.attributes[name] = value; },
    appendChild(child) { (this.children ||= []).push(child); },
    querySelectorAll: () => [], querySelector: () => null,
    ...extra,
  };
}

async function client() {
  const fields = new Map();
  const context = vm.createContext({
    console, URLSearchParams, URL,
    document: { cookie: '', hidden: false, getElementById: id => fields.get(id) ?? null, querySelector: () => null, querySelectorAll: () => [], addEventListener() {}, createElement: () => element() },
    window: { AUTHENTICATED_USER: { id: 1, name: 'Admin', role: 'admin' }, addEventListener() {} },
    localStorage: { removeItem() {}, setItem() {}, getItem() { return null; } },
    setInterval() { return 1; }, clearInterval() {}, setTimeout() {}, clearTimeout() {},
    fetch: async () => ({ ok: true, json: async () => [] }),
  });
  vm.runInContext(script, context);
  await new Promise(resolve => setImmediate(resolve));
  return { context, fields };
}

/** A control found by event delegation: closest() answers for the data-* selector the handler looks for. */
const clickOn = dataset => ({ target: { closest: selector => (selector.startsWith('[data-') ? { dataset } : null) }, stopPropagation() {} });

test('badges escape their text and only use the fixed tones', async () => {
  const { context } = await client();

  assert.equal(context.workspaceBadge('Active', 'green'), '<span class="badge badge-green">Active</span>');
  assert.equal(context.workspaceBadge(HOSTILE, 'red" onclick="x'), '<span class="badge badge-gray">&lt;img src=x onerror=alert(1)&gt;&#39;&quot;</span>');
});

test('loading and empty rows are announced as status, errors as an alert with a retry, and every row spans the table', async () => {
  const { context } = await client();

  const loading = context.workspaceStateRow(9, 'Loading resident records...', 'loading');
  assert.match(loading, /<td colspan="9" class="resident-table-message workspace-state" data-state="loading"><span role="status" aria-busy="true">Loading resident records\.\.\.<\/span><\/td>/);
  assert.match(context.workspaceStateRow('7"><script>', HOSTILE), /colspan="1"[^>]*data-state="empty"><span role="status">&lt;img/);
  assert.match(context.workspaceState('No accounts match your filters.'), /^<div class="resident-table-message workspace-state" data-state="empty"><span role="status">No accounts/);

  const error = context.workspaceErrorRow(8, new Error(HOSTILE), 'loadIncidents(incidentCurrentPage)');
  assert.match(error, /data-state="error"><span role="alert"><span>&lt;img src=x/);
  assert.match(error, /onclick="loadIncidents\(incidentCurrentPage\)">Try again<\/button>/);
  assert.ok(!error.includes('<img'));
});

test('document request statuses read the same as the x-staff.request-status Blade badge', async () => {
  const { context } = await client();
  const blade = read('resources/views/components/staff/request-status.blade.php');
  const labels = Object.fromEntries([...blade.matchAll(/'(\w+)' => \['([^']+)'/g)].map(match => [match[1], match[2]]));

  assert.equal(Object.keys(labels).length, 6);
  for (const [status, label] of Object.entries(labels)) {
    assert.match(context.documentRequestStatusBadge(status), new RegExp(`data-request-status="${status}"[^>]*>${label}</span>$`));
  }
  assert.match(context.documentRequestStatusBadge('on_hold'), /badge-gray">On Hold<\/span>$/);
});

test('the certificate board renders actions as escaped data attributes and handles them with one delegated listener', async () => {
  const { context, fields } = await client();
  const board = element({ querySelectorAll: () => [], contains: () => true });
  const lanes = [];
  fields.set('cert-kanban-board', Object.assign(board, { appendChild: lane => lanes.push(lane) }));
  const calls = [];
  vm.runInContext(`
    hasStaffPermission = () => true;
    certBoardState = 'ready';
    CERT_REQUESTS.splice(0, CERT_REQUESTS.length, { id: 7, code: ${JSON.stringify(HOSTILE)}, name: ${JSON.stringify(HOSTILE)}, type: 'Barangay Clearance', status: 'Pending', via: 'Online' });
  `, context);
  context.advanceCertStatus = (code, next) => calls.push(['advance', code, next]);
  context.openRejectRequest = (id, code) => calls.push(['reject', id, code]);
  context.openViewCertReq = code => calls.push(['view', code]);

  context.renderCertKanban();
  context.renderCertKanban();

  const markup = lanes.map(lane => lane.innerHTML).join('');
  assert.ok(!/onclick=/.test(markup), 'no inline handlers');
  assert.ok(!markup.includes('<img'), 'request text is escaped');
  assert.match(markup, /data-cert-action="advance" data-code="&lt;img[^"]*&#39;&quot;" data-next="In Progress"/);
  assert.match(markup, /class="cert-card-name cert-card-open" data-cert-action="view" data-code="[^"]+" data-no-admin-translate/);
  assert.equal(board.listeners.click.length, 1, 're-rendering does not add listeners');

  board.listeners.click[0](clickOn({ certAction: 'advance', code: 'REQ-1', next: 'In Progress' }));
  board.listeners.click[0](clickOn({ certAction: 'reject', code: 'REQ-1', requestId: '7' }));
  board.listeners.click[0](clickOn({ certAction: 'view', code: 'REQ-1' }));
  assert.deepEqual(calls, [['advance', 'REQ-1', 'In Progress'], ['reject', 7, 'REQ-1'], ['view', 'REQ-1']]);
});

test('resident row buttons are delegated from the table body, bound once, and names stay untranslated data', async () => {
  const { context, fields } = await client();
  const tbody = element();
  const rows = [];
  fields.set('records-tbody', Object.assign(tbody, { appendChild: row => rows.push(row) }));
  const calls = [];
  vm.runInContext(`RESIDENTS.splice(0, RESIDENTS.length,
    { id: ${JSON.stringify(HOSTILE)}, databaseId: 4, name: 'Admin', dob: '1990-01-01', purok: 'Purok 1', gender: 'Female', civil: 'Single', status: 'Active', archived: false },
    { id: 'ANB-2', databaseId: 5, name: 'Old Record', dob: '1990-01-01', purok: 'Purok 1', gender: 'Male', civil: 'Single', status: 'Inactive', archived: true });`, context);
  context.openViewResident = id => calls.push(['view', id]);
  context.openEditResident = id => calls.push(['edit', id]);
  context.deleteResident = id => calls.push(['archive', id]);
  context.restoreResident = id => calls.push(['restore', id]);

  context.renderResidentsTable();
  context.renderResidentsTable();

  assert.equal(tbody.listeners.click.length, 1);
  const markup = rows[0].innerHTML;
  assert.ok(!/onclick=/.test(markup) && !markup.includes('<img'));
  assert.match(markup, /<strong data-no-admin-translate>Admin<\/strong>/);
  assert.match(markup, /data-resident-action="edit" data-resident="&lt;img src=x onerror=alert\(1\)&gt;&#39;&quot;"/);
  assert.match(rows[1].innerHTML, /data-resident-action="restore" data-database-id="5"/);

  for (const action of ['view', 'edit', 'archive']) tbody.listeners.click[0](clickOn({ residentAction: action, resident: 'ANB-1' }));
  tbody.listeners.click[0](clickOn({ residentAction: 'restore', databaseId: '5' }));
  tbody.listeners.click[0]({ target: { closest: () => null } });
  assert.deepEqual(calls, [['view', 'ANB-1'], ['edit', 'ANB-1'], ['archive', 'ANB-1'], ['restore', 5]]);
});

test('incident rows keep complainant, respondent and location as untranslated data', async () => {
  const { context, fields } = await client();
  const rows = [];
  fields.set('incidents-tbody', element({ appendChild: row => rows.push(row) }));
  vm.runInContext(`INCIDENTS.splice(0, INCIDENTS.length, { id: '3', incident_number: 'INC-3', incident_type: 'Noise', occurred_at_display: 'Nov 2, 2026 3:30 PM', location: 'Online', complainant_name: ${JSON.stringify(HOSTILE)}, respondent_name: 'Admin', severity: 'high', severity_label: 'High', status: 'referred', status_label: 'Referred', can_update: false, can_archive: false });`, context);

  context.renderIncidents(1);

  const markup = rows[0].innerHTML;
  assert.match(markup, /<td data-no-admin-translate>Online<\/td>/);
  assert.match(markup, /<td data-no-admin-translate>&lt;img/);
  assert.match(markup, /<td data-no-admin-translate>Admin<\/td>/);
  assert.match(markup, /<span class="badge badge-red">High<\/span>/);
  assert.match(markup, /<span class="badge badge-purple">Referred<\/span>/);
  assert.match(markup, /onclick="openViewIncident\(3\)"/);
});
