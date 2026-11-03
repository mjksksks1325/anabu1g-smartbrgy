import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const read = path => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const english = JSON.parse(read('lang/en.json'));
const filipino = JSON.parse(read('lang/fil.json'));

function sentenceLiterals(source) {
  return [...source.matchAll(/'([A-Z][^'\\$<>{}]{8,}[.!?])'/g)].map(match => match[1]);
}

/** A message translates when it is a key of both catalogs, or the English text of a Filipino-keyed entry. */
function isCatalogued(message) {
  return (Object.hasOwn(english, message) && Object.hasOwn(filipino, message))
    || Object.entries(english).some(([key, text]) => text === message && Object.hasOwn(filipino, key));
}

test('every staff workspace message has an English and a Filipino catalog entry', () => {
  for (const script of ['public/js/admin.js', 'public/js/household-profiling.js']) {
    const missing = sentenceLiterals(read(script)).filter(message => !isCatalogued(message));

    assert.deepEqual([...new Set(missing)], [], `${script} has messages that stay untranslated`);
  }
});

test('the shared pager phrases translate with their numbers, even when one placeholder name starts another', () => {
  const window = { ADMIN_I18N: { locale: 'fil', catalogs: { en: english, fil: filipino } } };
  vm.runInContext(read('public/js/admin-localization.js'), vm.createContext({ window, document: { addEventListener() {} } }));

  assert.equal(window.adminTranslate('Showing :from–:to of :total', { from: 1, to: 15, total: 40 }), 'Ipinapakita ang 1–15 sa 40');
  assert.equal(window.adminTranslate('Page :current of :last', { current: 2, last: 3 }), 'Pahina 2 ng 3');
  window.ADMIN_I18N.locale = 'en';
  assert.equal(window.adminTranslate('Showing :from–:to of :total', { from: 1, to: 2, total: 2 }), 'Showing 1–2 of 2');
});

test('a label that a script changes after translation is translated again instead of being restored', () => {
  const handlers = {};
  const attributes = new Map([['aria-label', 'Notifications']]);
  const chip = {
    matches: () => false,
    hasAttribute: name => attributes.has(name),
    getAttribute: name => attributes.get(name) ?? null,
    setAttribute: (name, value) => attributes.set(name, value),
  };
  const document = {
    body: { hasAttribute: () => true },
    nodeType: 9,
    addEventListener: (type, handler) => { handlers[type] = handler; },
    createTreeWalker: () => ({ nextNode: () => null }),
    querySelectorAll: () => [chip],
  };
  const window = { ADMIN_I18N: { locale: 'fil', catalogs: { en: english, fil: filipino } } };
  vm.runInContext(read('public/js/admin-localization.js'), vm.createContext({ window, document, Node: { ELEMENT_NODE: 1, TEXT_NODE: 3 }, NodeFilter: { SHOW_TEXT: 4 } }));

  handlers['admin:locale-changed']();
  assert.equal(attributes.get('aria-label'), 'Mga abiso');

  attributes.set('aria-label', 'Notifications, 3 unread');
  handlers['admin:locale-changed']();
  assert.equal(attributes.get('aria-label'), 'Mga abiso, 3 ang hindi pa nababasa');
});
