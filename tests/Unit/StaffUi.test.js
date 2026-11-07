import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const script = readFileSync(new URL('../../public/js/staff-ui.js', import.meta.url), 'utf8');

class FakeDialog {
  constructor() { this.open = false; this.attributes = {}; this.focusedField = null; }
  hasAttribute(name) { return name in this.attributes; }
  setAttribute(name, value) { this.attributes[name] = value; }
  showModal() { this.open = true; }
  querySelector() { return { focus: () => { this.focusedField = 'first invalid field'; } }; }
}

function load({ wrappers, openOnLoad = null }) {
  const listeners = {};
  const context = vm.createContext({
    HTMLDialogElement: FakeDialog,
    window: {},
    document: {
      readyState: 'complete',
      addEventListener: (type, handler) => { (listeners[type] ??= []).push(handler); },
      querySelectorAll: () => wrappers,
      querySelector: selector => (selector === 'ui-modal[data-open-on-load]' ? openOnLoad : null),
    },
  });
  vm.runInContext(script, context);

  return { window: context.window, listeners };
}

const wrapper = (labelledby) => {
  const dialog = new FakeDialog();
  return { dialog, dataset: { labelledby }, querySelector: () => dialog };
};

test('names each Flux dialog by its heading and reopens the one marked for reopening', () => {
  const failedReview = wrapper('application-7-title');
  const other = wrapper('application-8-title');
  load({ wrappers: [failedReview, other], openOnLoad: failedReview });

  assert.equal(failedReview.dialog.attributes['aria-labelledby'], 'application-7-title');
  assert.equal(other.dialog.attributes['aria-labelledby'], 'application-8-title');
  assert.equal(failedReview.dialog.open, true);
  assert.equal(failedReview.dialog.focusedField, 'first invalid field');
  assert.equal(other.dialog.open, false);
});

test('returns focus to the control that opened a Flux dialog when it closes', () => {
  const { listeners } = load({ wrappers: [] });
  let focused = false;
  const button = { isConnected: true, focus() { focused = true; } };
  const trigger = { querySelector: () => button };

  listeners.click[0]({ target: { closest: selector => (selector === '[data-flux-modal-trigger]' ? trigger : null) } });
  const dialog = new FakeDialog();
  dialog.closest = () => ({});
  listeners.close[0]({ target: dialog });

  assert.equal(focused, true);
});

function openedHarness({ openDialogs = [], active = null }) {
  const listeners = {};
  let frame = null;
  const document = {
    readyState: 'complete',
    activeElement: active,
    addEventListener: (type, handler) => { (listeners[type] ??= []).push(handler); },
    querySelectorAll: (selector) => (selector === 'ui-modal > dialog[open]' ? openDialogs : []),
    querySelector: () => null,
  };
  const context = vm.createContext({ HTMLDialogElement: FakeDialog, window: {}, document, requestAnimationFrame: (callback) => { frame = callback; } });
  vm.runInContext(script, context);

  return { StaffUi: context.window.StaffUi, document, runFrame: () => frame?.() };
}

function dialogWith({ initialFocus = null, contains = () => false } = {}) {
  const dialog = new FakeDialog();
  dialog.open = true;
  dialog.removeAttribute = (name) => { delete dialog.attributes[name]; };
  dialog.contains = contains;
  dialog.querySelector = (selector) => (selector.includes('data-ui-initial-focus') ? initialFocus : null);
  dialog.focus = () => { dialog.focused = true; };
  return dialog;
}

test('a dialog that opens over another one is marked stacked and focuses its safe action', () => {
  const panel = dialogWith();
  let cancelFocused = false;
  const confirmation = dialogWith({ initialFocus: { focus() { cancelFocused = true; } } });
  const { StaffUi, runFrame } = openedHarness({ openDialogs: [panel, confirmation] });

  StaffUi.opened(confirmation);
  runFrame();

  assert.ok('data-ui-stacked' in confirmation.attributes);
  assert.equal(cancelFocused, true);
});

test('a dialog without a marked control takes focus itself once Flux has dropped its focus placeholder', () => {
  const placeholder = { matches: (selector) => selector === '[data-flux-focus-placeholder]' };
  const panel = dialogWith({ contains: (node) => node === placeholder });
  const { StaffUi, runFrame } = openedHarness({ openDialogs: [panel], active: placeholder });

  StaffUi.opened(panel);
  runFrame();

  assert.equal(panel.attributes.tabindex, '-1');
  assert.equal(panel.focused, true);
  assert.equal('data-ui-stacked' in panel.attributes, false);
});

test('focus that a script already placed inside the dialog is left alone, and closing clears the stacked mark', () => {
  const field = { matches: () => false };
  const dialog = dialogWith({ contains: (node) => node === field });
  const { StaffUi, runFrame } = openedHarness({ openDialogs: [dialogWith(), dialog], active: field });

  StaffUi.opened(dialog);
  runFrame();
  assert.equal(dialog.focused, undefined);

  dialog.open = false;
  StaffUi.opened(dialog);
  assert.equal('data-ui-stacked' in dialog.attributes, false);
});
