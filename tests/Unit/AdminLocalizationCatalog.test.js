import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

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

test('the shared pager phrases translate with their numbers', () => {
  assert.equal(filipino['Showing :from–:to of :total'], 'Ipinapakita ang :from–:to sa :total');
  assert.equal(filipino['Page :current of :last'], 'Pahina :current ng :last');
  assert.equal(english['Page :current of :last'], 'Page :current of :last');
});
