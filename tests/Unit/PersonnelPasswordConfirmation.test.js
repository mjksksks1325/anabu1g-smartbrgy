import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';
const script = readFileSync(new URL('../../public/js/personnel-password-confirmation.js', import.meta.url), 'utf8');

function popup() {
  const events = new Map(), windowEvents = new Map(), dialogEvents = new Map(), calls = [], destinations = [], fullPageDestinations = [];
  const error = { hidden: true, textContent: '' }, submit = { disabled: false };
  const password = { value: 'password', focus() {} };
  const cancel = { addEventListener(type, handler) { this.click = handler; } };
  const link = { href: 'http://localhost/staff/settings/security', focus() { this.focused = true; } };
  const form = { action: 'http://localhost/staff/settings/confirm-password', elements: { password },
    reset() { password.value = ''; }, querySelector: () => submit, closest: () => dialog };
  const dialog = { open: false, isConnected: true, dataset: { securityUrl: link.href, statusUrl: '/user/confirmed-password-status' },
    querySelector: selector => selector === 'form' ? form : selector === '[role="alert"]' ? error : selector === '[data-confirm-cancel]' ? cancel : submit,
    showModal() { this.open = true; }, close() { this.open = false; dialogEvents.get('close')(); },
    addEventListener: (type, handler) => dialogEvents.set(type, handler),
  };
  const context = vm.createContext({ URL, FormData: class { constructor(value) { this.form = value; } },
    window: { addEventListener(type, handler, options) { windowEvents.set(type, { handler, options }); }, location: { href: 'http://localhost/staff', assign: url => fullPageDestinations.push(url) }, Livewire: { navigate: url => destinations.push(url) } },
    document: { getElementById: () => dialog, addEventListener: (type, handler) => events.set(type, handler) },
    fetch: async (url, options) => { calls.push({ url, options }); return { ok: true, json: async () => ({ confirmed: false }) }; },
  });
  vm.runInContext(script, context);
  events.get('DOMContentLoaded')();
  const click = { target: { closest: () => link }, button: 0, preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; } };
  const send = { target: form, preventDefault() {} };
  return { context, events, windowEvents, dialogEvents, dialog, form, password, error, submit, cancel, link, click, send, destinations, fullPageDestinations, calls };
}

test('security opens a popup, cancel restores focus without navigating', async () => {
  const ui = popup();
  await ui.windowEvents.get('click').handler(ui.click);
  assert.equal(ui.dialog.open, true);
  assert.equal(ui.windowEvents.get('click').options, true);
  assert.equal(ui.click.stopped, true);
  ui.cancel.click();
  assert.equal(ui.dialog.open, false);
  assert.equal(ui.link.focused, true);
  assert.equal(ui.password.value, '');
  assert.deepEqual(ui.destinations, []);
});

test('invalid passwords keep the popup open and display the server validation message', async () => {
  const ui = popup();
  await ui.windowEvents.get('click').handler(ui.click);
  ui.context.fetch = async () => ({ ok: false, status: 422, json: async () => ({ errors: { password: ['Incorrect password.'] } }) });
  await ui.events.get('submit')(ui.send);
  assert.equal(ui.dialog.open, true);
  assert.equal(ui.error.textContent, 'Incorrect password.');
  assert.equal(ui.error.hidden, false);
  assert.equal(ui.submit.disabled, false);
  assert.deepEqual(ui.destinations, []);
});

test('successful confirmation continues only the chosen account security destination', async () => {
  const ui = popup();
  await ui.windowEvents.get('click').handler(ui.click);
  ui.context.fetch = async () => ({ ok: true, status: 201 });
  await ui.events.get('submit')(ui.send);
  assert.equal(ui.dialog.open, false);
  assert.deepEqual(ui.destinations, []);
  assert.deepEqual(ui.fullPageDestinations, [ui.link.href]);
});

test('account security still asks for confirmation when the session is already confirmed', async () => {
  const ui = popup();
  ui.context.fetch = async () => ({ ok: true, json: async () => ({ confirmed: true }) });
  await ui.windowEvents.get('click').handler(ui.click);
  assert.equal(ui.dialog.open, true);
  assert.deepEqual(ui.destinations, []);
  assert.deepEqual(ui.fullPageDestinations, []);
  assert.equal(ui.calls.length, 0);
});

test('cancelling a pending confirmation prevents late navigation', async () => {
  const ui = popup();
  await ui.windowEvents.get('click').handler(ui.click);
  let finish;
  ui.context.fetch = () => new Promise(resolve => { finish = resolve; });
  const pending = ui.events.get('submit')(ui.send);
  ui.cancel.click();
  finish({ ok: true });
  await pending;
  assert.deepEqual(ui.destinations, []);
});

test('other destinations and modified clicks retain their existing navigation', async () => {
  const ui = popup();
  ui.link.href = 'http://localhost/admin/settings';
  await ui.windowEvents.get('click').handler(ui.click);
  assert.equal(ui.click.prevented, undefined);
  assert.equal(ui.calls.length, 0);
});


for (const [status, message] of [[419, 'Your session expired'], [429, 'Too many attempts']]) {
  test(`confirmation failure ${status} remains inside the popup`, async () => {
    const ui = popup();
    await ui.windowEvents.get('click').handler(ui.click);
    ui.context.fetch = async () => ({ ok: false, status, json: async () => ({}) });
    await ui.events.get('submit')(ui.send);
    assert.equal(ui.dialog.open, true);
    assert.equal(ui.error.textContent.startsWith(message), true);
    assert.deepEqual(ui.destinations, []);
  });
}

test('Escape clears the password and restores the Account Security link focus', async () => {
  const ui = popup();
  await ui.windowEvents.get('click').handler(ui.click);
  ui.password.value = 'typed password';
  ui.dialogEvents.get('cancel')();
  ui.dialog.close();
  assert.equal(ui.password.value, '');
  assert.equal(ui.link.focused, true);
  assert.deepEqual(ui.destinations, []);
});

test('an unexpected successful HTML response cannot be treated as password confirmation', async () => {
  const ui = popup();
  await ui.windowEvents.get('click').handler(ui.click);
  ui.context.fetch = async () => ({ ok: true, status: 200, json: async () => { throw new Error('HTML response'); } });
  await ui.events.get('submit')(ui.send);
  assert.equal(ui.dialog.open, true);
  assert.equal(ui.error.hidden, false);
  assert.deepEqual(ui.destinations, []);
});
