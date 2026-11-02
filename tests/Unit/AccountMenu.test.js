import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

function accountMenu({ settings = false, mobile = false } = {}) {
  const listeners = new Map();
  const on = (target) => (name, handler) => { listeners.set(`${target}:${name}`, handler); };
  const context = { activeElement: null };
  const focusable = name => ({ name, focus() { context.activeElement = this; }, addEventListener: on(name) });
  const items = [focusable('account'), focusable('profile'), focusable('password'), focusable('logout')];
  const button = { attributes: { 'aria-controls': 'account-menu-list', 'aria-expanded': 'false' }, getAttribute(n) { return this.attributes[n]; }, setAttribute(n, v) { this.attributes[n] = String(v); }, focus() { context.activeElement = this; }, addEventListener: on('button') };
  const list = { hidden: true, querySelectorAll: selector => selector === '[data-resident-logout]' ? [items[3]] : items, addEventListener: on('list') };
  const menu = { hasAttribute: name => settings && name === 'data-settings-menu', querySelector: () => button, contains: node => node === button || items.includes(node) || node === list, addEventListener: on('menu') };
  const document = { querySelectorAll: () => [menu], getElementById: () => list, addEventListener: on('document'), get activeElement() { return context.activeElement; } };
  vm.runInContext(readFileSync(new URL('../../public/js/account-menu.js', import.meta.url), 'utf8'), vm.createContext({ document, window: { matchMedia: () => ({ matches: mobile, addEventListener() {} }) } }));
  return { listeners, button, list, items, context };
}

test('the account menu opens on click and closes on a click elsewhere', () => {
  const { listeners, button, list } = accountMenu();
  listeners.get('button:click')();
  assert.equal(button.getAttribute('aria-expanded'), 'true');
  assert.equal(list.hidden, false);
  listeners.get('document:click')({ target: {} });
  assert.equal(button.getAttribute('aria-expanded'), 'false');
  assert.equal(list.hidden, true);
});

test('the account menu supports arrow keys and returns focus on Escape', () => {
  const { listeners, button, list, items, context } = accountMenu();
  listeners.get('button:keydown')({ key: 'ArrowDown', preventDefault() {} });
  assert.equal(list.hidden, false);
  assert.equal(context.activeElement, items[0]);
  listeners.get('list:keydown')({ key: 'ArrowUp', preventDefault() {} });
  assert.equal(context.activeElement, items[3]);
  listeners.get('document:keydown')({ key: 'Escape' });
  assert.equal(list.hidden, true);
  assert.equal(context.activeElement, button);
});

test('the language and display settings stay visible inside the mobile menu', () => {
  const { listeners, button, list } = accountMenu({ settings: true, mobile: true });
  assert.equal(list.hidden, false);
  listeners.get('document:click')({ target: {} });
  assert.equal(button.getAttribute('aria-expanded'), 'false');
  assert.equal(list.hidden, false);
});

test('the language and display settings open as a dropdown on wider screens', () => {
  const { listeners, button, list } = accountMenu({ settings: true, mobile: false });
  assert.equal(list.hidden, true);
  listeners.get('button:click')();
  assert.equal(button.getAttribute('aria-expanded'), 'true');
  assert.equal(list.hidden, false);
});
