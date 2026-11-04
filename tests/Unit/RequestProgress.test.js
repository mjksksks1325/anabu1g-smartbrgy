import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

function requestProgress(activeScreen, selectedDocId) {
  const node = (attributes = {}) => {
    const classes = new Set();
    return {
      hidden: false, textContent: '', attributes: { ...attributes },
      classList: { toggle(name, state) { if (state) classes.add(name); else classes.delete(name); }, contains: name => classes.has(name) },
      setAttribute(name, value) { this.attributes[name] = value; },
      removeAttribute(name) { delete this.attributes[name]; },
      getAttribute(name) { return this.attributes[name]; },
    };
  };
  const steps = [1, 2, 3, 4, 5].map(step => Object.assign(node(), { dataset: { step: String(step) }, marker: node() }));
  steps.forEach(step => { step.querySelector = () => step.marker; });
  const aside = Object.assign(node(), { querySelectorAll: () => steps });
  const flow = Object.assign(node(), { querySelector: () => ({ id: activeScreen }) });
  const elements = { 'request-flow': flow, 'request-progress': aside, 'request-summary-document': node(), 'request-summary-fee': node() };
  let observed = null;
  const context = vm.createContext({
    window: {}, document: { getElementById: id => elements[id], addEventListener() {}, querySelectorAll: () => [] },
    MutationObserver: class { constructor(callback) { this.callback = callback; } observe(target, options) { observed = { target, options }; } },
    DOC_TYPES: { BC: { label: 'Barangay Clearance', fee: 'PHP 25.00' } },
    selectedDocId,
  });
  vm.runInContext(readFileSync(new URL('../../public/js/portal-localization.js', import.meta.url), 'utf8'), context);
  vm.runInContext(readFileSync(new URL('../../public/js/request-progress.js', import.meta.url), 'utf8'), context);
  return { aside, steps, elements, observed };
}

test('the step panel marks finished and current steps and shows the chosen document and fee', () => {
  const { aside, steps, elements, observed } = requestProgress('screen-form', 'BC');

  assert.equal(aside.hidden, false);
  assert.deepEqual(steps.map(step => [step.classList.contains('is-done'), step.classList.contains('is-current')]), [[true, false], [true, false], [false, true], [false, false], [false, false]]);
  assert.equal(steps[2].getAttribute('aria-current'), 'step');
  assert.equal(steps[2].marker.hidden, false);
  assert.equal(steps[0].marker.hidden, true);
  assert.equal(elements['request-summary-document'].textContent, 'Barangay Clearance');
  assert.equal(elements['request-summary-fee'].textContent, 'PHP 25.00');
  assert.equal(observed.options.attributeFilter.join(','), 'class');
});

test('the step panel stays empty before a document is chosen and hides after submission', () => {
  const before = requestProgress('screen-doctype', null);
  assert.equal(before.elements['request-summary-document'].textContent, 'Wala pang napili');
  assert.equal(before.elements['request-summary-fee'].textContent, '—');

  const submitted = requestProgress('screen-confirm', 'BC');
  assert.equal(submitted.aside.hidden, true);
});
