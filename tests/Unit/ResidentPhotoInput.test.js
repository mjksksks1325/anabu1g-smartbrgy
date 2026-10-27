import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

function createPhotoInput() {
  let changeHandler;
  const message = { textContent: '', setAttribute() {} };
  const error = {
    hidden: true,
    querySelector: () => message,
  };
  class HTMLInputElement {
    constructor() {
      this.dataset = { errorTarget: 'photo-error' };
      this.files = [];
      this.value = 'selected-file';
      this.attributes = {};
    }

    matches(selector) {
      return selector === '[data-resident-image-only]';
    }

    setAttribute(name, value) {
      this.attributes[name] = value;
    }

    removeAttribute(name) {
      delete this.attributes[name];
    }
  }

  const context = vm.createContext({
    HTMLInputElement,
    document: {
      addEventListener: (name, handler) => {
        if (name === 'change') {
          changeHandler = handler;
        }
      },
      getElementById: () => error,
    },
  });

  vm.runInContext(readFileSync(new URL('../../public/js/resident-photo-input.js', import.meta.url), 'utf8'), context);

  return {
    error,
    input: new HTMLInputElement(),
    message,
    select(file) {
      const input = this.input;
      input.files = [file];
      changeHandler({ target: input });
    },
  };
}

test('resident photo picker immediately rejects PDF files and clears the selection', () => {
  const picker = createPhotoInput();

  picker.select({ name: 'proof.pdf', size: 1000, type: 'application/pdf' });

  assert.equal(picker.input.value, '');
  assert.equal(picker.input.attributes['aria-invalid'], 'true');
  assert.equal(picker.error.hidden, false);
  assert.match(picker.message.textContent, /Documents are not accepted/);
});

test('resident photo picker accepts a supported image under the size limit', () => {
  const picker = createPhotoInput();

  picker.select({ name: 'resident.jpg', size: 1000, type: 'image/jpeg' });

  assert.equal(picker.input.value, 'selected-file');
  assert.equal(picker.input.attributes['aria-invalid'], undefined);
  assert.equal(picker.error.hidden, true);
});

test('resident photo picker rejects files larger than 5 MB', () => {
  const picker = createPhotoInput();

  picker.select({ name: 'resident.png', size: 5 * 1024 * 1024 + 1, type: 'image/png' });

  assert.equal(picker.input.value, '');
  assert.equal(picker.error.hidden, false);
});
