import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/js/site-live-updates.js', import.meta.url), 'utf8');

function site(fetch) {
    let reloads = 0;
    let cleared = 0;
    const scheduled = [];
    const document = { hidden: false, activeElement: null, querySelector: () => null, getElementById: () => null, addEventListener() {},
        querySelectorAll: selector => selector === '[data-live-region]' ? [{ dataset: { liveRegion: 'private' }, isConnected: true }]
            : selector === '[data-resident-private]' ? [{ replaceChildren() { cleared++; } }] : [] };
    const window = { location: { href: 'https://example.test/portal/account', reload() { reloads++; } }, addEventListener() {}, getSelection: () => null };
    const context = vm.createContext({ document, window, fetch, AbortController, URL, WeakSet, Map, Date,
        setTimeout(callback, delay) { scheduled.push(delay); return scheduled.length; }, clearTimeout() {} });
    vm.runInContext(source, context);
    return { live: window.SiteLiveUpdates, document, window, scheduled, reloads: () => reloads, cleared: () => cleared };
}

for (const status of [401, 403, 419]) test(`a ${status} response clears private data and lets the server reauthorize the page`, async () => {
    const page = site(async () => ({ status, ok: false, redirected: false }));
    assert.equal(await page.live.refresh(), false);
    assert.equal(page.reloads(), 1);
    assert.equal(page.cleared(), 1);
});

test('a redirect to sign-in reauthorizes the page rather than retaining the private view', async () => {
    const page = site(async () => ({ status: 200, ok: true, redirected: true, url: 'https://example.test/portal/login' }));
    await page.live.refresh();
    assert.equal(page.reloads(), 1);
});

test('network failures keep private data intact and back off without reloading', async () => {
    const page = site(async () => { throw new TypeError('Offline'); });
    assert.equal(await page.live.refresh(), false);
    assert.equal(page.reloads(), 0);
    assert.equal(page.cleared(), 0);
    assert.equal(page.scheduled.at(-1), 15000);
});

test('hidden tabs do not request data', async () => {
    let calls = 0;
    const page = site(async () => { calls++; });
    page.document.hidden = true;
    assert.equal(await page.live.refresh(), false);
    assert.equal(calls, 0);
});

test('a slow response does not overlap another poll or overwrite a different page', async () => {
    let resolve;
    let calls = 0;
    const page = site(() => { calls++; return new Promise(done => { resolve = done; }); });
    const first = page.live.refresh();
    assert.equal(await page.live.refresh(), false);
    page.window.location.href = 'https://example.test/portal/profile';
    resolve({ status: 200, ok: true, redirected: false });
    assert.equal(await first, false);
    assert.equal(calls, 1);
    assert.equal(page.reloads(), 0);
});
