import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

function createField({ id, name = '', label = '', value = '' }) {
  const classes = new Set();
  const attributes = {};
  const field = {
    id, name, value, type: 'text', required: false, disabled: false, offsetWidth: 100, offsetHeight: 20,
    scrolled: false, focused: false,
    classList: { add: (...names) => names.forEach(n => classes.add(n)), remove: (...names) => names.forEach(n => classes.delete(n)), contains: n => classes.has(n) },
    getClientRects: () => [{}],
    getAttribute: key => attributes[key] ?? null,
    setAttribute: (key, val) => { attributes[key] = String(val); },
    removeAttribute: key => { delete attributes[key]; },
    matches: () => true,
    closest: selector => (selector.includes('.form-group') ? { querySelector: () => ({ textContent: label }) } : null),
    insertAdjacentElement: () => {},
    scrollIntoView() { field.scrolled = true; },
    focus() { field.focused = true; },
  };

  return field;
}

function load(fields, fetchResponse) {
  const listeners = {};
  const elements = {};
  const modal = {
    querySelector: selector => fields.find(field => selector === `[name="${field.name}"]` || selector === `#${field.id}`) ?? null,
    querySelectorAll: () => fields,
  };
  const document = {
    addEventListener: (type, handler) => { (listeners[type] ??= []).push(handler); },
    querySelectorAll: selector => (selector.includes('.modal-overlay.show') ? [modal] : []),
    getElementById: id => elements[id] ?? null,
    createElement: () => {
      const element = { textContent: '', className: '', setAttribute() {}, remove() { delete elements[element.id]; } };
      Object.defineProperty(element, 'classList', { get: () => ({ contains: name => element.className.split(' ').includes(name) }) });
      Object.defineProperty(element, 'id', { set(value) { elements[value] = element; this._id = value; }, get() { return this._id; } });
      return element;
    },
  };
  const window = { fetch: async () => fetchResponse, matchMedia: () => ({ matches: false }) };
  const context = vm.createContext({ window, document, CSS: { escape: value => value }, requestAnimationFrame: callback => callback(), console });
  vm.runInContext(readFileSync('public/js/form-feedback.js', 'utf8'), context);

  return { window, listeners, elements };
}

test('validation errors from the server outline matching and empty required fields and bring the first into view', async () => {
  const lastName = createField({ id: 'res-lastname', label: 'Last Name *' });
  const contact = createField({ id: 'res-contact', label: 'Contact Number', value: 'abc' });
  const dob = createField({ id: 'res-dob', label: 'Date of Birth *' });
  const response = {
    status: 422,
    headers: { get: () => 'application/json' },
    clone: () => ({ json: async () => ({ errors: { last_name: ['The last name field is required.'], contact_number: ['The contact number format is invalid.'] } }) }),
  };
  const { window, elements } = load([lastName, contact, dob], response);

  assert.equal(await window.fetch('/staff/residents'), response);
  await new Promise(resolve => setTimeout(resolve));

  assert.equal(lastName.getAttribute('aria-invalid'), 'true');
  assert.equal(lastName.classList.contains('field-invalid'), true);
  assert.equal(lastName.classList.contains('field-shake'), true);
  assert.equal(dob.classList.contains('field-shake'), true);
  assert.equal(lastName.scrolled && lastName.focused, true);
  assert.equal(dob.scrolled, false);
  assert.equal(contact.classList.contains('field-invalid'), true);
  assert.equal(elements['res-lastname-feedback'].textContent, 'The last name field is required.');
  assert.equal(elements['res-contact-feedback'].textContent, 'The contact number format is invalid.');
});

test('a failed client-side check flags only empty required fields and editing clears the error', () => {
  const name = createField({ id: 'purok-name', label: 'Purok ID / Key *' });
  const label = createField({ id: 'purok-label', label: 'Display Name *', value: 'Purok 1' });
  const color = createField({ id: 'purok-color', label: 'Color' });
  const { window, listeners } = load([name, label, color], null);

  window.FormFeedback.flagMissing();

  assert.equal(name.classList.contains('field-invalid'), true);
  assert.equal(name.focused, true);
  assert.equal(label.classList.contains('field-invalid'), false);
  assert.equal(color.classList.contains('field-invalid'), false);

  listeners.input.forEach(handler => handler({ target: name }));
  assert.equal(name.classList.contains('field-invalid'), false);
  assert.equal(name.getAttribute('aria-invalid'), null);
});

test('an error notification is skipped when the message can be shown on the empty required field', () => {
  const key = createField({ id: 'purok-name', label: 'Purok ID / Key *' });
  const label = createField({ id: 'purok-label', label: 'Display Name *', value: 'Purok 1' });
  const { window, elements } = load([key, label], null);

  assert.equal(window.FormFeedback.handledOnFields('Enter a purok ID / key.'), true);
  assert.equal(elements['purok-name-feedback'].textContent, 'Enter a purok ID / key.');
  assert.equal(key.focused, true);
});

test('an unrelated error notification still appears when every required field is filled', () => {
  const label = createField({ id: 'purok-label', label: 'Display Name *', value: 'Purok 1' });
  const { window } = load([label], null);

  assert.equal(window.FormFeedback.handledOnFields('Network error.'), false);
  assert.equal(label.classList.contains('field-invalid'), false);
});
