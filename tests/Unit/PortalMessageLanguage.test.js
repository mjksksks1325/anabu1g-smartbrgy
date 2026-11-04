import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

function localization(locale) {
  const catalogs = {
    en: { 'The :attribute failed to upload.': 'The :attribute failed to upload.', photo: 'photo' },
    fil: { 'The :attribute failed to upload.': 'Hindi na-upload ang :attribute.', photo: 'larawan' },
  };
  const context = vm.createContext({
    window: { PORTAL_I18N: { locale, catalogs } },
    document: { addEventListener() {}, querySelectorAll: () => [] },
  });
  vm.runInContext(readFileSync(new URL('../../public/js/portal-localization.js', import.meta.url), 'utf8'), context);
  return context;
}

function shown(context, serverMessage) {
  const element = { textContent: '', attributes: {}, setAttribute(name, value) { this.attributes[name] = value; } };
  context.portalSetText(element, serverMessage);
  return element.textContent;
}

test('a Filipino server error is shown fully in English on an English page', () => {
  assert.equal(shown(localization('en'), 'Hindi na-upload ang larawan.'), 'The photo failed to upload.');
});

test('an English server error is shown fully in Filipino on a Filipino page', () => {
  assert.equal(shown(localization('fil'), 'The photo failed to upload.'), 'Hindi na-upload ang larawan.');
});
