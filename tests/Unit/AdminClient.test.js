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

test('request lookup gets rejected requests from the server and escapes their content', async () => {
  const { context, fields } = await client();
  fields.set('qr-status-result', { style: {} });
  fields.set('qr-status-result-card', { textContent: '', innerHTML: '' });
  let requestedUrl;
  context.fetch = async url => {
    requestedUrl = url;
    return { ok: true, json: async () => ({
      reference_code: 'REQ-2026-ABC', document_type: 'Barangay Clearance', status: 'rejected',
      rejection_reason: '<img src=x onerror=alert(1)>',
    }) };
  };

  await context.checkRequestStatus(' req-2026-abc ');

  assert.equal(requestedUrl, '/portal/request/REQ-2026-ABC');
  assert.match(fields.get('qr-status-result-card').innerHTML, /rejected/);
  assert.match(fields.get('qr-status-result-card').innerHTML, /&lt;img/);
  assert.doesNotMatch(fields.get('qr-status-result-card').innerHTML, /<img/);
});

test('failed request lookup replaces stale results with a readable error', async () => {
  const { context, fields } = await client();
  fields.set('qr-status-result', { style: {} });
  fields.set('qr-status-result-card', { textContent: 'Old success' });
  context.fetch = async () => ({ ok: false, json: async () => ({ message: 'Request not found.' }) });

  await context.checkRequestStatus('missing');

  assert.equal(fields.get('qr-status-result-card').textContent, 'Request not found.');
  assert.equal(context.messages.at(-1).type, 'red');
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
