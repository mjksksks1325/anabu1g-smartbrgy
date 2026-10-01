import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

function historyClient() {
  const timers = new Map();
  const listeners = {};
  const windowListeners = {};
  const row = { dataset: { requestId: '7', requestVersion: 'old' }, updates: [] };
  Object.defineProperty(row, 'outerHTML', { set(html) { this.updates.push(html); this.dataset.requestVersion = html.match(/data-request-version="([^"]+)"/)[1]; } });
  const history = {
    dataset: { requestHistoryUrl: '/portal/account/statuses' },
    querySelectorAll: () => [row],
  };
  let nextTimer = 0;
  const context = vm.createContext({
    URL, AbortController,
    document: {
      hidden: false,
      querySelector: () => history,
      addEventListener: (name, callback) => { listeners[name] = callback; },
    },
    window: {
      location: { href: 'http://localhost/portal/account?page=2', reload() { context.reloads++; } },
      addEventListener: (name, callback) => { windowListeners[name] = callback; },
    },
    setTimeout(callback, delay) { timers.set(++nextTimer, { callback, delay }); return nextTimer; },
    clearTimeout(id) { timers.delete(id); },
    fetch: async () => ({ ok: true, json: async () => ({ items: {} }) }),
  });
  context.reloads = 0;
  vm.runInContext(readFileSync(new URL('../../public/js/request-history.js', import.meta.url), 'utf8'), context);
  async function tick() {
    const [id, timer] = timers.entries().next().value;
    timers.delete(id);
    await timer.callback();
    return timer.delay;
  }
  return { context, row, timers, listeners, windowListeners, tick };
}

test('polls visible request ids and changes only rows with a new version', async () => {
  const { context, row, tick } = historyClient();
  const urls = [];
  context.fetch = async url => {
    urls.push(String(url));
    return { ok: true, json: async () => ({ items: { 7: { version: urls.length === 1 ? 'old' : 'new', html: '<li data-request-version="new">Approved</li>' } } }) };
  };

  assert.equal(await tick(), 5000);
  assert.equal(row.updates.length, 0);
  assert.equal(await tick(), 5000);
  assert.deepEqual(row.updates, ['<li data-request-version="new">Approved</li>']);
  assert.equal(new URL(urls[0]).searchParams.get('ids[]'), '7');
  assert.equal(new URL(urls[0]).searchParams.has('page'), false);
});

test('pauses in hidden tabs and prevents overlapping requests', async () => {
  const { context, timers, listeners, tick } = historyClient();
  let complete;
  let calls = 0;
  context.fetch = () => { calls++; return new Promise(resolve => { complete = resolve; }); };

  const pending = tick();
  assert.equal(calls, 1);
  context.document.hidden = true;
  listeners.visibilitychange();
  assert.equal(timers.size, 0);
  context.document.hidden = false;
  listeners.visibilitychange();
  await tick();
  assert.equal(calls, 1);
  complete({ ok: true, json: async () => ({ items: {} }) });
  await pending;
  assert.equal(timers.size, 1);
});

test('retries network failures slowly and stops after navigation', async () => {
  const { context, timers, windowListeners, tick } = historyClient();
  context.fetch = async () => { throw new Error('offline'); };

  await tick();
  assert.equal([...timers.values()][0].delay, 15000);
  windowListeners.pagehide();
  assert.equal(timers.size, 0);
});

test('reloads when resident access is revoked and does not keep polling', async () => {
  const { context, timers, tick } = historyClient();
  context.fetch = async () => ({ ok: false, status: 403 });

  await tick();

  assert.equal(context.reloads, 1);
  assert.equal(timers.size, 0);
});
