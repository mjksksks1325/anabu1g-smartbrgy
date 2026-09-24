import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const script = readFileSync(new URL('../../public/js/admin.js', import.meta.url), 'utf8');

async function client() {
  const fields = new Map();
  const context = vm.createContext({
    console, URLSearchParams, Blob, URL,
    document: {
      cookie: '', hidden: false,
      getElementById: id => fields.get(id) ?? null,
      querySelector: () => null, querySelectorAll: () => [], addEventListener() {},
    },
    window: { AUTHENTICATED_USER: { id: 1, name: 'Admin', role: 'admin' }, addEventListener() {} },
    localStorage: { removeItem() {}, setItem() {}, getItem() { return null; } },
    setInterval() { return 1; }, clearInterval() {}, setTimeout() {}, clearTimeout() {},
    fetch: async () => ({ ok: true, json: async () => [] }),
  });
  vm.runInContext(script, context);
  await new Promise(resolve => setImmediate(resolve));
  context.messages = [];
  vm.runInContext('showToast = (message, type) => messages.push({ message, type });', context);
  return { context, fields };
}

test('cancelling resident restoration does not send an administrative mutation', async () => {
  const { context } = await client();
  context.confirm = () => false;
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
  context.confirm = () => true;
  let finish;
  context.fetch = () => new Promise(resolve => { finish = resolve; });
  const button = { disabled: false, closest: () => ({ classList: { contains: () => true } }) };
  const pending = context.issuePortalActivation(1, button);
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
