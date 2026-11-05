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
