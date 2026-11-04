import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const script = readFileSync(new URL('../../public/js/rfid-file-tracking.js', import.meta.url), 'utf8');

function iotPage(fetchImpl) {
  const listeners = {};
  const attributes = {};
  const regionReplacements = [];
  const count = { textContent: '34 recorded events' };
  const section = {
    setAttribute: (name, value) => { attributes[name] = value; },
    querySelector: selector => (selector === '[data-filter-count]' ? count : { replaceWith: node => regionReplacements.push(node) }),
  };
  // A field named "action" shadows form.action, as in the RFID filter form.
  const form = {
    action: { tagName: 'SELECT' },
    getAttribute: name => (name === 'action' ? '/staff/rfid-file-tracking' : null),
    closest: selector => (selector === '[data-filter-results]' ? section : form),
    entries: [['search', ' Blotter '], ['action', 'returned'], ['date', '']],
  };
  const replaced = [];
  const context = vm.createContext({
    URL,
    FormData: class { constructor(target) { this.target = target; } forEach(callback) { this.target.entries.forEach(([key, value]) => callback(value, key)); } },
    DOMParser: class { parseFromString() { return { querySelector: selector => (selector === '[data-filter-region]' ? { region: 'new' } : selector === '[data-filter-count]' ? { textContent: '8 recorded events' } : null) }; } },
    fetch: fetchImpl,
    window: { location: { href: 'http://localhost/staff/rfid-file-tracking' }, history: { state: null, replaceState: (state, title, url) => replaced.push(url) }, setTimeout: callback => { callback(); return 1; }, clearTimeout() {} },
    document: { addEventListener: (type, handler) => { listeners[type] = handler; }, getElementById: () => null, querySelector: () => null },
  });
  vm.runInContext(script, context);

  return { listeners, form, section, attributes, count, regionReplacements, replaced };
}

test('filters load in place from the form URL even when a field is named "action"', async () => {
  const requested = [];
  const page = iotPage(async url => { requested.push(url); return { ok: true, text: async () => '<html></html>' }; });
  let prevented = false;

  page.listeners.submit({ target: { closest: () => page.form }, preventDefault: () => { prevented = true; } });
  await new Promise(resolve => setImmediate(resolve));

  assert.equal(prevented, true);
  assert.equal(requested[0], 'http://localhost/staff/rfid-file-tracking?search=Blotter&action=returned');
  assert.equal(page.regionReplacements.length, 1);
  assert.equal(page.count.textContent, '8 recorded events');
  assert.deepEqual(page.replaced, [requested[0]]);
  assert.equal(page.attributes['aria-busy'], 'false');
});
