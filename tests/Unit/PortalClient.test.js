import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

function portal() {
  const elements = new Map();
  function element(id) {
    if (!elements.has(id)) {
      const classes = new Set();
      const attributes = {};
      elements.set(id, {
        id, value: '', textContent: '', innerHTML: '', style: {}, files: [], checked: false, hidden: false,
        classList: { add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name), toggle(name, state) { if (state ?? !classes.has(name)) classes.add(name); else classes.delete(name); } },
        setAttribute: (name, value) => attributes[name] = value,
        getAttribute: name => attributes[name], removeAttribute: name => delete attributes[name],
        focus() { this.focused = true; }, checkValidity() { return this.valid !== false; }, addEventListener() {},
      });
    }
    return elements.get(id);
  }
  const screens = ['screen-terms', 'screen-doctype', 'screen-form', 'screen-attachment', 'screen-confirm', 'screen-status'].map(element);
  screens[0].classList.add('active');
  const cards = [element('card-BC'), element('card-BBC')];
  const values = { name: 'Juan Dela Cruz', address: 'Anabu I-G', email: 'juan@example.test', dob: '2000-01-01', purpose: 'Employment', business: '' };
  for (const [key, value] of Object.entries(values)) element('f-' + key).value = value;
  element('tnc-agree').checked = true;
  const saved = new Map();
  const revoked = [];
  const listeners = new Map();
  const context = vm.createContext({
    console, FormData, Object,
    DOC_TYPES: { BC: { label: 'Barangay Clearance', icon: 'BC', fee: 'PHP 50', days: '1 day' }, BBC: { label: 'Business Clearance', icon: 'BBC', fee: 'PHP 200', days: '3 days' } },
    selectedDocId: 'BC', tncScrolled: true, lastCode: '', _portalDark: false, _SK: 'smartbrgy_session',
    document: {
      body: element('body'), getElementById: element, addEventListener() {},
      querySelector(selector) {
        if (selector === '.screen.active') return screens.find(screen => screen.classList.contains('active'));
        if (selector.startsWith('.cert-btn')) return cards.find(card => selector.includes(card.id.slice(5)));
        if (selector.startsWith('meta')) return element('csrf-meta');
        return null;
      },
      querySelectorAll: selector => selector === '.screen' ? screens : selector === '.cert-btn' ? cards : [],
    },
    window: { history: { pushState() {}, replaceState() {} }, scrollTo() {}, addEventListener(name, handler) { listeners.set(name, handler); } },
    navigator: {}, URL: { createObjectURL: file => 'blob:' + file.name, revokeObjectURL: url => revoked.push(url) },
    sessionStorage: { setItem: (key, value) => saved.set(key, value), getItem: key => saved.get(key), removeItem: key => saved.delete(key) },
    setTimeout() {}, clearTimeout() {},
  });
  for (const file of ['portal-form.js', 'portal-session.js', 'attachments.js', 'portal-ui.js', 'document-request.js', 'status-checker.js']) {
    vm.runInContext(readFileSync(new URL('../../public/js/' + file, import.meta.url), 'utf8'), context);
  }
  context.messages = [];
  vm.runInContext('toast = (message, type) => messages.push({ message, type });', context);
  return { context, element, screens, saved, revoked, listeners };
}

test('mobile navigation still works when browser storage is unavailable', () => {
  const { context, element } = portal();
  context.sessionStorage.setItem = () => { throw new Error('Storage blocked'); };
  context.sessionStorage.getItem = () => { throw new Error('Storage blocked'); };
  context.sessionStorage.removeItem = () => { throw new Error('Storage blocked'); };

  assert.doesNotThrow(() => context.initializePortalSession());
  assert.doesNotThrow(() => context.showScreen('screen-status'));
  assert.equal(element('screen-status').classList.contains('active'), true);
});

test('invalid restored screens cannot leave the portal blank', () => {
  const { context, saved, element } = portal();
  saved.set('smartbrgy_session', JSON.stringify({ screen: 'missing-screen', docId: 'invalid', tncScrolled: true, tncChecked: true }));

  context.initializePortalSession();

  assert.equal(element('screen-terms').classList.contains('active'), true);
});

test('opening the portal discards legacy drafts and resets browser-restored resident data', () => {
  const { context, saved, element } = portal();
  saved.set('smartbrgy_session', JSON.stringify({ screen: 'screen-confirm', form: { name: 'Previous Resident' }, lastCode: 'REQ-OLD' }));
  element('status-code').value = 'REQ-OLD';
  element('conf-summary').textContent = 'Previous Resident';
  element('f-attachment').value = 'previous-id.jpg';

  context.initializePortalSession();

  assert.equal(saved.has('smartbrgy_session'), false);
  for (const id of ['f-name', 'f-address', 'f-email', 'f-dob', 'f-purpose', 'f-business', 'status-code', 'f-attachment']) {
    assert.equal(element(id).value, '');
  }
  assert.equal(element('conf-summary').textContent, '');
  assert.equal(element('tnc-agree').checked, false);
  assert.equal(element('tnc-agree').disabled, true);
  assert.equal(element('btn-proceed-terms').disabled, true);
  assert.equal(context.selectedDocId, null);
  assert.equal(context.lastCode, '');
  assert.equal(element('screen-terms').classList.contains('active'), true);
});

test('navigation retains the current form in memory and stores only the theme preference', () => {
  const { context, saved, element } = portal();

  context.goToAttachment();
  context.goBack('screen-form');
  context.togglePortalTheme();

  assert.equal(element('f-name').value, 'Juan Dela Cruz');
  assert.deepEqual([...saved], [['smartbrgy_portal_theme', 'dark']]);
  context.initializePortalSession();
  assert.equal(element('body').classList.contains('dark-mode'), true);
  assert.equal(element('f-name').value, '');
});

test('returning from the browser page cache clears resident details and attachments', () => {
  const { context, element, listeners, revoked } = portal();
  element('f-attachment').files = [{ name: 'id.jpg', size: 1000, type: 'image/jpeg' }];
  context.previewAttachment(element('f-attachment'));
  context.showScreen('screen-attachment');

  listeners.get('pageshow')({ persisted: true });

  assert.equal(element('f-name').value, '');
  assert.equal(element('f-attachment').value, '');
  assert.equal(element('att-preview').style.display, 'none');
  assert.deepEqual(revoked, ['blob:id.jpg']);
  assert.equal(element('screen-terms').classList.contains('active'), true);
});

test('required information errors stay visible and focus the invalid field', () => {
  const { context, element } = portal();
  element('f-email').valid = false;

  context.goToAttachment();

  assert.equal(element('screen-form').classList.contains('active'), true);
  assert.equal(element('portal-form-error').hidden, false);
  assert.equal(element('f-email').focused, true);
  assert.equal(element('f-email').getAttribute('aria-invalid'), 'true');
});

test('replacing an attachment with an invalid image clears stale previews', () => {
  const { context, element, revoked } = portal();
  const input = element('f-attachment');
  input.files = [{ name: 'valid.jpg', size: 1000, type: 'image/jpeg' }];
  context.previewAttachment(input);
  input.files = [{ name: 'oversized.jpg', size: 6 * 1024 * 1024, type: 'image/jpeg' }];

  context.previewAttachment(input);

  assert.deepEqual(revoked, ['blob:valid.jpg']);
  assert.equal(element('att-preview').style.display, 'none');
  assert.equal(element('att-dropzone').style.display, 'block');
  assert.equal(input.value, '');
});

test('rapid repeated taps submit only once and escape the confirmation', async () => {
  const { context, element } = portal();
  element('f-name').value = '<img src=x onerror=alert(1)>';
  let finish, submissions = 0;
  context.sendPortalDocumentRequest = () => { submissions++; return new Promise(resolve => finish = resolve); };

  const first = context.submitRequest();
  await context.submitRequest();
  finish({ ok: true, json: async () => ({ reference_code: 'REQ-2026-ABC123' }) });
  await first;

  assert.equal(submissions, 1);
  assert.equal(element('submit-request-button').disabled, false);
  assert.match(element('conf-summary').innerHTML, /&lt;img/);
  assert.doesNotMatch(element('conf-summary').innerHTML, /<img/);
  assert.equal(element('screen-confirm').classList.contains('active'), true);
  await context.submitRequest();
  assert.equal(submissions, 1);
});

test('unknown status codes are escaped and rate limits are not reported as missing records', async () => {
  const { context, element } = portal();
  element('status-code').value = '<svg onload=alert(1)>';
  context.fetch = async () => ({ status: 404, ok: false, json: async () => ({}) });
  await context.checkStatus();
  assert.doesNotMatch(element('status-result').innerHTML, /<SVG/);
  assert.match(element('status-result').innerHTML, /&lt;SVG/);
  context.fetch = async () => ({ status: 429, ok: false, json: async () => ({}) });
  await context.checkStatus();
  assert.match(element('status-result').innerHTML, /isang minuto/);
});

test('an expired CSRF session retries once with a new token', async () => {
  const { context } = portal();
  const calls = [];
  context.fetch = async (url, options) => {
    calls.push({ url, token: options.headers['X-CSRF-TOKEN'] });
    if (url.endsWith('csrf-token')) return { ok: true, json: async () => ({ token: 'token-' + calls.length }) };
    return { status: calls.length === 2 ? 419 : 200 };
  };

  const response = await context.sendPortalDocumentRequest(new FormData());

  assert.equal(response.status, 200);
  assert.equal(calls.length, 4);
  assert.equal(calls[1].token, 'token-1');
  assert.equal(calls[3].token, 'token-3');
});
