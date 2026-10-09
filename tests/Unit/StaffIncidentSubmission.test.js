import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const script = readFileSync(new URL('../../public/js/staff-incident-submission.js', import.meta.url), 'utf8');

function form(value = '') {
  const select = { value, attributes: {}, setAttribute(key, val) { this.attributes[key] = val; }, addEventListener(type, handler) { this.change = handler; } };
  const field = { hidden: true };
  const input = { value: 'Traffic obstruction', focus() { this.focused = true; } };
  vm.runInNewContext(script, { document: { querySelector: selector => ({ '[data-incident-type-select]': select, '[data-custom-incident-type]': field, '[data-custom-incident-input]': input })[selector] } });
  return { select, field, input };
}

test('Iba pa reveals a required textbox and changing back disables it without erasing the draft', () => {
  const { select, field, input } = form('Theft');
  assert.equal(field.hidden, true);
  assert.equal(input.disabled, true);
  assert.equal(input.required, false);
  select.value = 'Iba pa';
  select.change();
  assert.equal(field.hidden, false);
  assert.equal(input.disabled, false);
  assert.equal(input.required, true);
  assert.equal(input.focused, true);
  select.value = 'Theft';
  select.change();
  assert.equal(field.hidden, true);
  assert.equal(input.disabled, true);
  assert.equal(input.value, 'Traffic obstruction');
});

test('restored Iba pa selection shows the existing custom value on initialization', () => {
  const { field, input } = form('Iba pa');
  assert.equal(field.hidden, false);
  assert.equal(input.required, true);
  assert.equal(input.value, 'Traffic obstruction');
});

test('a repeated submit is prevented without disabling report inputs', () => {
  const button = { disabled: false, textContent: 'Submit report' };
  const form = { dataset: {}, querySelector: () => button, addEventListener(type, handler) { this.submit = handler; } };
  vm.runInNewContext(script, { document: { querySelector: selector => selector === '[data-incident-submission-form]' ? form : null } });
  let prevented = false;
  form.submit({ preventDefault() { prevented = true; } });
  assert.equal(prevented, false);
  assert.equal(button.disabled, true);
  assert.equal(button.textContent, 'Submitting report...');
  form.submit({ preventDefault() { prevented = true; } });
  assert.equal(prevented, true);
});
