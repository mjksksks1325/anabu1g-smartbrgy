import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

function residentClient() {
  function element(value = '') {
    const attributes = new Map();
    return {
      value, textContent: '', hidden: true, readOnly: false, disabled: false, dataset: {}, listeners: {},
      addEventListener(name, handler) { this.listeners[name] = handler; },
      replaceChildren() { this.cleared = true; },
      hasAttribute: name => attributes.has(name), setAttribute: (name, value) => attributes.set(name, value), removeAttribute: name => attributes.delete(name),
      getAttribute: name => attributes.get(name), focus() { this.focused = true; },
      classList: { classes: new Set(), toggle(name, state) { if (state) this.classes.add(name); else this.classes.delete(name); }, contains(name) { return this.classes.has(name); } },
    };
  }
  const fields = new Map(['f-name', 'f-address', 'f-email', 'f-dob', 'portal-form-error'].map(id => [id, element('Previous resident')]));
  const privateContent = element();
  const submit = element(); submit.textContent = 'Log out';
  const cancel = element();
  const logout = element();
  const form = element();
  form.setAttribute('data-resident-logout-form', '');
  form.checkValidity = () => true;
  form.querySelectorAll = selector => selector === 'button[type="submit"]' ? [submit] : [submit, cancel];
  form.querySelector = () => submit;
  const dialog = element();
  dialog.showModal = () => { dialog.open = true; };
  dialog.close = () => { dialog.open = false; };
  dialog.querySelector = () => form.hasAttribute('data-submitting') ? form : null;
  fields.set('resident-logout-dialog', dialog);
  const menuButton = element();
  const theme = element();
  fields.set('site-nav', element());
  const bodyClasses = new Set();
  const documentListeners = {};
  const listeners = new Map();
  const saved = new Map([['smartbrgy_session', 'private draft']]);
  const context = vm.createContext({
    console, resetCount: 0, redirects: [], reloads: 0,
    resetPortalSession() { context.resetCount++; },
    fetch: async () => ({ ok: true, json: async () => ({ name: 'Verified resident', address: 'Official address', email: 'resident@example.test', dob: '1990-01-01' }) }),
    document: {
      body: { classList: { add: name => bodyClasses.add(name), contains: name => bodyClasses.has(name), toggle(name) { if (bodyClasses.has(name)) { bodyClasses.delete(name); return false; } bodyClasses.add(name); return true; } } },
      getElementById: id => fields.get(id),
      addEventListener: (name, handler) => { documentListeners[name] = handler; },
      querySelector: selector => ({ '[data-resident-logout]': logout, '[data-resident-cancel]': cancel, '[data-menu-toggle]': menuButton, '[data-resident-theme]': theme })[selector] ?? null,
      querySelectorAll: selector => selector === '[data-resident-form]' ? [form] : selector === '[data-resident-private]' ? [privateContent] : [...fields.values()],
    },
    window: {
      RESIDENT_PORTAL: { authenticated: true, loginUrl: '/portal/login', identityUrl: '/portal/identity' },
      addEventListener: (name, handler) => listeners.set(name, handler),
      location: { assign: url => context.redirects.push(url), reload: () => context.reloads++ },
    },
    sessionStorage: { getItem: key => saved.get(key), setItem: (key, value) => saved.set(key, value), removeItem: key => saved.delete(key) },
  });
  vm.runInContext(readFileSync(new URL('../../public/js/resident-account.js', import.meta.url), 'utf8'), context);
  return { context, fields, privateContent, submit, cancel, logout, form, dialog, listeners, saved, menuButton, theme, bodyClasses, documentListeners };
}

test('resident identity comes from the authenticated endpoint and is read only', async () => {
  const { context, fields } = residentClient();
  assert.equal(await context.ensurePortalIdentity(), true);
  assert.equal(fields.get('f-name').value, 'Verified resident');
  for (const id of ['f-name', 'f-address', 'f-email', 'f-dob']) assert.equal(fields.get(id).readOnly, true);
});

test('guests are directed to login before requesting protected identity', async () => {
  const { context } = residentClient();
  context.window.RESIDENT_PORTAL.authenticated = false;
  context.fetch = () => { throw new Error('Should not fetch'); };
  assert.equal(await context.ensurePortalIdentity(), false);
  assert.deepEqual(context.redirects, ['/portal/login']);
});

test('denied resident eligibility shows assistance and does not populate identity', async () => {
  const { context, fields } = residentClient();
  context.fetch = async () => ({ ok: false, status: 403, json: async () => ({ message: 'Visit the barangay for assistance.' }) });
  assert.equal(await context.ensurePortalIdentity(), false);
  assert.equal(fields.get('portal-form-error').hidden, false);
  assert.equal(fields.get('portal-form-error').textContent, 'Visit the barangay for assistance.');
});

test('leaving a page removes private content and drafts even when storage is blocked', () => {
  const { context, fields, privateContent, listeners } = residentClient();
  context.sessionStorage.removeItem = () => { throw new Error('Storage blocked'); };
  assert.doesNotThrow(() => listeners.get('pagehide')());
  assert.equal(privateContent.cleared, true);
  assert.equal(fields.get('f-name').value, '');
  assert.equal(context.resetCount, 1);
});

test('back-forward cache restoration scrubs protected content and reloads authorization', () => {
  const { context, privateContent, saved, listeners } = residentClient();
  listeners.get('pageshow')({ persisted: true });
  assert.equal(privateContent.cleared, true);
  assert.equal(saved.has('smartbrgy_session'), false);
  assert.equal(context.reloads, 1);
});

test('an identity response arriving after logout cannot restore resident information', async () => {
  const { context, fields } = residentClient();
  let resolve;
  context.fetch = () => new Promise(done => { resolve = done; });
  const pending = context.ensurePortalIdentity();
  context.clearResidentClientState();
  resolve({ ok: true, json: async () => ({ name: 'Previous resident' }) });
  assert.equal(await pending, false);
  assert.equal(fields.get('f-name').value, '');
});

test('logout confirmation supports cancel and prevents duplicate submissions', () => {
  const { context, logout, cancel, dialog, form, submit, privateContent } = residentClient();
  logout.listeners.click();
  assert.equal(dialog.open, true);
  cancel.listeners.click();
  assert.equal(dialog.open, false);
  assert.equal(context.resetCount, 0);
  let prevented = 0;
  form.listeners.submit({ preventDefault() { prevented++; } });
  assert.equal(submit.disabled, true);
  assert.equal(privateContent.cleared, true);
  form.listeners.submit({ preventDefault() { prevented++; } });
  assert.equal(prevented, 1);
  dialog.listeners.cancel({ preventDefault() { prevented++; } });
  assert.equal(prevented, 2);
});

test('the mobile menu opens from its button and closes with Escape', () => {
  const { menuButton, fields, bodyClasses, documentListeners } = residentClient();
  const navigation = fields.get('site-nav');
  assert.equal(bodyClasses.has('nav-collapsible'), true);
  menuButton.listeners.click();
  assert.equal(menuButton.getAttribute('aria-expanded'), 'true');
  assert.equal(navigation.classList.contains('is-open'), true);
  documentListeners.keydown({ key: 'Escape' });
  assert.equal(menuButton.getAttribute('aria-expanded'), 'false');
  assert.equal(navigation.classList.contains('is-open'), false);
  assert.equal(menuButton.focused, true);
});

test('the theme button reports its state and remembers the choice', () => {
  const { theme, saved } = residentClient();
  assert.equal(theme.getAttribute('aria-pressed'), 'false');
  theme.listeners.click();
  assert.equal(theme.getAttribute('aria-pressed'), 'true');
  assert.equal(saved.get('smartbrgy_portal_theme'), 'dark');
});
