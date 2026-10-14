import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

function visibilityClient() {
  const fields = new Map(), buttons = [], events = new Map(), windowEvents = new Map();
  function add(id, localized = false) {
    const field = { type: 'password', value: 'UnchangedPassword123!' };
    const attributes = new Map(localized ? [['data-portal-i18n-aria-label', 'Show password'], ['data-portal-i18n-title', 'Show password']] : []);
    const handlers = [];
    const button = { dataset: { passwordToggle: id }, handlers,
      hasAttribute: name => attributes.has(name), getAttribute: name => attributes.get(name),
      setAttribute: (name, value) => attributes.set(name, value), addEventListener: (name, handler) => handlers.push(handler),
      click: () => handlers.forEach(handler => handler()), textContent: '',
    };
    fields.set(id, field); buttons.push(button);
    return { field, button };
  }
  const first = add('first', true), second = add('second');
  const context = vm.createContext({ WeakSet, document: {
    readyState: 'interactive', getElementById: id => fields.get(id), querySelectorAll: () => buttons,
    addEventListener: (name, handler) => { if (!events.has(name)) events.set(name, []); events.get(name).push(handler); },
  }, window: { addEventListener: (name, handler) => windowEvents.set(name, handler) },
  portalSetAttribute(button, attribute, key) { button.setAttribute(attribute, key === 'Hide password' ? 'Itago ang password' : 'Ipakita ang password'); },
  sessionStorage: { setItem() { throw new Error('Passwords must not be stored'); } },
  });
  vm.runInContext(readFileSync(new URL('../../public/js/password-visibility.js', import.meta.url), 'utf8'), context);
  return { first, second, add, fields, buttons, fire: (name, target) => events.get(name)?.forEach(handler => handler({ target })), windowEvents };
}

test('each password toggles independently with accessible labels and unchanged values', () => {
  const { first, second } = visibilityClient();
  first.button.click();
  assert.equal(first.field.type, 'text');
  assert.equal(second.field.type, 'password');
  assert.equal(first.field.value, 'UnchangedPassword123!');
  assert.equal(first.button.getAttribute('aria-pressed'), 'true');
  assert.equal(first.button.getAttribute('aria-label'), 'Itago ang password');
  assert.equal(first.button.textContent, '');
  second.button.click();
  assert.equal(second.button.getAttribute('aria-label'), 'Hide password');
  first.button.click();
  assert.equal(first.field.type, 'password');
  assert.equal(first.button.getAttribute('aria-label'), 'Ipakita ang password');
});

test('form reset and dialog close hide only their own passwords', () => {
  const { first, second, fire } = visibilityClient();
  first.button.click(); second.button.click();
  fire('reset', { querySelectorAll: () => [first.button] });
  assert.equal(first.field.type, 'password');
  assert.equal(second.field.type, 'text');
  fire('close', { querySelectorAll: () => [second.button] });
  assert.equal(second.field.type, 'password');
  assert.equal(second.button.getAttribute('aria-pressed'), 'false');
});

test('Livewire navigation initializes new fields without duplicate handlers and masks outgoing fields', () => {
  const { first, add, fire } = visibilityClient();
  fire('DOMContentLoaded'); fire('livewire:navigated');
  assert.equal(first.button.handlers.length, 1);
  const next = add('next');
  fire('livewire:navigated'); next.button.click();
  assert.equal(next.field.type, 'text');
  fire('livewire:navigating');
  assert.equal(next.field.type, 'password');
});

test('leaving the page masks passwords and missing targets are ignored safely', () => {
  const { first, fields, fire, windowEvents } = visibilityClient();
  first.button.click();
  fields.delete('second');
  fire('livewire:navigated');
  windowEvents.get('pagehide')();
  assert.equal(first.field.type, 'password');
  assert.equal(first.field.value, 'UnchangedPassword123!');
});
