import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const script = readFileSync(new URL('../../public/js/admin.js', import.meta.url), 'utf8');

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
  for (const id of ['print-document-request-id', 'print-certificate-type', 'print-resident-name', 'print-purpose', 'print-amount-paid']) fields.set(id, { value: '2026-12-31' });
  vm.runInContext("CERT_REQUESTS.push({ code: 'TEST', id: 2, type: 'Registered Voter Certification' }); openModal = () => {};", context);
  context.printCert('TEST');
  assert.equal(fields.get('print-amount-paid').value, 'PHP 25.00');
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
  const section = { hidden: true };
  fields.set('staff-assignment-fields', section);
  vm.runInContext("USERS.push({ id: 7, is_super_admin: true });", context);

  context.toggleStaffAssignmentFields();

  assert.equal(section.hidden, false);
});


for (const photo of [
  { name: 'report.pdf', type: 'application/pdf', size: 100 },
  { name: 'photo.jpg', type: 'text/plain', size: 100 },
  { name: 'photo.svg', type: 'image/svg+xml', size: 100 },
  { name: 'photo.png', type: 'image/png', size: 5 * 1024 * 1024 + 1 },
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
  assert.equal(params.get('date_from'), '2026-10-01');
  assert.equal(params.has('date_to'), false);
  assert.match(pagination.innerHTML, /Showing 11–20 of 23 <span>certificates<\/span>/);
  assert.match(pagination.innerHTML, /Page 2 of 3/);
  assert.match(pagination.innerHTML, /onclick="refreshIssuedCertificates\(1\)"/);
  assert.match(tbody.innerHTML, /No issued certificates match these filters/);
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
