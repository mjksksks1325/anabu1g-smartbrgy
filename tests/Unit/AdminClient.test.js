import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const script = readFileSync(new URL('../../public/js/admin.js', import.meta.url), 'utf8');

/** Mirrors App\CertificateType::feeSchedule(), which the staff page passes as window.CERTIFICATE_FEES. */
const FEE_SCHEDULE = {
  'Barangay Clearance': { amount: 25, confirmed: true }, 'Certificate of Residency': { amount: 25, confirmed: true },
  'Certificate of Indigency': { amount: 0, confirmed: true }, 'Registered Voter Certification': { amount: 25, confirmed: true },
  'Barangay ID': { amount: 100, confirmed: true }, 'First Time Jobseeker': { amount: 0, confirmed: true },
  'Business Clearance': { amount: 200, confirmed: false },
};

test('background snapshots skip unchanged, paused and stale-page data', async () => {
  const { context, fields } = await client();
  const app = {};
  fields.set('app', app);
  context.window.location = { href: 'http://example.test/staff/residents' };
  let paused = false;
  context.window.SiteLiveUpdates = { isPaused: () => paused };
  const request = { app, url: context.window.location.href };
  assert.equal(context.acceptWorkspaceSnapshot('test', { count: 1 }, false, null), true);
  assert.equal(context.acceptWorkspaceSnapshot('test', { count: 1 }, true, request), false);
  paused = true;
  assert.equal(context.acceptWorkspaceSnapshot('test', { count: 2 }, true, request), false);
  paused = false;
  assert.equal(context.acceptWorkspaceSnapshot('test', { count: 2 }, true, request), true);
  context.window.location.href = 'http://example.test/staff/incidents';
  assert.equal(context.acceptWorkspaceSnapshot('test', { count: 3 }, true, request), false);
  context.window.location.href = request.url;
  fields.set('app', {});
  assert.equal(context.acceptWorkspaceSnapshot('test', { count: 3 }, true, request), false);
});

test('live workspace refresh keeps the current page of the visible module', async () => {
  const { context } = await client();
  let screen = 'screen-records';
  context.document.querySelector = () => ({ id: screen });
  context.hasStaffPermission = () => true;
  context.window.SiteLiveUpdates = { isPaused: () => false };
  const calls = [];
  for (const name of ['loadResidents', 'loadVoterRegistry', 'loadIncidents', 'loadRequestRecords', 'reloadUsers', 'reloadAuditLog', 'loadPuroks', 'refreshIssuedCertificates']) {
    context[name] = async (...args) => calls.push([name, ...args]);
  }
  context.refreshDashboardStats = async () => {};
  vm.runInContext('residentCurrentPage = 3; voterCurrentPage = 2; incidentCurrentPage = 4; rrCurrentPage = 5; issuedCertificatesPage = 2;', context);
  for (const [id, loader, page] of [['records', 'loadResidents', 3], ['voters', 'loadVoterRegistry', 2], ['incidents', 'loadIncidents', 4], ['request-records', 'loadRequestRecords', 5], ['certificates', 'refreshIssuedCertificates', 2]]) {
    screen = `screen-${id}`;
    await context.refreshWorkspaceLive();
    const call = calls.pop();
    assert.equal(call[0], loader);
    assert.equal(call[1], page);
    assert.equal(call[2].background, true);
    assert.equal(calls.length, 0);
  }
  screen = 'screen-dashboard';
  await context.refreshWorkspaceLive();
  assert.equal(calls[0][0], 'loadPuroks');
  assert.equal(calls[0][1].background, true);
});

test('a folder movement alert opens the matching RFID movement history', async () => {
  const { context, fields } = await client();
  const destinations = [];
  const activity = { innerHTML: '' };
  fields.set('dash-recent-activity', activity);
  context.window.location = { assign: url => destinations.push(url) };
  vm.runInContext(`dashboardActivityItems = [{ type: 'file_movement', reference: 'F-005', title: 'Folder moved with no cabinet access', detail: 'F-005 — Removed', time: 'Just now', url: '/staff/rfid-file-tracking?search=F-005#movement-18' }];`, context);
  context.renderDashboardActivity();
  assert.match(activity.innerHTML, /dashboard-activity-mark incident/);
  await context.openDashboardActivity(0);
  assert.deepEqual(destinations, ['/staff/rfid-file-tracking?search=F-005#movement-18']);
});

async function client() {
  const fields = new Map();
  const windowEvents = new Map();
  const documentEvents = new Map();
  const context = vm.createContext({
    console, URLSearchParams, Blob, URL, FormData,
    document: {
      cookie: '', hidden: false,
      getElementById: id => fields.get(id) ?? null,
      querySelector: () => null, querySelectorAll: () => [], addEventListener(type, handler) {
        const handlers = documentEvents.get(type) || [];
        handlers.push(handler);
        documentEvents.set(type, handlers);
      },
    },
    window: { AUTHENTICATED_USER: { id: 1, name: 'Admin', role: 'admin' }, addEventListener(type, handler) { windowEvents.set(type, handler); } },
    localStorage: { removeItem() {}, setItem() {}, getItem() { return null; } },
    setInterval() { return 1; }, clearInterval() {}, setTimeout() {}, clearTimeout() {},
    fetch: async () => ({ ok: true, json: async () => [] }),
  });
  vm.runInContext(script, context);
  await new Promise(resolve => setImmediate(resolve));
  context.messages = [];
  vm.runInContext('showToast = (message, type) => messages.push({ message, type });', context);
  return { context, fields, windowEvents, documentEvents };
}

test('workspace screen switching records clean URLs and back navigation restores the active screen', async () => {
  const { context, fields, windowEvents } = await client();
  const pushed = [];
  context.window.ADMIN_SCREEN_ROUTES = { dashboard: 'http://localhost/admin', records: 'http://localhost/staff/residents' };
  context.window.location = { href: 'http://localhost/admin', pathname: '/admin' };
  context.window.history = { pushState(state, title, url) {
    pushed.push({ state, url });
    context.window.location.href = url;
    context.window.location.pathname = new URL(url).pathname;
  } };
  const screens = ['dashboard', 'records'].map(id => {
    const classes = new Set(id === 'dashboard' ? ['active'] : []);
    const element = { classes, classList: { add: name => classes.add(name), remove: name => classes.delete(name) }, setAttribute() {}, focus() {}, querySelector: () => null };
    fields.set('screen-' + id, element);
    return element;
  });
  const navigation = ['dashboard', 'records'].map(id => {
    const classes = new Set();
    return { classes, current: '', getAttribute: name => name === 'data-screen' ? id : null,
      setAttribute(name, value) { if (name === 'aria-current') this.current = value; },
      classList: { add: name => classes.add(name), remove: name => classes.delete(name) } };
  });
  context.document.querySelectorAll = selector => selector === '.content' ? screens : selector === '.nav-item' ? navigation : [];
  vm.runInContext('currentUserAccess = "Staff Access"; showLoadingBar = () => {}; toggleNavigation = () => {}; refreshDashboardStats = () => {};', context);

  context.showScreen('records', navigation[1]);
  assert.equal(pushed[0].url, 'http://localhost/staff/residents');
  assert.equal(navigation[1].current, 'page');
  assert.equal(screens[1].classes.has('active'), true);
  context.window.location.pathname = '/admin';
  context.window.location.href = 'http://localhost/admin';
  windowEvents.get('popstate')();
  assert.equal(pushed.length, 1);
  assert.equal(navigation[0].current, 'page');
  assert.equal(screens[0].classes.has('active'), true);
  assert.equal(screens[1].classes.has('active'), false);
  context.window.location.pathname = '/staff/residents';
  context.window.location.href = 'http://localhost/staff/residents';
  windowEvents.get('popstate')();
  assert.equal(navigation[1].current, 'page');
  assert.equal(pushed.length, 1);
});

test('workspace URL filter parameters initialize resident pagination and audit filters', async () => {
  const { context, fields } = await client();
  fields.set('residents-search', { value: '' });
  fields.set('audit-search', { value: '' });
  fields.set('audit-date-filter', { value: '' });
  context.window.ADMIN_ACTIVE_SCREEN = 'records';
  context.window.location = { search: '?search=Santos&status=archived&page=2' };
  context.restoreAdminFiltersFromUrl();
  assert.equal(fields.get('residents-search').value, 'Santos');
  assert.equal(vm.runInContext('residentCurrentPage', context), 2);
  assert.equal(vm.runInContext('residentStatusFilter', context), 'archived');
  context.window.ADMIN_ACTIVE_SCREEN = 'audit';
  context.window.location.search = '?search=Juan&date=2026-10-01';
  context.restoreAdminFiltersFromUrl();
  assert.equal(fields.get('audit-search').value, 'Juan');
  assert.equal(fields.get('audit-date-filter').value, '2026-10-01');
  assert.equal(vm.runInContext('auditCurrentSearch', context), 'juan');
});

test('manual issuance displays the confirmed clearance and residency fees', async () => {
  const { context, fields } = await client();
  context.window.CERTIFICATE_FEES = FEE_SCHEDULE;
  const typeField = { value: 'Barangay Clearance' };
  const feeField = { value: '' };
  fields.set('manual-certificate-type', typeField);
  fields.set('manual-certificate-fee', feeField);

  context.updateManualCertificateFee();
  assert.equal(feeField.value, 'PHP 25.00');
  typeField.value = 'Certificate of Residency';
  context.updateManualCertificateFee();
  assert.equal(feeField.value, 'PHP 25.00');
  typeField.value = 'Registered Voter Certification';
  context.updateManualCertificateFee();
  assert.equal(feeField.value, 'PHP 25.00');
});

test('resident editing only supplies explicit nationality and indigency verification', async () => {
  const { context, fields } = await client();
  let payload = context.residentFormPayload();
  assert.equal(payload.nationality, null);
  assert.equal(payload.is_verified_indigent, false);
  fields.set('res-nationality', { value: '  Test nationality  ' });
  fields.set('res-verified-indigent', { checked: true });
  payload = context.residentFormPayload();
  assert.equal(payload.nationality, 'Test nationality');
  assert.equal(payload.is_verified_indigent, true);
});

test('online release displays the voter certification fee without a custom expiration control', async () => {
  const { context, fields } = await client();
  context.window.CERTIFICATE_FEES = FEE_SCHEDULE;
  for (const id of ['print-document-request-id', 'print-certificate-type', 'print-resident-name', 'print-purpose', 'print-amount-paid']) fields.set(id, { value: '2026-12-31' });
  vm.runInContext("CERT_REQUESTS.push({ code: 'TEST', id: 2, type: 'Registered Voter Certification' }); openModal = () => {};", context);
  context.printCert('TEST');
  assert.equal(fields.get('print-amount-paid').value, 'PHP 25.00');
});

/** Mirrors App\CertificateType::staffEnteredDetails(), passed as window.CERTIFICATE_STAFF_DETAILS. */
const STAFF_DETAILS = {
  'Barangay Clearance': [], 'Certificate of Residency': ['residing_since'], 'Certificate of Indigency': [],
  'Registered Voter Certification': [], 'Barangay ID': [], 'First Time Jobseeker': ['years_of_residency'], 'Business Clearance': [],
};

/** A dialog holding the two staff-entered detail groups, as the dashboard renders them. */
function detailContainer() {
  const groups = ['residing_since', 'years_of_residency'].map(detail => {
    const group = { hidden: true, dataset: { certificateDetail: detail } };
    group.input = {
      value: '', required: false, type: detail === 'years_of_residency' ? 'number' : 'month', focused: false,
      closest: () => group, checkValidity() { return this.value !== ''; }, reportValidity() {}, focus() { this.focused = true; },
    };
    group.querySelector = () => group.input;
    return group;
  });
  return {
    groups,
    querySelectorAll(selector) {
      const visible = selector.includes(':not([hidden])') ? groups.filter(group => !group.hidden) : groups;
      return selector.endsWith('input') ? visible.map(group => group.input) : visible;
    },
  };
}

test('releasing a request asks only for the residency detail its certificate prints and sends it with the issue request', async () => {
  const { context, fields } = await client();
  context.window.CERTIFICATE_FEES = FEE_SCHEDULE;
  context.window.CERTIFICATE_STAFF_DETAILS = STAFF_DETAILS;
  const modal = detailContainer();
  const [since, years] = modal.groups;
  fields.set('modal-print-release', modal);
  for (const id of ['print-document-request-id', 'print-certificate-type', 'print-resident-name', 'print-purpose', 'print-amount-paid', 'print-release-submit']) fields.set(id, { value: '', innerHTML: '', dataset: {} });
  vm.runInContext("CERT_REQUESTS.push({ code: 'RES', id: 7, type: 'Certificate of Residency' }); openModal = () => {}; closeModal = () => {}; refreshDocumentRequestsLive = async () => {}; refreshIssuedCertificates = async () => {};", context);
  const requests = [];
  const opened = [];
  context.window.open = () => { opened.push(true); return { location: {}, close() {} }; };
  context.fetch = async (url, options) => { requests.push({ url, body: JSON.parse(options.body) }); return { ok: true, json: async () => ({}) }; };

  context.printCert('RES');
  assert.equal(since.hidden, false);
  assert.equal(since.input.required, true);
  assert.equal(years.hidden, true);

  await context.confirmPrintRelease();
  assert.equal(requests.length, 0, 'nothing is issued while the month is blank');
  assert.equal(opened.length, 0);
  assert.equal(since.input.focused, true);

  since.input.value = '2001-07';
  await context.confirmPrintRelease();
  assert.deepEqual(requests, [{ url: '/staff/document-requests/7/issue', body: { residing_since: '2001-07' } }]);
});

test('previewing a release opens the unsaved certificate with the residency detail typed so far', async () => {
  const { context, fields } = await client();
  context.window.CERTIFICATE_STAFF_DETAILS = STAFF_DETAILS;
  const modal = detailContainer();
  fields.set('modal-print-release', modal);
  fields.set('print-document-request-id', { value: '7' });
  const opened = [];
  context.window.open = (url, target, features) => opened.push([url, target, features]);

  context.previewCertificateRelease();
  modal.groups[0].hidden = false;
  modal.groups[0].input.value = '2001-07';
  context.previewCertificateRelease();

  assert.deepEqual(opened, [
    ['/staff/document-requests/7/certificate-preview', '_blank', 'noopener'],
    ['/staff/document-requests/7/certificate-preview?residing_since=2001-07', '_blank', 'noopener'],
  ]);
});

test('onsite issuance shows the years of residency field only for First Time Jobseeker and sends it as a number', async () => {
  const { context, fields } = await client();
  context.window.CERTIFICATE_FEES = FEE_SCHEDULE;
  context.window.CERTIFICATE_STAFF_DETAILS = STAFF_DETAILS;
  const form = detailContainer();
  const [since, years] = form.groups;
  const type = { value: 'First Time Jobseeker' };
  fields.set('manual-certificate-form', form);
  fields.set('manual-certificate-type', type);
  fields.set('manual-certificate-fee', { value: '' });

  context.updateManualCertificateFee();
  assert.equal(years.hidden, false);
  assert.equal(years.input.required, true);
  assert.equal(since.hidden, true);
  years.input.value = '18';
  assert.deepEqual({ ...context.certificateDetailPayload(form) }, { years_of_residency: 18 });

  type.value = 'Barangay Clearance';
  context.updateManualCertificateFee();
  assert.equal(years.hidden, true);
  assert.equal(years.input.required, false);
  assert.equal(years.input.value, '');
  assert.deepEqual({ ...context.certificateDetailPayload(form) }, {});
});

test('staff fee displays read the server schedule and mark an unconfirmed fee everywhere', async () => {
  const { context, fields } = await client();
  context.window.CERTIFICATE_FEES = FEE_SCHEDULE;
  const typeField = { value: 'Business Clearance' };
  const feeField = { value: '' };
  const table = { innerHTML: '' };
  fields.set('manual-certificate-type', typeField);
  fields.set('manual-certificate-fee', feeField);
  fields.set('cert-types-list', table);

  context.updateManualCertificateFee();
  assert.equal(feeField.value, 'PHP 200.00 (not yet confirmed)');
  typeField.value = 'First Time Job Seeker';
  context.updateManualCertificateFee();
  assert.equal(feeField.value, 'Free');
  context.renderCertTypesList();
  assert.match(table.innerHTML, /Business Clearance[\s\S]*PHP 200\.00 \(not yet confirmed\)/);
  assert.match(table.innerHTML, /Barangay ID[\s\S]*PHP 100\.00/);
  assert.doesNotMatch(table.innerHTML, /200\.00\+/);

  context.window.CERTIFICATE_FEES = undefined;
  assert.equal(context.certificateFeeText('Barangay Clearance'), '—');
});

test('a double-clicked Save Record sends one request and keeps the button busy until it finishes', async () => {
  const { context, fields } = await client();
  fields.set('res-edit-id', { value: '' });
  fields.set('res-photo', { files: [] });
  const button = { disabled: false, setAttribute() {}, removeAttribute() {} };
  fields.set('resident-save-button', button);
  let release;
  const requests = [];
  context.fetch = (url, options) => {
    requests.push({ url, options });
    return new Promise(resolve => { release = () => resolve({ ok: false, status: 422, json: async () => ({ errors: { first_name: ['The first name field is required.'] } }) }); });
  };

  const first = context.saveResident();
  const second = context.saveResident();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(button.disabled, true);
  release();
  await Promise.all([first, second]);

  assert.equal(requests.length, 1);
  assert.equal(button.disabled, false);
  assert.equal(context.messages.at(-1).message, 'The first name field is required.');
});

test('a failed photo upload retries the saved resident instead of creating a duplicate', async () => {
  const { context, fields } = await client();
  fields.set('res-edit-id', { value: '' });
  fields.set('res-photo', { files: [new Blob(['image'], { type: 'image/png' })] });
  const requests = [];
  context.fetch = async (url, options) => {
    requests.push({ url, options });
    if (url.endsWith('/photo')) return { ok: false, json: async () => ({ message: 'Photo upload failed.' }) };
    return { ok: true, json: async () => ({ resident: { id: 12, resident_number: 'TEST-12', status: 'active' } }) };
  };
  await context.saveResident();
  await context.saveResident();
  assert.equal(requests[0].options.method, 'POST');
  assert.equal(requests[2].url, '/staff/residents/12');
  assert.equal(requests[2].options.method, 'PATCH');
  assert.ok(requests[1].options.body instanceof FormData);
  assert.equal(requests[1].options.headers['Content-Type'], undefined);
});

test('audit event symbols use SVG paths for known and unexpected types', async () => {
  const { context } = await client();
  assert.match(context.auditTypeSymbol('security'), /<svg.*<circle/);
  assert.match(context.auditTypeSymbol('unknown'), /<svg.*<path/);
  assert.doesNotMatch(context.auditTypeSymbol('<script>'), /<script>/);
});

test('cancelling resident restoration does not send an administrative mutation', async () => {
  const { context } = await client();
  context.confirmAction = async () => false;
  let requests = 0;
  context.fetch = () => { requests++; };
  await context.restoreResident(1);
  assert.equal(requests, 0);
});

test('a private activation code cannot appear in another resident modal after navigation', async () => {
  const { context, fields } = await client();
  const original = { isConnected: true, innerHTML: '' };
  const replacement = { isConnected: true, innerHTML: '' };
  fields.set('resident-activation-result', original);
  context.confirmAction = async () => true;
  let finish;
  context.fetch = () => new Promise(resolve => { finish = resolve; });
  const button = { disabled: false, closest: () => ({ classList: { contains: () => true } }) };
  const pending = context.issuePortalActivation(1, button);
  await new Promise(resolve => setImmediate(resolve));
  original.isConnected = false;
  fields.set('resident-activation-result', replacement);
  finish({ ok: true, json: async () => ({ activation_code: 'PRIVATE-CODE', message: 'Private', expires_at: 'tomorrow' }) });
  await pending;
  assert.equal(replacement.innerHTML, '');
  assert.equal(original.innerHTML, '');
  assert.equal(button.disabled, false);
});

test('issuing an activation code asks staff to confirm the in-person ID check and sends that confirmation', async () => {
  const { context, fields } = await client();
  const output = { isConnected: true, innerHTML: '' };
  fields.set('resident-activation-result', output);
  let prompt;
  context.confirmAction = async (message, options) => { prompt = { message, options }; return true; };
  let sent;
  context.fetch = async (url, options) => { sent = { url, options }; return { ok: true, json: async () => ({ activation_code: 'ABCD2345', message: 'Code issued', expires_at: 'tomorrow' }) }; };
  const button = { disabled: false, closest: () => ({ classList: { contains: () => true } }) };

  await context.issuePortalActivation(7, button);

  assert.match(prompt.message, /checked this resident's valid ID in person/);
  assert.equal(prompt.options.confirmLabel, 'I checked the ID, issue code');
  assert.equal(sent.url, '/staff/residents/7/portal-activation');
  assert.deepEqual(JSON.parse(sent.options.body), { identity_checked: true });
  assert.match(output.innerHTML, /ABCD2345/);
});

test('the portal account panel shows email confirmation and flags self-service activations for review', async () => {
  const { context, fields } = await client();
  const panel = { isConnected: true, innerHTML: '' };
  fields.set('resident-portal-account', panel);
  context.fetch = async () => ({ ok: true, json: async () => ({ status: 'active', portal_account: { registered: true, is_active: true, email: 'j***@example.test', created_at: '2026-11-01', last_login: null, email_verified: false, activation: 'email_self_service' } }) });

  await context.loadResidentPortalAccount(3);

  assert.match(panel.innerHTML, /Email confirmed: <strong>Not yet/);
  assert.match(panel.innerHTML, /Activated with: <strong>Email self-service \(identity not checked by staff\)/);
  assert.match(panel.innerHTML, /Check the resident&#39;s valid ID in person|Check the resident's valid ID in person/);
});

test('failed account save keeps the form open and releases the submit button', async () => {
  const { context, fields } = await client();
  for (const [id, value] of Object.entries({
    'adduser-edit-id': '', 'adduser-name': 'New Clerk', 'adduser-email': 'clerk@example.test',
    'adduser-role': 'staff', 'adduser-status': 'active', 'adduser-password': 'secure-password',
  })) fields.set(id, { value });
  fields.set('adduser-save-btn', { disabled: false });
  fields.set('adduser-cabinet-access', { checked: false });
  context.fetch = async () => ({ ok: false, json: async () => ({ errors: { email: ['This email is already in use.'] } }) });
  context.closed = false;
  vm.runInContext('closeModal = () => { closed = true; };', context);

  await context.saveNewUser();

  assert.equal(context.closed, false);
  assert.equal(fields.get('adduser-save-btn').disabled, false);
  assert.equal(context.messages[0].message, 'This email is already in use.');
  assert.equal(context.messages[0].type, 'red');
});

test('no employee access profile includes the removed QR module', async () => {
  const { context } = await client();
  const grantsQr = vm.runInContext('Object.values(ACCESS_PERMS).some(permissions => permissions.includes("QR"))', context);

  assert.equal(grantsQr, false);
});

test('opening an obsolete QR screen does not clear the current workspace', async () => {
  const { context, fields } = await client();
  let dashboardCleared = false;
  fields.set('screen-dashboard', { classList: { remove() { dashboardCleared = true; } } });
  context.document.querySelectorAll = selector => selector === '.content' ? [fields.get('screen-dashboard')] : [];

  context.showScreen('qr', null);

  assert.equal(dashboardCleared, false);
});

test('staff navigation does not grant access to account administration', async () => {
  const { context } = await client();
  vm.runInContext('currentUserAccess = "Staff Access";', context);

  context.showScreen('users', null);

  assert.equal(context.messages.at(-1).type, 'red');
});


test('demographics escapes resident names and puroks in the senior register', async () => {
  const { context, fields } = await client();
  fields.set('senior-citizens-list', { innerHTML: '' });
  vm.runInContext(`DEMOGRAPHIC_SUMMARY = { senior_residents: [{
    resident_number: 'RES-001', full_name: '<img src=x onerror=alert(1)>',
    purok: '<script>alert(1)</script>', date_of_birth: '1950-01-01', age: 76, status: 'active'
  }] };`, context);

  context.renderSeniorList();

  assert.match(fields.get('senior-citizens-list').innerHTML, /&lt;img/);
  assert.match(fields.get('senior-citizens-list').innerHTML, /&lt;script/);
  assert.doesNotMatch(fields.get('senior-citizens-list').innerHTML, /<img|<script/);
});

test('the senior register shows ten residents per page with the shared pager', async () => {
  const { context, fields } = await client();
  const list = { innerHTML: '' };
  const pager = { innerHTML: '' };
  fields.set('senior-citizens-list', list);
  fields.set('senior-citizens-pagination', pager);
  vm.runInContext(`DEMOGRAPHIC_SUMMARY = { seniors: 13, senior_residents: Array.from({ length: 13 }, (_, index) => ({ resident_number: 'RES-' + index, full_name: 'Senior ' + index, purok: 'Purok 1', date_of_birth: '1950-01-01', age: 76, status: 'active' })) };`, context);

  context.renderSeniorList();
  assert.equal(list.innerHTML.match(/<tr>/g).length, 11);
  assert.match(pager.innerHTML, /Showing 1–10 of 13 <span>senior citizens<\/span>/);
  assert.match(pager.innerHTML, /onclick="changeSeniorPage\(2\)">Next/);

  context.changeSeniorPage(2);
  assert.equal(list.innerHTML.match(/<tr>/g).length, 4);
  assert.match(list.innerHTML, /Senior 12/);
});

async function logoutClient() {
  const { context, fields } = await client();
  const buttons = [{ disabled: false }, { disabled: false }, { disabled: false, textContent: 'Log out' }];
  const attributes = new Map();
  const dialog = {
    open: false, dataset: { logoutUrl: '/logout', loginUrl: '/login' },
    showModal() { this.open = true; }, close() { this.open = false; },
    querySelectorAll: () => buttons,
    setAttribute: (key, value) => attributes.set(key, value),
    removeAttribute: key => attributes.delete(key),
  };
  const error = { hidden: true, textContent: '' };
  fields.set('logout-dialog', dialog);
  fields.set('logout-confirm', buttons[2]);
  fields.set('logout-error', error);
  context.window.location = { href: '/admin' };
  context.document.cookie = 'XSRF-TOKEN=test-token';
  return { context, dialog, buttons, error };
}

test('opening and cancelling logout never sends a logout request', async () => {
  const { context, dialog } = await logoutClient();
  let requests = 0;
  context.fetch = async () => { requests++; };

  context.doLogout();
  assert.equal(dialog.open, true);
  assert.equal(requests, 0);
  context.cancelLogout();
  await context.confirmLogout();

  assert.equal(dialog.open, false);
  assert.equal(requests, 0);
  assert.equal(context.window.location.href, '/admin');
});

test('confirming logout sends one CSRF-protected request and redirects even when storage is blocked', async () => {
  const { context, dialog, buttons } = await logoutClient();
  const requests = [];
  let finish;
  context.fetch = (url, options) => {
    requests.push({ url, options });
    return new Promise(resolve => finish = resolve);
  };
  context.localStorage.removeItem = () => { throw new Error('Storage blocked'); };
  context.doLogout();

  const pending = context.confirmLogout();
  await context.confirmLogout();
  context.cancelLogout();
  assert.equal(dialog.open, true);
  assert.equal(buttons.every(button => button.disabled), true);
  assert.equal(buttons[2].textContent, 'Logging out...');
  finish({ ok: true });
  await pending;

  assert.equal(requests.length, 1);
  assert.equal(requests[0].url, '/logout');
  assert.equal(requests[0].options.method, 'POST');
  assert.equal(requests[0].options.credentials, 'same-origin');
  assert.equal(requests[0].options.headers['X-XSRF-TOKEN'], 'test-token');
  assert.equal(context.window.location.href, '/login');
});

for (const [status, message] of [
  [500, 'Unable to log out. Please try again.'],
  [419, 'Your session has expired. Refresh this page and try again.'],
]) {
  test(`a ${status} logout response keeps the dialog open with a useful error`, async () => {
    const { context, dialog, buttons, error } = await logoutClient();
    context.fetch = async () => ({ ok: false, status });
    context.doLogout();

    await context.confirmLogout();

    assert.equal(dialog.open, true);
    assert.equal(error.hidden, false);
    assert.equal(error.textContent, message);
    assert.equal(buttons.every(button => !button.disabled), true);
    assert.equal(buttons[2].textContent, 'Log out');
    assert.equal(context.window.location.href, '/admin');
  });
}

test('a network failure lets the user retry logout', async () => {
  const { context, error, buttons } = await logoutClient();
  vm.runInContext('fetch = async () => { throw new TypeError("Failed to fetch"); };', context);
  context.doLogout();

  await context.confirmLogout();

  assert.equal(error.hidden, false);
  assert.match(error.textContent, /Check your connection/);
  assert.equal(buttons.every(button => !button.disabled), true);
  context.fetch = async () => ({ ok: true });
  await context.confirmLogout();
  assert.equal(context.window.location.href, '/login');
});


test('voters csv import prevents duplicate submits and renders validation errors as text', async () => {
  const { context, fields } = await client();
  const button = { disabled: false };
  const output = { textContent: '', hidden: true };
  fields.set('voters-import-button', button);
  fields.set('voters-import-result', output);
  fields.set('voters-import-file', { files: [new Blob(['resident_number'])], value: 'voters.csv' });
  const requests = [];
  let resolve;
  context.fetch = (url, options) => {
    requests.push({ url, options });
    return new Promise(done => { resolve = done; });
  };
  const pending = context.importVotersCsv();
  await context.importVotersCsv();
  assert.equal(requests.length, 1);
  assert.equal(requests[0].url, '/staff/voter-registrations-import');
  assert.equal(requests[0].options.method, 'POST');
  assert.equal(requests[0].options.body instanceof FormData, true);
  resolve({ ok: false, json: async () => ({ errors: { file: ['CSV row 2: <script>invalid</script>'] } }) });
  await pending;
  assert.equal(output.textContent, 'CSV row 2: <script>invalid</script>');
  assert.equal(button.disabled, false);
});

test('successful voters csv import clears the file and refreshes the list', async () => {
  const { context, fields } = await client();
  fields.set('voters-import-button', { disabled: false });
  const output = { textContent: '', hidden: true };
  fields.set('voters-import-result', output);
  const file = { files: [new Blob(['csv'])], value: 'voters.csv' };
  fields.set('voters-import-file', file);
  context.fetch = async () => ({ ok: true, json: async () => ({ imported: 2 }) });
  vm.runInContext('loadVoterRegistry = async page => { window.importRefreshPage = page; };', context);
  await context.importVotersCsv();
  assert.equal(file.value, '');
  assert.equal(context.window.importRefreshPage, 1);
  assert.equal(output.textContent, 'Imported voter records: 2.');
});

test('voters csv import reports existing records that were skipped', async () => {
  const { context, fields } = await client();
  fields.set('voters-import-button', { disabled: false });
  const output = { textContent: '', hidden: true };
  fields.set('voters-import-result', output);
  fields.set('voters-import-file', { files: [new Blob(['csv'])], value: 'voters.csv' });
  context.fetch = async () => ({ ok: true, json: async () => ({ imported: 1, skipped: [{ row: 3, resident_number: 'RES-1' }] }) });
  vm.runInContext('loadVoterRegistry = async () => {};', context);
  await context.importVotersCsv();
  assert.equal(output.textContent, 'Imported voter records: 1. Skipped existing records: 1 (row 3).');
});


test('welcome greeting appears once after login and stays silent on page navigation', async () => {
  const { context } = await client();
  vm.runInContext('currentUserName = "Super Admin";', context);

  context.showPersonnelLoginGreeting();
  assert.equal(context.messages.length, 0);
  context.window.PERSONNEL_LOGIN_GREETING = true;
  context.showPersonnelLoginGreeting();
  context.showPersonnelLoginGreeting();
  assert.equal(context.messages.length, 1);
  assert.equal(context.messages[0].message, 'Welcome back, Super Admin!');
  assert.equal(context.window.PERSONNEL_LOGIN_GREETING, false);
});


test('super admin switches across all Records screens through browser history without reloading or greeting', async () => {
  const { context, fields } = await client();
  const ids = ['records', 'voters', 'certificates', 'request-records', 'incidents'];
  const pushed = [];
  const screens = ids.map(id => {
    const classes = new Set();
    const element = { classes, classList: { add: name => classes.add(name), remove: name => classes.delete(name) },
      setAttribute() {}, focus() {}, querySelector: () => null };
    fields.set('screen-' + id, element);
    return element;
  });
  const navigation = ids.map(id => ({
    current: '', getAttribute: name => name === 'data-screen' ? id : null,
    setAttribute(name, value) { if (name === 'aria-current') this.current = value; },
    classList: { add() {}, remove() {} },
  }));
  context.document.querySelectorAll = selector => selector === '.content' ? screens : selector === '.nav-item' ? navigation : [];
  context.window.AUTHENTICATED_USER.is_super_admin = true;
  context.window.ADMIN_SCREEN_ROUTES = Object.fromEntries(ids.map(id => [id, `http://localhost/staff/${id}`]));
  context.window.location = { href: 'http://localhost/admin', pathname: '/admin' };
  context.window.history = { pushState(state, title, url) {
    pushed.push(url);
    context.window.location.pathname = new URL(url).pathname;
  } };
  vm.runInContext(`currentUserAccess = "Full Access";
    showLoadingBar = toggleNavigation = renderCertKanban = loadRequestRecords = loadRestrictions = loadIncidents = loadVoterRegistry = () => {};`, context);

  ids.forEach((id, index) => {
    context.showScreen(id, navigation[index]);
    assert.equal(navigation[index].current, 'page');
    assert.equal(screens[index].classes.has('active'), true);
    assert.equal(screens.filter(screen => screen.classes.has('active')).length, 1);
  });

  assert.deepEqual(pushed, ids.map(id => `http://localhost/staff/${id}`));
  assert.equal(context.messages.length, 0);
  assert.equal(context.window.location.href, 'http://localhost/admin');
});


test('workspace shortcuts switch screens without Livewire navigation and check permissions first', async () => {
  const { context, fields } = await client();
  const destinations = [];
  const screen = () => ({ classList: { add() {}, remove() {} }, setAttribute() {}, focus() {}, querySelector: () => null });
  fields.set('screen-records', screen());
  fields.set('screen-audit', screen());
  context.window.ADMIN_SCREEN_ROUTES = { records: '/staff/residents', audit: '/admin/audit' };
  context.window.location = { href: 'http://localhost/admin', pathname: '/admin' };
  context.window.history = { pushState(state, title, url) { destinations.push(url); } };
  vm.runInContext('currentUserAccess = "Staff Access";', context);

  context.showScreen('records', null);
  context.showScreen('audit', null);

  assert.deepEqual(destinations, ['/staff/residents']);
  assert.equal(context.messages[0].type, 'red');
});

test('dashboard pie chart is interactive and lists request counts beneath it', async () => {
  const { context, fields } = await client();
  const drawnText = [];
  const drawnStyles = [];
  let primaryColor = '#132238';
  let secondaryColor = '#526273';
  const drawingContext = {
    clearRect() {}, beginPath() {}, arc() {}, closePath() {}, fill() {}, stroke() {},
    fillText(text) { drawnText.push(String(text)); drawnStyles.push(this.fillStyle); },
  };
  const canvas = {
    width: 240, height: 240, title: '',
    getContext: () => drawingContext,
    getBoundingClientRect: () => ({ left: 0, top: 0, width: 240, height: 240 }),
    setAttribute() {},
  };
  const chart = {
    innerHTML: '',
    querySelector: selector => selector === '#dash-certificate-pie' && chart.innerHTML.includes('id="dash-certificate-pie"') ? canvas : null,
  };
  fields.set('dash-chart', chart);
  context.document.querySelectorAll = selector => selector === 'canvas.dashboard-pie' ? [canvas] : [];
  context.getComputedStyle = (_, property) => ({ getPropertyValue: name => name === '--text-primary' ? primaryColor : name === '--text-secondary' ? secondaryColor : '#fff' });

  context.renderDashboardCertificateChart({ 'Barangay Clearance': 4, 'Certificate of Residency': 2 });

  assert.ok(chart.innerHTML.indexOf('dash-certificate-pie') < chart.innerHTML.indexOf('dashboard-pie-breakdown'));
  assert.match(chart.innerHTML, /<span class="dashboard-pie-label">Barangay Clearance<\/span><strong>4 \(67%\)<\/strong>/);
  assert.match(chart.innerHTML, /<span class="dashboard-pie-label">Certificate of Residency<\/span><strong>2 \(33%\)<\/strong>/);
  assert.match(canvas.title, /6 total requests/);

  canvas.onclick({ clientX: 120, clientY: 30 });

  assert.match(canvas.title, /Barangay Clearance: 4 \(67%\)/);
  assert.ok(drawnText.includes('4'));

  primaryColor = '#E8F4FF';
  secondaryColor = '#bac8d6';
  context.refreshDashboardPieTheme();
  assert.ok(drawnStyles.includes('#E8F4FF'));
  assert.ok(drawnStyles.includes('#bac8d6'));

  chart.innerHTML = '';
  context.renderDashboardCertificateChart({ 'Barangay Clearance': 4, 'Certificate of Residency': 2 });

  assert.match(chart.innerHTML, /id="dash-certificate-pie"/);
});

test('dashboard rebuilds the Purok chart after navigation replaces its container', async () => {
  const { context, fields } = await client();
  const createCanvas = () => ({
    width: 240,
    height: 240,
    getContext: () => ({ clearRect() {}, beginPath() {}, arc() {}, closePath() {}, fill() {}, stroke() {}, fillText() {} }),
    getBoundingClientRect: () => ({ left: 0, top: 0, width: 240, height: 240 }),
    setAttribute() {},
  });
  const createContainer = () => {
    const container = { innerHTML: '' };
    container.querySelector = selector => selector === '#dash-purok-pie' && container.innerHTML.includes('id="dash-purok-pie"') ? createCanvas() : null;
    return container;
  };
  fields.set('dash-purok-breakdown', createContainer());
  fields.set('dash-purok-interpretation', { textContent: '' });
  context.getComputedStyle = () => ({ getPropertyValue: () => '#132238' });
  vm.runInContext(`syncPurokSelects = () => {};
    PUROK_DATA.push({ key: 'purok-1', label: 'Purok 1', residentsCount: 4 });
    DEMOGRAPHIC_SUMMARY = { total: 4 };`, context);

  context.renderDashPurokBreakdown();
  assert.match(fields.get('dash-purok-breakdown').innerHTML, /id="dash-purok-pie"/);

  fields.set('dash-purok-breakdown', createContainer());
  context.renderDashPurokBreakdown();

  assert.match(fields.get('dash-purok-breakdown').innerHTML, /id="dash-purok-pie"/);
  assert.match(fields.get('dash-purok-breakdown').innerHTML, /Purok 1/);
});

test('dashboard recent activity paginates records and exposes the record reference on hover', async () => {
  const { context, fields } = await client();
  const activity = { innerHTML: '' };
  const pagination = { hidden: false, innerHTML: '' };
  fields.set('dash-recent-activity', activity);
  fields.set('dash-activity-pagination', pagination);
  vm.runInContext("dashboardActivityItems = Array.from({ length: 8 }, (_, index) => ({ type: 'request', title: `Activity ${index + 1}`, detail: `REQ-${index + 1}`, reference: `REQ-${index + 1}`, time: 'just now' }));", context);

  context.renderDashboardActivity();

  assert.match(activity.innerHTML, /title="REQ-1"/);
  assert.doesNotMatch(activity.innerHTML, /title="REQ-7"/);
  assert.equal(pagination.hidden, false);
  context.changeDashboardActivityPage(1);
  assert.match(activity.innerHTML, /title="REQ-7"/);
});

test('navigation away stops workspace refresh timers and ignores workspace initialization on IoT pages', async () => {
  const { context, documentEvents } = await client();
  const cleared = [];
  context.clearInterval = timer => cleared.push(timer);
  vm.runInContext('clockTimer = 21; auditRefreshTimer = 22; documentRequestLiveTimer = 23;', context);

  documentEvents.get('livewire:navigating').forEach(handler => handler());
  await context.initializePersonnelWorkspace();
  context.toggleNavigation(false);

  assert.deepEqual(cleared, [21, 22, 23]);
  assert.equal(vm.runInContext('clockTimer', context), null);
  assert.equal(context.messages.length, 0);
});


test('returning from an IoT page initializes the new workspace and its data again', async () => {
  const { context, fields, documentEvents } = await client();
  context.document.body = { classList: { contains: () => true } };
  context.initializations = [];
  vm.runInContext(`loadPuroks = async () => {};
    launchApp = (name, role) => initializations.push({ name, role });
    startDocumentRequestLiveRefresh = refreshIssuedCertificates = populateManualResidentDropdown = loadVoterRegistry = () => {};
    loadResidents = async () => {};`, context);

  fields.set('app', {});
  await context.initializePersonnelWorkspace();
  documentEvents.get('livewire:navigating').forEach(handler => handler());
  fields.delete('app');
  await context.initializePersonnelWorkspace();
  fields.set('app', {});
  await context.initializePersonnelWorkspace();

  assert.equal(context.initializations.length, 2);
  assert.equal(context.initializations[1].name, 'Admin');
  assert.equal(context.initializations[1].role, 'admin');
});


test('assigned page access controls navigation independently of legacy role profiles', async () => {
  const { context } = await client();
  context.window.PERSONNEL_SCREEN_ACCESS = { dashboard: false, demographics: true, records: false };
  context.window.PERSONNEL_PERMISSIONS = ['demographics.view'];
  const links = ['dashboard', 'demographics', 'records'].map(screen => ({ dataset: { screen }, style: {} }));
  context.document.querySelectorAll = selector => selector === '.nav-item[data-screen]' ? links : [];
  context.applyAccessControl('Full Access');
  assert.deepEqual(links.map(link => link.style.display), ['none', '', 'none']);
  context.showScreen('records', null);
  assert.equal(context.messages.at(-1).type, 'red');
  assert.equal(context.hasStaffPermission('records.update'), false);
});

test('staff presets remain editable and action selection maintains page dependencies', async () => {
  const { context, fields } = await client();
  const inputs = ['records.view', 'records.update', 'incidents.submit', 'vawc.view'].map(permission => ({ dataset: { staffPermission: permission }, checked: false }));
  fields.set('staff-access-preset', { value: 'Secretary / Assistant' });
  context.window.STAFF_PERMISSION_PRESETS = { Tanod: ['incidents.submit'] };
  context.document.querySelectorAll = selector => selector === '[data-staff-permission]' ? inputs : selector === '[data-staff-permission^="records."]' ? inputs.slice(0, 2) : [];
  context.document.querySelector = selector => selector === '[data-staff-permission="records.view"]' ? inputs[0] : null;
  context.applyStaffPreset('Tanod');
  assert.deepEqual(inputs.map(input => input.checked), [false, false, true, false]);
  inputs[1].checked = true;
  context.changeStaffAssignment(inputs[1]);
  assert.equal(inputs[0].checked, true);
  assert.equal(fields.get('staff-access-preset').value, '');
  inputs[0].checked = false;
  context.changeStaffAssignment(inputs[0]);
  assert.equal(inputs[1].checked, false);
  assert.equal(inputs[2].checked, true);
});

test('staff assignments remain visible when editing a super admin account', async () => {
  const { context, fields } = await client();
  fields.set('adduser-edit-id', { value: '7' });
  fields.set('adduser-role', { value: 'admin' });
  const section = { hidden: true, querySelectorAll: () => [] };
  fields.set('staff-assignment-fields', section);
  vm.runInContext("USERS.push({ id: 7, is_super_admin: true });", context);

  context.toggleStaffAssignmentFields();

  assert.equal(section.hidden, false);
});


for (const photo of [
  { name: 'report.pdf', type: 'application/pdf', size: 100 },
  { name: 'photo.jpg', type: 'text/plain', size: 100 },
  { name: 'photo.svg', type: 'image/svg+xml', size: 100 },
  { name: 'photo.png', type: 'image/png', size: 10 * 1024 * 1024 + 1 },
]) {
  test(`invalid resident photo ${photo.name} is cleared before any record save`, async () => {
    const { context, fields } = await client();
    const input = { value: 'selected-file', files: [photo] };
    fields.set('res-photo', input);
    let requests = 0;
    context.fetch = () => { requests++; };
    await context.saveResident();
    assert.equal(requests, 0);
    assert.equal(input.value, '');
    assert.equal(context.messages[0].type, 'red');
  });
}

test('resident photo picker allows supported image formats and no selection', async () => {
  const { context } = await client();
  for (const [name, type] of [['face.JPG', 'image/jpeg'], ['face.png', 'image/png'], ['face.gif', 'image/gif'], ['face.bmp', 'image/bmp'], ['face.webp', 'image/webp'], ['face.avif', 'image/avif'], ['face.heic', 'image/heic'], ['face.heif', 'image/heif']]) {
    assert.equal(context.validateResidentPhoto({ files: [{ name, type, size: 5 * 1024 * 1024 }] }), true);
  }
  assert.equal(context.validateResidentPhoto({ files: [] }), true);
});

test('opening and closing user management remasks passwords through the shared visibility control', async () => {
  const { context, fields } = await client();
  const masked = [];
  const modal = { classList: { add() {}, remove() {} }, setAttribute() {}, querySelector: () => null };
  fields.set('modal-adduser', modal);
  context.window.PasswordVisibility = { hide: root => masked.push(root) };
  context.openModal('modal-adduser');
  context.closeModal('modal-adduser');
  assert.deepEqual(masked, [modal, modal]);
});

test('user management offers delete only for other non-super-admin accounts', async () => {
  const { context, fields } = await client();
  fields.set('users-list-container', { innerHTML: '', querySelectorAll: () => [] });
  fields.set('usr-active', { textContent: '' });
  fields.set('usr-suspended', { textContent: '' });
  vm.runInContext(`USERS.splice(0, USERS.length,
    { id: 1, name: 'Admin', email: 'admin@example.test', role: 'admin', is_active: true },
    { id: 2, name: 'Staff', email: 'staff@example.test', role: 'staff', is_active: true, is_super_admin: false },
    { id: 3, name: 'Other Super Admin', email: 'super@example.test', role: 'admin', is_active: true, is_super_admin: true }
  );`, context);

  context.renderUsers();

  const html = fields.get('users-list-container').innerHTML;
  assert.match(html, /onclick="event\.stopPropagation\(\);deleteUser\(2\)"/);
  assert.doesNotMatch(html, /deleteUser\(1\)|deleteUser\(3\)/);
});

test('user cards open details from a name button instead of nesting buttons in a button card', async () => {
  const { context, fields } = await client();
  fields.set('users-list-container', { innerHTML: '' });
  fields.set('user-search', { value: '' });
  fields.set('user-role-filter', { value: 'super_admin' });
  fields.set('user-status-filter', { value: 'all' });
  vm.runInContext(`USERS.splice(0, USERS.length,
    { id: 1, name: 'Captain', email: 'captain@example.test', role: 'captain', is_active: true },
    { id: 2, name: 'Owner', email: 'owner@example.test', role: 'admin', is_active: true, is_super_admin: true },
    { id: 3, name: 'Clerk', email: 'clerk@example.test', role: 'staff', is_active: false }
  );`, context);

  context.renderUsers();
  let html = fields.get('users-list-container').innerHTML;
  assert.doesNotMatch(html, /role="button"/);
  assert.match(html, /<button type="button" class="user-card-open" onclick="openUserDetails\(1\)"/);
  assert.match(html, /Barangay Captain/);
  assert.match(html, /aria-label="Suspend Clerk"|aria-label="Activate Clerk"/);

  context.filterUsers();
  html = fields.get('users-list-container').innerHTML;
  assert.match(html, /openUserDetails\(2\)/);
  assert.doesNotMatch(html, /openUserDetails\([13]\)/);
  assert.match(html, />Super Admin</);
});

test('a confirmation opened from a detail dialog is layered above it, which becomes inert until it closes', async () => {
  const { context, fields } = await client();
  const overlay = name => {
    const classes = new Set();
    return {
      name, style: {}, inert: false, focused: 0,
      classList: { add: value => classes.add(value), remove: value => classes.delete(value), contains: value => classes.has(value) },
      setAttribute() {}, querySelector: () => null, querySelectorAll: () => [],
    };
  };
  const details = overlay('details');
  const confirmation = overlay('confirmation');
  const app = { inert: false };
  const pageButton = { isConnected: true, focus() { this.focused = true; } };
  const archiveButton = { isConnected: true, focus() { this.focused = true; } };
  fields.set('modal-view-resident', details);
  fields.set('modal-confirm-action', confirmation);
  fields.set('app', app);
  const bodyClasses = new Set();
  context.document.body = { classList: { toggle: (name, on) => (on ? bodyClasses.add(name) : bodyClasses.delete(name)) } };

  context.document.activeElement = pageButton;
  context.openModal('modal-view-resident');
  context.document.activeElement = archiveButton;
  context.openModal('modal-confirm-action');

  assert.ok(Number(confirmation.style.zIndex) > Number(details.style.zIndex));
  assert.equal(details.inert, true);
  assert.equal(confirmation.inert, false);
  assert.equal(app.inert, true);
  assert.equal(bodyClasses.has('modal-open'), true);
  assert.equal(context.topOpenModal(), confirmation);

  context.closeModal('modal-confirm-action');
  assert.equal(details.inert, false);
  assert.equal(archiveButton.focused, true);
  assert.equal(context.topOpenModal(), details);
  assert.equal(app.inert, true);

  context.closeModal('modal-view-resident');
  assert.equal(app.inert, false);
  assert.equal(bodyClasses.has('modal-open'), false);
  assert.equal(pageButton.focused, true);
  assert.equal(details.style.zIndex, '');
});

test('a confirmation whose opener was disabled before opening still returns focus to that opener', async () => {
  const { context, fields, documentEvents } = await client();
  const overlay = { style: {}, inert: false, classList: { add() {}, remove() {}, contains: () => true }, setAttribute() {}, querySelector: () => null, querySelectorAll: () => [] };
  fields.set('modal-confirm-action', overlay);
  const suspendButton = { isConnected: true, disabled: false, focused: false, focus() { this.focused = true; } };
  context.document.body = { classList: { toggle() {} } };
  documentEvents.get('focusin').forEach(handler => handler({ target: suspendButton }));
  context.document.activeElement = context.document.body;

  context.openModal('modal-confirm-action');
  context.closeModal('modal-confirm-action');

  assert.equal(suspendButton.focused, true);
});

test('list load failures explain network errors in plain words, keep server messages, and offer a retry', async () => {
  const { context, fields } = await client();
  assert.equal(context.requestErrorMessage(new TypeError('Failed to fetch')), 'Could not reach the server. Check your connection, then try again.');
  assert.equal(context.requestErrorMessage(new Error('You do not have permission to view incident reports.')), 'You do not have permission to view incident reports.');

  const tbody = { innerHTML: '' };
  fields.set('incidents-tbody', tbody);
  fields.set('inc-stat-pending', { textContent: '0', setAttribute() {} });
  context.fetch = async () => { throw new TypeError('Failed to fetch'); };
  await context.loadIncidents(1);

  assert.match(tbody.innerHTML, /Could not reach the server/);
  assert.match(tbody.innerHTML, /onclick="loadIncidents\(incidentCurrentPage\)">Try again<\/button>/);
  assert.doesNotMatch(tbody.innerHTML, /Failed to fetch/);
  assert.equal(fields.get('inc-stat-pending').textContent, '—');
});

test('purok panels say loading, failed with a retry, or genuinely empty instead of "no data"', async () => {
  const { context } = await client();
  vm.runInContext("PUROK_DATA.splice(0); purokLoadState = 'loading';", context);
  assert.match(context.purokPanelState(), /Loading purok data/);
  vm.runInContext("purokLoadState = 'failed'; purokLoadError = new TypeError('Failed to fetch');", context);
  assert.match(context.purokPanelState(), /Could not reach the server.*onclick="loadPuroks\(\)">Try again/s);
  vm.runInContext("purokLoadState = 'loaded'; purokLoadError = null;", context);
  assert.match(context.purokPanelState(), /No puroks have been added yet/);
  vm.runInContext("PUROK_DATA.push({ key: 'Purok 1', label: 'Purok 1' });", context);
  assert.equal(context.purokPanelState(), null);
});

test('incident numbers break once after the year and keep the identifier itself unbroken', async () => {
  const { context } = await client();
  assert.equal(context.incidentReferenceMarkup('INC-2026-01M6GZXFF42W6PJD3YRZ1CN2E1'), 'INC-2026-<wbr><span class="incident-ref-id">01M6GZXFF42W6PJD3YRZ1CN2E1</span>');
  assert.equal(context.incidentReferenceMarkup('<b>legacy</b>'), '&lt;b&gt;legacy&lt;/b&gt;');
});

test('dialogs open on their first usable field and keep a single dialog role', async () => {
  const { context, fields } = await client();
  const focused = [];
  const close = { focus() { focused.push('close'); } };
  const hidden = { disabled: false, tabIndex: -1, getClientRects: () => [], focus() { focused.push('hidden'); } };
  const reason = { disabled: false, tabIndex: 0, getClientRects: () => [{}], focus() { focused.push('reason'); } };
  const formAttributes = {};
  fields.set('modal-reject-request', {
    classList: { add() {} }, setAttribute: (name, value) => { formAttributes[name] = value; },
    querySelector: selector => (selector.includes('role=') ? null : selector === '.modal-title' ? { id: 'reject-title' } : close),
    querySelectorAll: () => [hidden, reason],
  });
  const confirmAttributes = {};
  fields.set('modal-confirm-action', {
    classList: { add() {} }, setAttribute: (name, value) => { confirmAttributes[name] = value; },
    querySelector: selector => (selector.includes('role=') ? {} : close), querySelectorAll: () => [],
  });

  context.openModal('modal-reject-request');
  context.openModal('modal-confirm-action');

  assert.deepEqual(focused, ['reason', 'close']);
  assert.equal(formAttributes.role, 'dialog');
  assert.equal(confirmAttributes.role, undefined);
});

test('destructive confirmations start on Cancel so Enter does not confirm them', async () => {
  const { context, fields } = await client();
  const focused = [];
  fields.set('modal-confirm-action', { classList: { add() {}, remove() {} }, setAttribute() {}, querySelector: () => null });
  fields.set('confirm-action-button', { textContent: '', className: '', onclick: null, focus() { focused.push('confirm'); } });
  fields.set('confirm-action-cancel', { focus() { focused.push('cancel'); } });
  fields.set('confirm-action-title', { textContent: '' });
  fields.set('confirm-action-message', { textContent: '' });

  context.confirmAction('Suspend Clerk?', { danger: true });
  context.confirmAction('Reactivate Clerk?');

  assert.deepEqual(focused, ['cancel', 'confirm']);
});

test('notification read state survives a refresh without storing request or incident details', async () => {
  const { context, fields } = await client();
  const stored = new Map();
  context.window.localStorage = { getItem: key => stored.get(key) ?? null, setItem: (key, value) => stored.set(key, value) };
  fields.set('notif-list', { innerHTML: '', appendChild() {} });
  context.document.createElement = () => ({});
  fields.set('notif-count', { textContent: '', style: {} });
  const activity = [
    { type: 'incident', record_id: 7, title: 'Incident filed', detail: 'INC-2026-0007 — Maria Santos', time: 'now', occurred_at: '2026-11-03T08:00:00Z' },
    { type: 'request', record_id: 9, title: 'Document request pending', detail: 'REQ-2026-ABC — Barangay Clearance', time: 'now', occurred_at: '2026-11-03T07:00:00Z' },
  ];
  context.hasStaffPermission = () => true;
  context.renderDashboardActivity = () => {};
  context.renderDashboardCertificateChart = () => {};
  context.fetch = async () => ({ ok: true, json: async () => ({ summary: {}, recent_activity: activity }) });
  fields.set('dash-sub-pending', { textContent: '' });
  fields.set('dash-sub-incidents', { textContent: '' });

  const refreshed = await context.refreshDashboardStats();
  assert.deepEqual([refreshed, context.messages], [true, []]);
  assert.equal(fields.get('notif-count').textContent, 2);
  context.markAllNotifsRead();
  assert.equal(fields.get('notif-count').textContent, 0);
  const saved = [...stored.values()].join(' ');
  assert.doesNotMatch(saved, /Maria|INC-2026|REQ-2026|Clearance/);

  vm.runInContext('NOTIFICATIONS.splice(0)', context);
  await context.refreshDashboardStats();
  assert.equal(fields.get('notif-count').textContent, 0);
});

test('a dashboard summary that returns after the staff member left the workspace changes nothing', async () => {
  const { context, fields } = await client();
  let activityRendered = false;
  context.hasStaffPermission = () => true;
  context.renderDashboardActivity = () => { activityRendered = true; };
  context.fetch = async () => ({ ok: true, json: async () => ({ summary: { pending_requests: 2 }, recent_activity: [] }) });
  fields.delete('dash-sub-pending');
  fields.delete('dash-sub-incidents');

  assert.equal(await context.refreshDashboardStats(), false);
  assert.deepEqual([activityRendered, context.messages], [false, []]);
});

test('deleting a user requires confirmation and sends the delete request', async () => {
  const { context, fields } = await client();
  fields.set('users-list-container', { innerHTML: '', querySelectorAll: () => [] });
  fields.set('usr-active', { textContent: '' });
  fields.set('usr-suspended', { textContent: '' });
  vm.runInContext(`USERS.splice(0, USERS.length, {
    id: 2, name: 'Staff User', email: 'staff@example.test', role: 'staff', is_active: true,
    is_super_admin: false, cabinet_access: { rpi_employee_id: 'EMP002' }
  });`, context);
  const prompts = [];
  const requests = [];
  context.confirmAction = async message => { prompts.push(message); return true; };
  context.fetch = async (url, options) => {
    requests.push({ url, options });
    return { ok: true, json: async () => url === '/admin/users/2'
      ? { message: 'Account removed. RPi revocation queued.' }
      : { users: [] } };
  };

  await context.deleteUser(2);

  assert.match(prompts[0], /remove.*Raspberry Pi.*confirms/i);
  assert.equal(requests[0].url, '/admin/users/2');
  assert.equal(requests[0].options.method, 'DELETE');
  assert.equal(context.messages[0].message, 'Account removed. RPi revocation queued.');
});

test('special group cards open filtered resident records and the All button clears the group', async () => {
  const { context, fields } = await client();
  const container = { innerHTML: '' };
  fields.set('special-groups-container', container);
  vm.runInContext("DEMOGRAPHIC_SUMMARY = { total: 10, seniors: 2, special_groups: { PWD: 3, 'Solo Parent': 5 } };", context);
  context.window.PERSONNEL_PERMISSIONS = ['demographics.view'];
  context.renderSpecialGroups();
  assert.doesNotMatch(container.innerHTML, /openSpecialGroupRecords|<button/);
  context.window.PERSONNEL_PERMISSIONS = ['demographics.view', 'records.view'];
  context.renderSpecialGroups();
  assert.match(container.innerHTML, /<button class="special-group-row" type="button" data-group="PWD" onclick="openSpecialGroupRecords\(this.dataset.group\)"/);
  assert.ok(container.innerHTML.indexOf('data-group="Solo Parent"') < container.innerHTML.indexOf('data-group="PWD"'), 'largest groups come first');

  const groupSelect = { value: '' };
  fields.set('resident-group-filter', groupSelect);
  const statusPills = ['', 'Active', 'Senior'].map(value => {
    const item = { value, dataset: { residentStatus: value.toLowerCase() }, classes: new Set(), getAttribute: () => `filterResidentStatus('${value}',this)` };
    item.classList = { toggle: (name, on) => (on ? item.classes.add(name) : item.classes.delete(name)), remove: name => item.classes.delete(name), add: name => item.classes.add(name) };
    return item;
  });
  context.document.querySelectorAll = selector => selector.includes('status-pill') ? statusPills : [];
  const requested = [];
  context.fetch = async url => { requested.push(url); return { ok: false, json: async () => ({ message: 'stop' }) }; };
  vm.runInContext('showScreen = id => { openedScreenId = id; };', context);

  await context.openSpecialGroupRecords('PWD');

  assert.equal(context.openedScreenId, 'records');
  let params = new URL(requested[0], 'http://localhost').searchParams;
  assert.equal(params.get('special_group'), 'PWD');
  assert.equal(params.get('status'), 'active');
  assert.equal(groupSelect.value, 'PWD');
  assert.equal(statusPills[1].classes.has('active'), true);
  assert.equal(statusPills[0].classes.has('active'), false);

  context.filterResidentStatus('', statusPills[0]);
  params = new URL(requested[1], 'http://localhost').searchParams;
  assert.equal(params.has('special_group'), false);
  assert.equal(statusPills[0].classes.has('active'), true);
  assert.equal(groupSelect.value, '');

  await context.openSpecialGroupRecords('Senior Citizen');
  params = new URL(requested[2], 'http://localhost').searchParams;
  assert.equal(params.get('status'), 'senior');
  assert.equal(params.has('special_group'), false);
  assert.equal(statusPills[2].classes.has('active'), true);
});

test('add purok shows a missing name on its own field instead of a notification', async () => {
  const { context, fields } = await client();
  fields.set('purok-label', { value: ' ' });
  fields.set('purok-color', { value: '#22C55E' });
  const shown = [];
  context.window.FormFeedback = { showErrors: errors => shown.push(errors) };
  let fetched = false;
  context.fetch = async () => { fetched = true; return { ok: true, json: async () => ({}) }; };

  await context.savePurok();

  assert.equal(fetched, false);
  assert.equal(context.messages.length, 0);
  assert.deepEqual(JSON.parse(JSON.stringify(shown)), [{ 'purok-label': ['Enter a purok display name.'] }]);
});

test('add purok sends only the name and colour the server stores', async () => {
  const { context, fields } = await client();
  fields.set('purok-label', { value: '  Purok 7 - Dahlia ' });
  fields.set('purok-color', { value: '#3366AA' });
  let body = null;
  context.fetch = async (url, options = {}) => {
    if (options.method === 'POST') body = JSON.parse(options.body);
    return { ok: true, json: async () => ({ message: 'Purok added successfully.', data: [], demographics: {} }) };
  };
  vm.runInContext('loadPuroks = async () => true;', context);

  await context.savePurok();

  assert.deepEqual(body, { name: 'Purok 7 - Dahlia', color: '#3366AA' });
});

test('the shared pager summarises the range and calls the named loader for the neighbouring pages', async () => {
  const { context, fields } = await client();
  const attributes = {};
  const pager = { innerHTML: 'stale', setAttribute: (name, value) => { attributes[name] = value; } };
  fields.set('resident-pagination', pager);

  context.renderPager('resident-pagination', { page: 2, lastPage: 3, total: 31, loader: 'loadResidents' });
  assert.match(pager.innerHTML, /Showing 16–30 of 31 <span>records<\/span>/);
  assert.match(pager.innerHTML, /onclick="loadResidents\(1\)">Previous/);
  assert.match(pager.innerHTML, /Page 2 of 3/);
  assert.match(pager.innerHTML, /onclick="loadResidents\(3\)">Next/);
  assert.equal(attributes.role, 'navigation');

  context.renderPager('resident-pagination', { page: 1, lastPage: 1, total: 1, noun: ['report', 'reports'], loader: 'loadIncidents' });
  assert.match(pager.innerHTML, /Showing 1–1 of 1 <span>report<\/span>/);
  assert.doesNotMatch(pager.innerHTML, /Previous|Next/);

  context.renderPager('resident-pagination', { page: 1, lastPage: 1, total: 0, loader: 'loadResidents' });
  assert.equal(pager.innerHTML, '');
});

test('Escape closes the dialog on top and focus returns to the control that opened each dialog', async () => {
  const { context, fields, documentEvents } = await client();
  const dialog = id => {
    const classes = new Set();
    return { id, classList: { add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name) },
      setAttribute() {}, querySelector: () => null, querySelectorAll: () => [] };
  };
  const control = name => ({ name, focus() { context.document.activeElement = this; } });
  fields.set('modal-resident', dialog('modal-resident'));
  fields.set('modal-confirm-action', dialog('modal-confirm-action'));
  const pageButton = control('page button');
  const saveButton = control('save button');

  context.document.activeElement = pageButton;
  context.openModal('modal-resident');
  context.document.activeElement = saveButton;
  context.openModal('modal-confirm-action');
  const keydown = documentEvents.get('keydown').at(-1);
  keydown({ key: 'Escape' });

  assert.equal(fields.get('modal-confirm-action').classList.contains('show'), false);
  assert.equal(fields.get('modal-resident').classList.contains('show'), true);
  assert.equal(context.document.activeElement, saveButton);

  keydown({ key: 'Escape' });
  assert.equal(fields.get('modal-resident').classList.contains('show'), false);
  assert.equal(context.document.activeElement, pageButton);
});

test('the special group dropdown combines with the selected status', async () => {
  const { context, fields } = await client();
  fields.set('resident-group-filter', { value: '' });
  const requested = [];
  context.fetch = async url => { requested.push(url); return { ok: false, json: async () => ({ message: 'stop' }) }; };

  context.filterResidentStatus('Inactive');
  context.filterResidentSpecialGroup('Solo Parent');

  const params = new URL(requested[1], 'http://localhost').searchParams;
  assert.equal(params.get('status'), 'inactive');
  assert.equal(params.get('special_group'), 'Solo Parent');
  assert.equal(fields.get('resident-group-filter').value, 'Solo Parent');
});

test('resident contact numbers accept only 09 plus 9 digits or +639 plus 9 digits', async () => {
  const { context, fields } = await client();
  const input = { value: '0917-123 45678999' };
  context.formatResidentContact(input);
  assert.equal(input.value, '09171234567');
  input.value = '+63 917 123 4567 89';
  context.formatResidentContact(input);
  assert.equal(input.value, '+639171234567');

  assert.equal(context.residentContactError('09171234567'), '');
  assert.equal(context.residentContactError('+639171234567'), '');
  assert.equal(context.residentContactError(''), '');
  assert.match(context.residentContactError('0817123456'), /11 digits starting with 09/);

  fields.set('res-photo', { files: [], value: '' });
  fields.set('res-contact', { value: '12345' });
  const shown = [];
  context.window.FormFeedback = { showErrors: errors => shown.push(errors) };
  let fetched = false;
  context.fetch = async () => { fetched = true; return { ok: true, json: async () => ({}) }; };
  await context.saveResident();
  assert.equal(fetched, false);
  assert.deepEqual(Object.keys(shown[0]), ['res-contact']);
});

test('clicking a resident row opens its details but its action buttons keep their own behavior', async () => {
  const { context, fields } = await client();
  const rows = [];
  fields.set('records-tbody', { innerHTML: '', appendChild: row => rows.push(row) });
  context.document.createElement = () => {
    const listeners = {};
    const classes = new Set();
    return { innerHTML: '', listeners, classes, attributes: {}, classList: { add: name => classes.add(name) }, setAttribute(name, value) { this.attributes[name] = value; }, addEventListener: (type, handler) => { listeners[type] = handler; }, querySelectorAll: () => [] };
  };
  vm.runInContext(`RESIDENTS.splice(0, RESIDENTS.length,
    { id: 'ANB-1', databaseId: 1, name: 'Maria Santos', dob: '1990-01-01', purok: 'Purok 1', gender: 'Female', civil: 'Single', status: 'Active', archived: false },
    { id: 'ANB-2', databaseId: 2, name: 'Old Record', dob: '1990-01-01', purok: 'Purok 1', gender: 'Male', civil: 'Single', status: 'Active', archived: true });
    openedResidents = []; openViewResident = id => openedResidents.push(id);`, context);

  context.renderResidentsTable();

  assert.equal(rows[0].classes.has('resident-row-clickable'), true);
  assert.equal(rows[0].tabIndex, 0);
  rows[0].listeners.click({ target: { closest: () => null } });
  rows[0].listeners.click({ target: { closest: selector => selector.includes('button') ? {} : null } });
  rows[0].listeners.keydown({ target: rows[0], key: 'Enter', preventDefault() {} });
  assert.deepEqual([...context.openedResidents], ['ANB-1', 'ANB-1']);
  assert.equal(rows[1].listeners.click, undefined);
});

test('a resident save refused as stale keeps the typed values, loads the latest record and can re-apply them', async () => {
  const { context, fields } = await client();
  const ids = ['res-lastname', 'res-name', 'res-middlename', 'res-suffix', 'res-dob', 'res-gender', 'res-civil', 'res-nationality', 'res-purok', 'res-address', 'res-contact', 'res-type', 'res-status', 'res-edit-id', 'res-household-id', 'res-household-relationship'];
  ids.forEach(id => fields.set(id, { value: '' }));
  ['res-good-standing', 'res-verified-indigent', 'res-household-head', 'res-create-household'].forEach(id => fields.set(id, { checked: false }));
  fields.set('res-photo', { value: '', files: [] });
  const notice = { hidden: true, innerHTML: '', scrollIntoView() {} };
  fields.set('res-conflict', notice);
  context.document.querySelector = selector => (selector === '#modal-resident-title span' ? { textContent: '' } : null);
  vm.runInContext(`syncPurokSelects = () => {}; ensurePuroksLoaded = async () => {}; setResidentPhotoPreview = () => {};
    resetResidentHouseholdFields = () => {}; openModal = () => {}; checkedGroups = [];
    setCheckedSpecialGroups = groups => { checkedGroups = [...groups]; }; getCheckedSpecialGroups = () => checkedGroups;`, context);
  const record = extra => ({ id: 9, resident_number: 'ANB-9', first_name: 'Maria', last_name: 'Santos', date_of_birth: '1990-05-14', gender: 'Female', civil_status: 'Single', purok: 'Purok 1', address: 'Old address', contact_number: '09171111111', residency_type: 'Homeowner', status: 'active', is_in_good_standing: true, special_groups: [], ...extra });
  context.loaded = record({ updated_at: '2026-11-04T01:00:00.000000Z' });
  context.latest = record({ address: 'Their address', residency_type: 'Renter', updated_at: '2026-11-04T01:05:00.000000Z' });
  vm.runInContext('RESIDENTS.splice(0, RESIDENTS.length, residentFromApi(loaded));', context);

  context.openEditResident('ANB-9');
  fields.get('res-address').value = 'My address';
  fields.get('res-contact').value = '09172222222';
  let sent;
  context.fetch = async (url, options) => { sent = JSON.parse(options.body); return { ok: false, status: 409, json: async () => ({ message: 'Someone else saved changes to this resident after you opened it. Your changes were not saved.', resident: context.latest }) }; };
  await context.saveResidentRecord();

  assert.equal(sent.expected_updated_at, '2026-11-04T01:00:00.000000Z');
  assert.equal(notice.hidden, false);
  assert.match(notice.innerHTML, /Load the latest record/);
  assert.equal(fields.get('res-address').value, 'My address');

  context.loadLatestResidentVersion();
  assert.equal(fields.get('res-address').value, 'Their address');
  assert.equal(fields.get('res-contact').value, '09171111111');
  assert.equal(fields.get('res-type').value, 'Renter');
  assert.match(notice.innerHTML, /Changed in the latest record:<\/span> <strong>res-address, res-type<\/strong>/);
  assert.match(notice.innerHTML, /res-address<\/span> <strong>My address<\/strong>/);
  assert.match(notice.innerHTML, /res-contact<\/span> <strong>09172222222<\/strong>/);
  assert.doesNotMatch(notice.innerHTML, /<li><span>res-type/);
  assert.equal(context.residentFormPayload().expected_updated_at, '2026-11-04T01:05:00.000000Z');

  context.reapplyResidentDraft();
  assert.equal(fields.get('res-address').value, 'My address');
  assert.equal(fields.get('res-contact').value, '09172222222');
  assert.equal(fields.get('res-type').value, 'Renter', 'a field only the other save changed keeps the latest value');
  assert.equal(notice.hidden, true);
  assert.equal(context.residentFormPayload().expected_updated_at, '2026-11-04T01:05:00.000000Z');
});

test('incident status chips show counts, hide empty legacy statuses and apply the matching filters', async () => {
  const { context, fields } = await client();
  const strip = { innerHTML: '' };
  fields.set('incident-status-strip', strip);
  ['incident-search', 'incident-status-filter', 'incident-severity-filter', 'incident-date-from', 'incident-date-to', 'incident-assignee-filter'].forEach(id => fields.set(id, { value: '' }));
  fields.set('incidents-tbody', { innerHTML: '' });
  const requested = [];
  context.fetch = async url => { requested.push(String(url)); return { ok: true, json: async () => ({ data: [], total: 0, summary: {} }) }; };
  vm.runInContext('renderIncidents = () => {}; revealKpiResults = () => {};', context);
  const byStatus = { open: 3, under_review: 1, referred: 0, resolved: 2, closed: 4, pending: 1, under_investigation: 0, dismissed: 0 };

  context.renderIncidentStatusStrip({ by_status: byStatus });
  assert.match(strip.innerHTML, /Open<\/span><span class="badge badge-blue">3</);
  assert.match(strip.innerHTML, /Referred<\/span><span class="badge badge-purple">0</);
  assert.match(strip.innerHTML, /Pending \(legacy\)/);
  assert.doesNotMatch(strip.innerHTML, /Dismissed|With a protection order/);

  context.renderIncidentStatusStrip({ by_status: byStatus, protection_orders: 2 });
  assert.match(strip.innerHTML, /With a protection order<\/span><span class="badge badge-purple">2</);

  context.toggleProtectionOrderIncidents();
  await new Promise(resolve => setImmediate(resolve));
  assert.match(requested.at(-1), /protection_order=1/);
  context.showIncidentKpi('closed', '');
  await new Promise(resolve => setImmediate(resolve));
  assert.match(requested.at(-1), /status=closed/);
  assert.doesNotMatch(requested.at(-1), /protection_order/);
});

test('resident rows mark registered voters only, and a missing voter record adds no label', async () => {
  const { context, fields } = await client();
  const rows = [];
  fields.set('records-tbody', { innerHTML: '', appendChild: row => rows.push(row) });
  context.document.createElement = () => ({ innerHTML: '', classList: { add() {} }, setAttribute() {}, addEventListener() {}, querySelectorAll: () => [] });
  context.fetched = [
    { id: 1, resident_number: 'ANB-1', full_name: 'Registered Person', date_of_birth: '1990-01-01', status: 'active', voter_status: 'active' },
    { id: 2, resident_number: 'ANB-2', full_name: 'Unrecorded Person', date_of_birth: '1990-01-01', status: 'active', voter_status: null },
  ];
  vm.runInContext('RESIDENTS.splice(0, RESIDENTS.length, ...fetched.map(residentFromApi));', context);

  context.renderResidentsTable();

  assert.equal(vm.runInContext('RESIDENTS[0].voterStatus', context), 'active');
  assert.equal(vm.runInContext('RESIDENTS[1].voterStatus', context), null);
  assert.match(rows[0].innerHTML, /badge-green" title="Registered voter">Voter</);
  assert.doesNotMatch(rows[1].innerHTML, /Voter/);
});

test('resident details list linked incident reports with number, type, status and role only', async () => {
  const { context, fields } = await client();
  const container = { innerHTML: 'Loading', removed: false, remove() { this.removed = true; } };
  fields.set('resident-incident-links', container);
  const requested = [];
  context.fetch = async url => {
    requested.push(String(url));
    const active = String(url).includes('status=active');
    return { ok: true, json: async () => (active ? { total: 1, data: [] } : { total: 2, data: [
      { incident_number: 'INC-2026-01ABCDEFGHJKMNPQRSTVWXYZ00', incident_type: 'Noise Complaint', status: 'open', status_label: 'Open', complainant_resident_id: 7, respondent_resident_id: null, details: 'PRIVATE NARRATIVE', complainant_name: 'Hidden Party' },
      { incident_number: 'INC-2026-01ABCDEFGHJKMNPQRSTVWXYZ01', incident_type: 'Property Dispute', status: 'resolved', status_label: 'Resolved', complainant_resident_id: 3, respondent_resident_id: 7, details: 'PRIVATE NARRATIVE', respondent_name: 'Hidden Party' },
    ] }) };
  };

  await context.loadResidentIncidentLinks(7);

  assert.ok(requested.every(url => url.startsWith('/staff/incidents?') && url.includes('resident_id=7')));
  assert.match(container.innerHTML, /<strong>2<\/strong> <span>linked reports<\/span> · <strong>1<\/strong>/);
  assert.match(container.innerHTML, /Noise Complaint[\s\S]*Complainant[\s\S]*Property Dispute[\s\S]*Respondent/);
  assert.match(container.innerHTML, /does not by itself restrict document requests/);
  assert.doesNotMatch(container.innerHTML, /PRIVATE NARRATIVE|Hidden Party/);
});

test('resident details drop the incident panel when the viewer may not list incidents', async () => {
  const { context, fields } = await client();
  const container = { innerHTML: 'Loading', removed: false, remove() { this.removed = true; } };
  fields.set('resident-incident-links', container);
  context.fetch = async () => ({ ok: false, status: 403, json: async () => ({ message: 'This action is unauthorized.' }) });

  await context.loadResidentIncidentLinks(7);

  assert.equal(container.removed, true);
});

test('confirmations use the workspace modal and resolve from its buttons', async () => {
  const { context, fields } = await client();
  const modal = { classList: { add() {}, remove() {} }, setAttribute() {}, querySelector: () => null };
  const button = { textContent: '', className: '', onclick: null, focus() {} };
  fields.set('modal-confirm-action', modal);
  fields.set('confirm-action-button', button);
  fields.set('confirm-action-title', { textContent: '' });
  fields.set('confirm-action-message', { textContent: '' });

  const confirmed = context.confirmAction('Archive Maria Santos?', { title: 'Archive resident', confirmLabel: 'Archive', danger: true });
  assert.equal(fields.get('confirm-action-message').textContent, 'Archive Maria Santos?');
  assert.equal(button.textContent, 'Archive');
  assert.equal(button.className, 'btn btn-danger');
  button.onclick();
  assert.equal(await confirmed, true);

  const dismissed = context.confirmAction('Restore?');
  context.closeModal('modal-confirm-action');
  assert.equal(await dismissed, false);
});

test('issued certificate history requests the selected page and filters and renders pagination', async () => {
  const { context, fields } = await client();
  const tbody = { innerHTML: '', appendChild() {} };
  const pagination = { innerHTML: '' };
  fields.set('issued-certificates-tbody', tbody);
  fields.set('issued-certificates-pagination', pagination);
  fields.set('issued-search', { value: ' Maria ' });
  fields.set('issued-type-filter', { value: 'Barangay Clearance' });
  fields.set('issued-source-filter', { value: 'online' });
  fields.set('issued-status-filter', { value: 'revoked' });
  fields.set('issued-date-from', { value: '2026-10-01' });
  fields.set('issued-date-to', { value: '' });
  const requested = [];
  context.fetch = async url => { requested.push(url); return { ok: true, json: async () => ({ data: [], total: 23, current_page: 2, last_page: 3 }) }; };

  await context.refreshIssuedCertificates(2);

  const params = new URL(requested[0], 'http://localhost').searchParams;
  assert.equal(params.get('page'), '2');
  assert.equal(params.get('per_page'), '10');
  assert.equal(params.get('search'), 'Maria');
  assert.equal(params.get('certificate_type'), 'Barangay Clearance');
  assert.equal(params.get('source'), 'online');
  assert.equal(params.get('status'), 'revoked');
  assert.equal(params.get('date_from'), '2026-10-01');
  assert.equal(params.has('date_to'), false);
  assert.match(pagination.innerHTML, /Showing 11–20 of 23 <span>certificates<\/span>/);
  assert.match(pagination.innerHTML, /Page 2 of 3/);
  assert.match(pagination.innerHTML, /onclick="refreshIssuedCertificates\(1\)"/);
  assert.match(tbody.innerHTML, /No issued certificates match these filters/);
});

test('revoking a certificate needs a reason and the supervisor password before anything is sent', async () => {
  const { context, fields } = await client();
  const reason = { value: '', focused: false, focus() { this.focused = true; } };
  const error = { hidden: true, textContent: '' };
  fields.set('revoke-certificate-number', { textContent: '' });
  fields.set('revoke-certificate-reason', reason);
  fields.set('revoke-certificate-reason-error', error);
  const requests = [];
  let passwordPrompts = 0;
  context.fetch = async (url, options) => { requests.push({ url, method: options.method, body: JSON.parse(options.body) }); return { ok: true, json: async () => ({ message: 'Certificate revoked.' }) }; };
  vm.runInContext('openModal = () => {}; closeModal = () => {}; refreshIssuedCertificates = async () => {};', context);
  context.confirmDestructiveAction = async () => { passwordPrompts++; return passwordPrompts === 1 ? null : 'captain-password'; };

  context.openRevokeCertificate({ id: 9, certificate_number: 'CERT-2026-ABC' });
  assert.equal(fields.get('revoke-certificate-number').textContent, 'CERT-2026-ABC');
  reason.value = 'Mistake';
  await context.submitRevokeCertificate({ preventDefault() {} });
  assert.equal(error.hidden, false);
  assert.equal(reason.focused, true);
  assert.equal(passwordPrompts, 0);

  reason.value = 'Issued to the wrong person by mistake.';
  await context.submitRevokeCertificate({ preventDefault() {} });
  assert.equal(requests.length, 0, 'cancelling the password prompt sends nothing');

  await context.submitRevokeCertificate({ preventDefault() {} });
  assert.deepEqual(requests, [{ url: '/staff/issued-certificates/9/revoke', method: 'POST', body: { reason: 'Issued to the wrong person by mistake.', super_admin_password: 'captain-password' } }]);
  assert.deepEqual({ ...context.messages.at(-1) }, { message: 'Certificate revoked.', type: 'green' });
});

test('voter rows offer Edit only with voters.update, and editing sends the record version without changing the resident', async () => {
  const { context, fields } = await client();
  const element = () => ({ value: '', textContent: '', hidden: false, disabled: false, innerHTML: '' });
  ['voter-registry-tbody', 'voter-pagination', 'voter-modal-title', 'voter-resident-group', 'voter-resident-readonly', 'voter-status-group', 'voter-comelec-label', 'voter-comelec-hint',
    'voter-resident-name', 'voter-comelec-number', 'voter-precinct', 'voter-cluster', 'voter-registration-date', 'voter-status', 'voter-save-button', 'voter-resident-id'].forEach(id => fields.set(id, element()));
  vm.runInContext(`voterRegistrations = [{ id: 9, resident_name: 'Maria Santos', resident_number: 'ANB-2026-AAAA', age: 40, purok: 'Purok 1', voter_eligibility: 'regular_only', voter_eligibility_label: 'Regular only',
    precinct_number: '0123A', cluster_number: '045', registration_date: '2020-01-02', status: 'active', version: 3, integrity_valid: true, masked_comelec_voter_number: '****9012' }];`, context);

  context.window.PERSONNEL_PERMISSIONS = ['voters.view'];
  context.renderVoterRegistry(1);
  assert.doesNotMatch(fields.get('voter-registry-tbody').innerHTML, /openEditVoter/);
  context.window.PERSONNEL_PERMISSIONS = ['voters.view', 'voters.update'];
  context.renderVoterRegistry(1);
  assert.match(fields.get('voter-registry-tbody').innerHTML, /onclick="openEditVoter\(9\)"/);

  vm.runInContext('openModal = () => {}; closeModal = () => {}; loadVoterRegistry = async () => true;', context);
  context.openEditVoter(9);
  assert.equal(fields.get('voter-resident-group').hidden, true);
  assert.equal(fields.get('voter-status-group').hidden, false);
  assert.equal(fields.get('voter-precinct').value, '0123A');
  assert.match(fields.get('voter-comelec-hint').textContent, /\*\*\*\*9012/);

  fields.get('voter-status').value = 'transferred';
  let request;
  context.fetch = async (url, options) => { request = { url, options }; return { ok: true, json: async () => ({}) }; };
  await context.saveVoterRegistration();
  assert.equal(request.url, '/staff/voter-registrations/9');
  assert.equal(request.options.method, 'PATCH');
  const body = JSON.parse(request.options.body);
  assert.equal(body.version, 3);
  assert.equal(body.status, 'transferred');
  assert.equal(Object.hasOwn(body, 'resident_id'), false);
  assert.deepEqual(JSON.parse(JSON.stringify(context.messages.at(-1))), { message: 'Voter record updated.', type: 'green' });
});

test('a slower earlier voter search cannot overwrite the newest results', async () => {
  const { context, fields } = await client();
  ['voter-registry-tbody', 'voter-pagination', 'voter-purok-filter', 'voter-stat-total', 'voter-stat-sk-only', 'voter-stat-sk-regular', 'voter-stat-regular-only'].forEach(id => fields.set(id, { innerHTML: '', textContent: '', value: '' }));
  fields.set('voter-search', { value: '' });
  context.window.PERSONNEL_PERMISSIONS = ['voters.view'];
  vm.runInContext('populateVoterPurokFilter = () => {};', context);
  const registration = name => ({ id: 1, resident_name: name, resident_number: 'ANB-1', age: 40, purok: 'Purok 1', voter_eligibility: 'regular_only', voter_eligibility_label: 'Regular only', precinct_number: '1', cluster_number: '1' });
  const pending = [];
  context.fetch = () => new Promise(resolve => pending.push(resolve));

  const older = context.loadVoterRegistry(1);
  const newer = context.loadVoterRegistry(1);
  pending[1]({ ok: true, json: async () => ({ data: [registration('Newest Result')], total: 1, current_page: 1, last_page: 1 }) });
  await newer;
  pending[0]({ ok: true, json: async () => ({ data: [registration('Stale Result')], total: 1, current_page: 1, last_page: 1 }) });
  await older;

  assert.match(fields.get('voter-registry-tbody').innerHTML, /Newest Result/);
  assert.doesNotMatch(fields.get('voter-registry-tbody').innerHTML, /Stale Result/);
});

function fakeSelect() {
  const select = { options: [], value: '', innerHTML: '' };
  Object.defineProperty(select, 'innerHTML', { set() { select.options = []; select.value = ''; }, get() { return ''; } });
  select.appendChild = option => { select.options.push(option); if (option.selected) select.value = option.value; };
  return select;
}

test('rebuilding the eligibility resident list keeps the resident already chosen', async () => {
  const { context, fields } = await client();
  const select = fakeSelect();
  fields.set('elig-resident-select', select);
  context.document.createElement = () => ({ value: '', textContent: '', selected: false });
  vm.runInContext(`RESIDENTS.splice(0, RESIDENTS.length,
    { databaseId: 1, id: 'ANB-1', name: 'Ana Cruz', dob: '1990-01-01' },
    { databaseId: 2, id: 'ANB-2', name: 'Ben Reyes', dob: '1980-01-01' });`, context);

  context.populateEligResidentDropdown(null);
  select.value = 'ANB-2';
  context.populateEligResidentDropdown(null);

  assert.equal(select.value, 'ANB-2');
  assert.equal(select.options.length, 2);
});

test('the eligibility search finds residents outside the default list and checks them by database id', async () => {
  const { context, fields } = await client();
  const select = fakeSelect();
  fields.set('elig-resident-select', select);
  fields.set('elig-resident-search', { value: 'Zamora' });
  context.document.createElement = () => ({ value: '', textContent: '', selected: false });
  const requested = [];
  context.fetch = async url => { requested.push(url); return { ok: true, json: async () => ({ data: [{ id: 145, resident_number: 'ANB-145', full_name: 'Zed Zamora', first_name: 'Zed', last_name: 'Zamora', date_of_birth: '1970-01-01', status: 'active' }] }) }; };
  vm.runInContext('setTimeout = callback => { callback(); return 1; };', context);

  context.searchEligibilityResidents();
  await new Promise(resolve => setImmediate(resolve));

  const params = new URL(requested[0], 'http://localhost').searchParams;
  assert.equal(params.get('search'), 'Zamora');
  assert.equal(select.options.length, 1);
  assert.equal(select.options[0].value, 'ANB-145');
  assert.equal(context.findEligibilityResident('ANB-145').databaseId, 145);
});

test('incident rows use status colours by meaning, split date and time, and open the record from the row', async () => {
  const { context, fields } = await client();
  const tbody = { innerHTML: '', rows: [], appendChild(row) { this.rows.push(row); } };
  fields.set('incidents-tbody', tbody);
  fields.set('incident-pagination', { innerHTML: '' });
  context.document.createElement = () => {
    const listeners = {};
    return { className: '', tabIndex: -1, innerHTML: '', listeners, setAttribute() {}, addEventListener(type, handler) { listeners[type] = handler; }, querySelectorAll: () => [] };
  };
  vm.runInContext(`INCIDENTS.splice(0, INCIDENTS.length, { id: 4, incident_number: 'INC-1', incident_type: 'Theft', occurred_at_display: 'Nov 2, 2026 3:30 PM', location: 'Purok 1',
    complainant_name: 'Ana Cruz', respondent_name: null, severity: 'high', severity_label: 'High', status: 'closed', status_label: 'Closed', can_update: true, can_archive: false });
    opened = []; openViewIncident = id => opened.push(id);`, context);

  context.renderIncidents(1);
  const row = tbody.rows[0];

  assert.match(row.innerHTML, /class="badge badge-gray">Closed/);
  assert.match(row.innerHTML, /<span>Nov 2, 2026<\/span><small>3:30 PM<\/small>/);
  assert.doesNotMatch(row.innerHTML, /btn-primary/);
  row.listeners.click({ target: { closest: () => ({ tagName: 'BUTTON' }) } });
  assert.equal(context.opened.length, 0);
  row.listeners.click({ target: { closest: () => null } });
  assert.deepEqual([...context.opened], [4]);
  assert.equal(context.incidentStatusBadge('open'), 'badge-blue');
  assert.equal(context.incidentStatusBadge('referred'), 'badge-purple');
});

test('the audit log filters by category, pages with the shared pager, and shows permission changes as text', async () => {
  const { context, fields } = await client();
  const list = { children: [], appendChild(row) { this.children.push(row); } };
  Object.defineProperty(list, 'innerHTML', { get: () => '', set() { list.children = []; } });
  const pager = { innerHTML: '' };
  const category = { value: 'all' };
  fields.set('audit-log-list', list);
  fields.set('audit-pagination', pager);
  fields.set('audit-category-filter', category);
  fields.set('audit-date-filter', { value: '' });
  context.document.createElement = () => {
    const element = { children: [], textContent: '', innerHTML: '', style: {}, className: '', setAttribute() {}, addEventListener() {}, append(...nodes) { this.children.push(...nodes); } };
    return element;
  };
  vm.runInContext(`LIVE_AUDIT_LOGS.splice(0, LIVE_AUDIT_LOGS.length,
    ...Array.from({ length: 25 }, (_, index) => ({ id: index + 1, type: index < 22 ? 'record' : 'auth', action: 'residents update', detail: 'Record ID: ' + index, user: 'Clerk', time: 'Nov 01, 2026 10:00', date: '2026-11-01' })));`, context);

  context.renderAuditLog();
  assert.match(pager.innerHTML, /Showing 1–20 of 25 <span>events<\/span>/);
  assert.match(pager.innerHTML, /onclick="goToAuditPage\(2\)">Next/);

  context.filterAuditType('auth');
  assert.equal(category.value, 'auth');
  assert.match(pager.innerHTML, /Showing 1–3 of 3 <span>events<\/span>/);
  assert.equal(list.children.length, 3);

  const detailFields = { children: [], replaceChildren() { this.children = []; }, append(node) { this.children.push(node); } };
  fields.set('audit-detail-fields', detailFields);
  fields.set('audit-detail-subtitle', { textContent: '' });
  vm.runInContext('openModal = () => {};', context);
  context.openAuditDetail({ id: 9, type: 'security', action: 'users permissions changed', detail: 'Staff #4', user: 'Super Admin', actor_id: 1, target_staff_id: 4, time: 'Nov 01, 2026 10:00',
    before_assignments: ['records.view'], after_assignments: ['records.view', 'records.update'] });
  const values = detailFields.children.map(wrapper => `${wrapper.children[0].textContent}=${wrapper.children[1].textContent}`);
  assert.ok(values.includes('Performed by=Super Admin (user #1)'));
  assert.ok(values.includes('Affected record=Staff account #4'));
  assert.ok(values.includes('Permissions after=records.view, records.update'));
});

test('every administrator, Captain included, gets the portal-account suspend button the server allows; staff do not', async () => {
  const { context, fields } = await client();
  const panel = { isConnected: true, innerHTML: '', textContent: '' };
  fields.set('resident-portal-account', panel);
  context.adminRequest = async () => ({ status: 'active', portal_account: { registered: true, is_active: true, email: 'm***@example.test', created_at: '2026-11-01', email_verified: true, activation: 'staff_code' } });

  for (const [user, expected] of [
    [{ role: 'captain', is_administrator: true }, true],
    [{ role: 'admin', is_super_admin: true, is_administrator: true }, true],
    [{ role: 'staff', is_administrator: false }, false],
  ]) {
    context.window.AUTHENTICATED_USER = user;
    await context.loadResidentPortalAccount(5);
    assert.equal(panel.innerHTML.includes('changePortalAccountStatus(5, false, this)'), expected, user.role);
  }
});

test('a Captain account shows only the VAWC boxes, and only a Super Admin editing someone else can change them', async () => {
  const { context, fields } = await client();
  const input = key => ({ dataset: { staffPermission: key }, checked: false, disabled: false });
  const module = keys => { const inputs = keys.map(input); return { hidden: false, inputs, querySelector: selector => selector.includes('vawc.') ? inputs.find(i => i.dataset.staffPermission.startsWith('vawc.')) ?? null : inputs[0] ?? null }; };
  const records = module(['records.view']);
  const vawc = module(['vawc.view', 'vawc.update']);
  const groups = [{ hidden: false, querySelectorAll: () => [records] }, { hidden: false, querySelectorAll: () => [vawc] }];
  const toolbar = { hidden: false };
  const lock = { hidden: true };
  const section = { hidden: true, querySelectorAll: selector => ({
    '.staff-permission-module': [records, vawc], '.staff-permission-group': groups, '.staff-preset-toolbar, .staff-preset-help': [toolbar],
    '[data-staff-permission^="vawc."]': vawc.inputs,
  })[selector] ?? [] };
  fields.set('staff-assignment-fields', section);
  fields.set('vawc-permission-lock', lock);
  fields.set('adduser-edit-id', { value: '4' });
  fields.set('adduser-role', { value: 'captain' });
  vm.runInContext("USERS.push({ id: 4, role: 'captain' }, { id: 2, role: 'admin', is_super_admin: true });", context);

  context.window.AUTHENTICATED_USER = { id: 4, role: 'captain', is_administrator: true };
  context.toggleStaffAssignmentFields();
  assert.equal(section.hidden, false);
  assert.equal(records.hidden, true);
  assert.equal(groups[0].hidden, true);
  assert.equal(vawc.hidden, false);
  assert.equal(toolbar.hidden, true);
  assert.ok(vawc.inputs.every(item => item.disabled));
  assert.equal(lock.hidden, false);

  context.window.AUTHENTICATED_USER = { id: 2, role: 'admin', is_super_admin: true, is_administrator: true };
  context.toggleStaffAssignmentFields();
  assert.ok(vawc.inputs.every(item => !item.disabled));
  assert.equal(lock.hidden, true);
});

test('the reset-link action shows for active accounts the viewer may change, never for another Super Admin or a new account', async () => {
  const { context, fields } = await client();
  const row = { hidden: false };
  fields.set('adduser-reset-link-row', row);
  context.window.AUTHENTICATED_USER = { id: 2, role: 'captain', is_administrator: true };

  for (const [user, hidden] of [
    [null, true],
    [{ id: 5, role: 'staff', is_active: true }, false],
    [{ id: 6, role: 'staff', is_active: false }, true],
    [{ id: 1, role: 'admin', is_super_admin: true, is_active: true }, true],
  ]) {
    context.showUserResetLink(user);
    assert.equal(row.hidden, hidden, JSON.stringify(user));
  }
  context.window.AUTHENTICATED_USER = { id: 1, role: 'admin', is_super_admin: true, is_administrator: true };
  context.showUserResetLink({ id: 1, role: 'admin', is_super_admin: true, is_active: true });
  assert.equal(row.hidden, false);
});
test('password guidance marks each required rule of the staff policy as the password is typed', () => {
  const source = readFileSync(new URL('../../public/js/admin.js', import.meta.url), 'utf8');
  const code = source.slice(source.indexOf('const PASSWORD_RULES = {'), source.indexOf('function openAddUser() {'));
  const items = ['length', 'uppercase', 'lowercase', 'number', 'symbol'].map(rule => ({ dataset: { passwordRule: rule }, classes: new Set(), classList: { toggle(name, on) { on ? this.owner.classes.add(name) : this.owner.classes.delete(name); } } }));
  items.forEach(item => { item.classList.owner = item; });
  const fields = { 'adduser-password': { value: '' }, 'adduser-pass-label': { textContent: '' } };
  const context = vm.createContext({
    adminText: text => text,
    document: { getElementById: id => fields[id], querySelectorAll: () => items },
  });
  vm.runInContext(code, context);
  const states = () => Object.fromEntries(items.map(item => [item.dataset.passwordRule, item.dataset.state]));

  context.setPasswordGuidanceMode(false);
  assert.equal(fields['adduser-pass-label'].textContent, 'Required. The password must meet every requirement below.');
  assert.deepEqual(states(), { length: 'idle', uppercase: 'idle', lowercase: 'idle', number: 'idle', symbol: 'idle' });

  fields['adduser-password'].value = 'barangay.hall';
  context.updatePasswordGuidance();
  assert.deepEqual(states(), { length: 'met', uppercase: 'unmet', lowercase: 'met', number: 'unmet', symbol: 'met' }, 'a period counts as a symbol');

  fields['adduser-password'].value = 'Barangay.Hall2026';
  context.updatePasswordGuidance();
  assert.deepEqual(states(), { length: 'met', uppercase: 'met', lowercase: 'met', number: 'met', symbol: 'met' });

  fields['adduser-password'].value = 'BarangayHall2026';
  context.updatePasswordGuidance();
  assert.equal(states().symbol, 'unmet', 'letters and numbers alone are not a symbol');

  context.setPasswordGuidanceMode(true);
  assert.match(fields['adduser-pass-label'].textContent, /^Leave blank to keep the current password/);
});

test('the tablet and phone drawer moves focus in when opened and back to Menu when Escape closes it', () => {
  const source = readFileSync(new URL('../../public/js/admin.js', import.meta.url), 'utf8');
  const code = source.slice(source.indexOf('function toggleNavigation(force'), source.indexOf('// A click outside the open drawer closes it'));
  const classes = new Set();
  let focused = null;
  const firstItem = { focus() { focused = 'first item'; } };
  const sidebar = { classList: { contains: name => classes.has(name), toggle(name, on) { on ? classes.add(name) : classes.delete(name); } }, querySelector: () => firstItem };
  const attributes = {};
  const button = { setAttribute(name, value) { attributes[name] = value; }, focus() { focused = 'Menu'; } };
  let buttonDisplay = 'inline-flex';
  const context = vm.createContext({
    document: { getElementById: () => sidebar, querySelector: () => button },
    getComputedStyle: () => ({ display: buttonDisplay }),
  });
  vm.runInContext(code, context);

  context.toggleNavigation();
  assert.equal(classes.has('is-open'), true);
  assert.equal(attributes['aria-expanded'], 'true');
  assert.equal(focused, 'first item');

  context.toggleNavigation(false, { restoreFocus: true });
  assert.equal(classes.has('is-open'), false);
  assert.equal(focused, 'Menu');

  // Desktop: the sidebar is always shown, so opening state changes never move focus.
  buttonDisplay = 'none';
  focused = null;
  context.toggleNavigation(true);
  context.toggleNavigation(false, { restoreFocus: true });
  assert.equal(focused, null);
});

test('an approved request waits in Processing for "Mark ready", because only a ready request can be issued', async () => {
  const { context } = await client();
  const card = status => context.boardRequestFromServer({ id: 3, reference_code: 'REQ-1', status, source: 'online' });

  assert.equal(context.getLane(card('approved').status, 'Online'), 'processing');
  assert.equal(context.getLane(card('ready_for_release').status, 'Online'), 'ready');
  assert.equal(context.getLane(card('released').status, 'Online'), 'completed');
});

test('request details lead to the issued certificate, or say why a released request has none to reprint', async () => {
  const { context, fields } = await client();
  const wrap = { hidden: true };
  const target = { innerHTML: '' };
  fields.set('vreq-certificate-wrap', wrap);
  fields.set('vreq-certificate', target);
  const request = (status, issued) => context.boardRequestFromServer({ id: 6, reference_code: 'REQ-6', status, issued_certificate: issued });

  context.renderRequestCertificate(request('released', { id: 41, certificate_number: 'CERT-2026-ABC', revoked_at: null }));
  assert.equal(wrap.hidden, false);
  assert.match(target.innerHTML, /CERT-2026-ABC/);
  assert.match(target.innerHTML, /href="\/staff\/issued-certificates\/41\/print"/);

  context.renderRequestCertificate(request('released', { id: 41, certificate_number: 'CERT-2026-ABC', revoked_at: '2026-11-01T00:00:00Z' }));
  assert.match(target.innerHTML, /Revoked/);
  assert.doesNotMatch(target.innerHTML, /\/print/);

  context.renderRequestCertificate(request('released', null));
  assert.equal(wrap.hidden, false);
  assert.match(target.innerHTML, /No certificate was issued for this request/);
  assert.match(target.innerHTML, /href="\/staff\/document-requests\/6"/);

  context.renderRequestCertificate(request('processing', null));
  assert.equal(wrap.hidden, true);
});

test('staff without print access see the certificate number in request details but no reprint', async () => {
  const { context, fields } = await client();
  const target = { innerHTML: '' };
  fields.set('vreq-certificate-wrap', { hidden: true });
  fields.set('vreq-certificate', target);
  context.window.PERSONNEL_PERMISSIONS = ['documents.view'];

  context.renderRequestCertificate(context.boardRequestFromServer({ id: 6, reference_code: 'REQ-6', status: 'released', issued_certificate: { id: 41, certificate_number: 'CERT-2026-ABC', revoked_at: null } }));
  assert.match(target.innerHTML, /CERT-2026-ABC/);
  assert.doesNotMatch(target.innerHTML, /Reprint|\/print/);
});

function readinessFields(fields) {
  const panel = { hidden: true, dataset: {}, innerHTML: '', querySelectorAll: () => [], querySelector: () => null };
  const submit = { disabled: false, attributes: {}, setAttribute(name, value) { this.attributes[name] = value; }, removeAttribute(name) { delete this.attributes[name]; } };
  fields.set('print-release-readiness', panel);
  fields.set('print-release-submit', submit);
  fields.set('print-document-request-id', { value: '4' });
  return { panel, submit };
}

test('Print & Release lists what is missing and keeps Issue & Print off until it is complete', async () => {
  const { context, fields } = await client();
  const { panel, submit } = readinessFields(fields);
  context.window.PERSONNEL_PERMISSIONS = ['documents.issue', 'records.update'];

  context.renderReleaseReadiness({ ready: false, resident: { id: 7, resident_number: 'ANB-2026-X' }, items: [
    { kind: 'verification', message: 'Indigency must be verified by authorized barangay staff.', action: 'indigency', field: 'is_verified_indigent' },
    { kind: 'missing', message: 'Nationality', action: 'resident', field: 'nationality' },
    { kind: 'blocked', message: 'Resident is not in good standing.', action: null, field: null },
  ] });

  assert.equal(panel.dataset.state, 'blocked');
  assert.equal(submit.disabled, true);
  assert.equal(submit.attributes['aria-describedby'], 'print-release-readiness-title');
  assert.match(panel.innerHTML, /Complete before issuing/);
  assert.match(panel.innerHTML, /Missing from the resident record:<\/span>|Missing from the resident record: <strong>Nationality<\/strong>/);
  assert.match(panel.innerHTML, /data-readiness-field="is_verified_indigent">Verify in Resident Records/);
  assert.match(panel.innerHTML, /data-readiness-field="nationality">Complete missing information/);
  assert.match(panel.innerHTML, /Resident is not in good standing\./);
  assert.match(panel.innerHTML, /data-readiness-recheck/);

  context.renderReleaseReadiness({ ready: true, resident: null, items: [] });
  assert.equal(panel.dataset.state, 'ready');
  assert.equal(submit.disabled, false);
});

test('staff without Resident Records or Voters access are told who completes an item, with no button', async () => {
  const { context, fields } = await client();
  const { panel } = readinessFields(fields);
  context.window.PERSONNEL_PERMISSIONS = ['documents.issue'];

  context.renderReleaseReadiness({ ready: false, resident: { id: 7, resident_number: 'ANB-2026-X' }, items: [
    { kind: 'missing', message: 'Nationality', action: 'resident', field: 'nationality' },
    { kind: 'verification', message: 'An active verified voter registration is required.', action: 'voters', field: null },
  ] });

  assert.doesNotMatch(panel.innerHTML, /data-readiness-field|href="\/staff\/voters"/);
  assert.match(panel.innerHTML, /Staff who may update Resident Records complete this\./);
  assert.match(panel.innerHTML, /Staff who manage voters complete this\./);
});

test('a failed check never blocks issuing, which enforces every rule itself', async () => {
  const { context, fields } = await client();
  const { panel, submit } = readinessFields(fields);
  context.fetch = async () => ({ ok: false, json: async () => ({ message: 'Server error' }) });

  await context.refreshReleaseReadiness();
  assert.equal(panel.dataset.state, 'error');
  assert.equal(submit.disabled, false);
  assert.match(panel.innerHTML, /Issue &amp; Print still checks every requirement/);
});

test('completing missing information opens the saved resident record at the missing field, never a new record', async () => {
  const { context, fields } = await client();
  readinessFields(fields);
  const requests = [];
  const focused = [];
  context.fetch = async url => { requests.push(url); return { ok: true, json: async () => ({ data: [{ id: 7, resident_number: 'ANB-2026-X', first_name: 'Ana', last_name: 'Reyes', full_name: 'Ana Reyes', household_id: 3, household: { household_number: 'HH-1', address: 'Purok 1' }, status: 'active' }] }) }; };
  fields.set('res-nationality', { focus() { focused.push('res-nationality'); }, scrollIntoView() {} });
  const opened = [];
  vm.runInContext('fillResidentForm = resident => { filled = resident; }; var filled = null;', context);
  context.openModal = id => opened.push(id);
  vm.runInContext(`releaseReadiness = { ready: false, resident: { id: 7, resident_number: 'ANB-2026-X' }, items: [] };`, context);

  await context.openResidentForCertificate('nationality');
  assert.match(requests[0], /^\/staff\/residents\?search=ANB-2026-X/);
  assert.equal(vm.runInContext('filled.databaseId', context), 7);
  assert.equal(vm.runInContext('filled.householdId', context), 3);
  assert.equal(vm.runInContext("RESIDENTS.filter(r => r.databaseId === 7).length", context), 1);
  assert.deepEqual(opened, ['modal-resident']);
  assert.deepEqual(focused, ['res-nationality']);
});

test('the eligibility checker says eligible, eligible once completed, or not eligible, and offers Proceed only when ready', async () => {
  const { context } = await client();
  context.window.PERSONNEL_PERMISSIONS = ['eligibility.view', 'records.update'];
  const base = { resident: { id: 7, resident_number: 'ANB-2026-X', full_name: 'Ana Reyes' }, certificate_type: 'Certificate of Indigency' };

  const incomplete = { ...base, eligible: false, reasons: ['Indigency must be verified.'], disqualifications: [], verifications: ['Indigency must be verified.'], missing_details: ['Nationality'] };
  assert.equal(context.eligibilityOutcome(incomplete), 'incomplete');
  const html = context.eligibilityResultHtml(incomplete);
  assert.match(html, /Eligible once completed/);
  assert.match(html, /Needs staff verification/);
  assert.match(html, /Missing from the resident record[\s\S]*Nationality/);
  assert.match(html, /data-eligibility-complete/);

  assert.equal(context.eligibilityOutcome({ ...base, eligible: true, reasons: [], disqualifications: [], verifications: [], missing_details: ['Resident photo'] }), 'incomplete');
  assert.equal(context.eligibilityOutcome({ ...base, eligible: true, reasons: [], disqualifications: [], verifications: [], missing_details: [] }), 'eligible');
  const ineligible = { ...base, eligible: false, reasons: ['Resident is not in good standing.'], disqualifications: ['Resident is not in good standing.'], verifications: [], missing_details: [] };
  assert.equal(context.eligibilityOutcome(ineligible), 'ineligible');
  assert.match(context.eligibilityResultHtml(ineligible), /Not eligible because[\s\S]*not in good standing/);
  assert.equal(context.eligibilityOutcome({ ...base, eligible: false, reasons: ['Older server reason.'] }), 'ineligible');
});

test('resident rows flag records missing certificate details, and household names use the body font', async () => {
  const { context, fields } = await client();
  const tbody = { innerHTML: '', dataset: {}, addEventListener() {}, appendChild(row) { this.rows = [...(this.rows || []), row]; } };
  fields.set('records-tbody', tbody);
  context.document.createElement = () => ({ innerHTML: '', classList: { add() {} }, setAttribute() {}, addEventListener() {}, querySelectorAll: () => [] });
  vm.runInContext(`RESIDENTS.splice(0, RESIDENTS.length, residentFromApi({ id: 1, resident_number: 'ANB-1', full_name: 'Ana Reyes', date_of_birth: '1990-01-01', status: 'active', missing_certificate_details: ['nationality', 'photo'], household: null }))`, context);
  context.renderResidentsTable();
  assert.match(tbody.rows[0].innerHTML, /resident-missing-badge[^>]*title="Missing for certificates: Nationality, Resident photo">Missing details/);
  assert.match(tbody.rows[0].innerHTML, /resident-household is-unassigned">Unassigned/);
});

test('a workspace restored by browser Back opens the screen its address names, not the one first loaded', async () => {
  const { context, fields } = await client();
  context.document.body = { classList: { contains: () => true } };
  context.opened = [];
  vm.runInContext(`loadPuroks = async () => {}; launchApp = () => {};
    startDocumentRequestLiveRefresh = refreshIssuedCertificates = populateManualResidentDropdown = loadVoterRegistry = startDashboardLiveRefresh = () => {};
    loadResidents = async () => {}; findNavItem = id => ({ id, style: {} });
    showScreen = (id, navigation, updateHistory) => opened.push(id + ':' + updateHistory);`, context);
  fields.set('app', {});
  context.window.ADMIN_ACTIVE_SCREEN = 'dashboard';
  context.window.ADMIN_SCREEN_ROUTES = { dashboard: 'http://localhost/admin', incidents: 'http://localhost/staff/incidents' };

  context.window.location = { href: 'http://localhost/staff/incidents', pathname: '/staff/incidents', search: '' };
  await context.initializePersonnelWorkspace();
  context.window.location = { href: 'http://localhost/staff/households/page', pathname: '/staff/households/page', search: '' };
  await context.initializePersonnelWorkspace();

  assert.equal(context.opened.join(','), 'incidents:false,dashboard:false');
});
