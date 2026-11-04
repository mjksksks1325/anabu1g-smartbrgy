import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

test('the error summary links each message to its field without taking focus from it', () => {
  const field = { id: 'resident-email', focused: false, scrolled: false, focus() { this.focused = true; }, scrollIntoView() { this.scrolled = true; } };
  const link = { dataset: { errorField: 'email' }, href: '#resident-main', listeners: {}, setAttribute(name, value) { this[name] = value; }, addEventListener(name, handler) { this.listeners[name] = handler; } };
  const summary = { focused: false, focus() { this.focused = true; }, querySelectorAll: () => [link] };
  const context = vm.createContext({
    CSS: { escape: value => value },
    document: { querySelector: selector => selector === '[data-error-summary]' ? summary : selector.includes('[name="email"]') ? field : null },
  });

  vm.runInContext(readFileSync(new URL('../../public/js/portal-errors.js', import.meta.url), 'utf8'), context);

  assert.equal(summary.focused, false);
  assert.equal(link.href, '#resident-email');
  let prevented = false;
  link.listeners.click({ preventDefault() { prevented = true; } });
  assert.equal(prevented, true);
  assert.equal(field.focused, true);
});
