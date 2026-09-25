import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

function effects({ reducedMotion = false, observerAvailable = true } = {}) {
  const classes = () => ({ set: new Set(), add(name) { this.set.add(name); }, contains(name) { return this.set.has(name); } });
  const sections = [{ classList: classes() }, { classList: classes() }];
  const root = { classList: classes() };
  const observed = [];
  let notify = null;
  class IntersectionObserver {
    constructor(callback) { notify = callback; }
    observe(element) { observed.push(element); }
    unobserve(element) { observed.splice(observed.indexOf(element), 1); }
  }
  const context = vm.createContext({
    document: { documentElement: root, querySelectorAll: selector => selector === '[data-reveal]' ? sections : [] },
    window: {
      matchMedia: () => ({ matches: reducedMotion }),
      IntersectionObserver: observerAvailable ? IntersectionObserver : undefined,
    },
  });
  vm.runInContext(readFileSync(new URL('../../public/js/portal-effects.js', import.meta.url), 'utf8'), context);
  return { sections, root, observed, notify: entries => notify(entries) };
}

test('sections are revealed once they scroll into view', () => {
  const { sections, root, observed, notify } = effects();
  assert.equal(root.classList.contains('reveal-ready'), true);
  assert.equal(observed.length, 2);

  notify([{ target: sections[0], isIntersecting: true }, { target: sections[1], isIntersecting: false }]);

  assert.equal(sections[0].classList.contains('is-revealed'), true);
  assert.equal(sections[1].classList.contains('is-revealed'), false);
  assert.deepEqual(observed, [sections[1]]);
});

test('content is never hidden when motion is reduced or the observer is unavailable', () => {
  assert.equal(effects({ reducedMotion: true }).root.classList.contains('reveal-ready'), false);
  assert.equal(effects({ observerAvailable: false }).root.classList.contains('reveal-ready'), false);
});
