/* Refresh data in place. Forms, open dialogs, current filters and the surrounding page stay under user control. */
(() => {
    if (window.SiteLiveUpdates) return;
    const delay = 5000;
    const dirtyForms = new WeakSet();
    const signatures = new Map();
    let timer;
    let controller;
    let navigating = false;
    let generation = 0;
    let lastInput = 0;
    let signatureUrl = '';

    function isPaused() {
        const field = document.activeElement;
        const editing = field?.matches('input:not([type="hidden"]), textarea, select, [contenteditable="true"]')
            && (field.isContentEditable || field.closest('form')?.method.toLowerCase() === 'post');
        return navigating || document.hidden || Date.now() - lastInput < 1200
            || !!document.querySelector('dialog[open], .modal-overlay.show, details[open], form[data-submitting], [data-filter-results][aria-busy="true"]')
            || editing
            || !!window.getSelection?.()?.toString();
    }

    function schedule(wait = delay) {
        clearTimeout(timer);
        if (!navigating && !document.hidden) timer = setTimeout(refresh, wait);
    }

    function cancel() {
        generation++;
        clearTimeout(timer);
        controller?.abort();
    }

    function accessChanged() {
        navigating = true;
        cancel();
        document.querySelectorAll('[data-resident-private]').forEach(element => element.replaceChildren());
        window.location.reload();
    }

    function formKey(form) {
        return [form.getAttribute('action') || '', form.getAttribute('method') || 'get',
            ...['_method', '_announcement', '_employee'].map(name => form.elements.namedItem(name)?.value || '')].join('|');
    }

    function replaceRegion(current, incoming) {
        const focused = document.activeElement;
        const selection = typeof focused?.selectionStart === 'number' ? [focused.selectionStart, focused.selectionEnd] : null;
        const scrollSelector = '[data-ui-table-scroll], [data-filter-region], .request-table';
        const scrollPositions = [...current.querySelectorAll(scrollSelector)].map(element => [element.scrollLeft, element.scrollTop]);
        const preserveAll = current.hasAttribute('data-live-preserve-forms');
        const candidates = [...incoming.querySelectorAll('form')];
        for (const form of current.querySelectorAll('form')) {
            if (!preserveAll && !dirtyForms.has(form) && !form.querySelector('[aria-invalid="true"]') && form.method.toLowerCase() !== 'get') continue;
            const match = candidates.find(other => formKey(other) === formKey(form));
            if (!match) continue;
            candidates.splice(candidates.indexOf(match), 1);
            const modal = form.closest('ui-modal');
            const panel = preserveAll ? form.closest('.rp-panel') : null;
            const island = modal || panel || form;
            const replacement = modal ? match.closest('ui-modal') : panel ? match.closest('.rp-panel') : match;
            if (replacement && current.contains(island)) replacement.replaceWith(island);
        }

        incoming.querySelectorAll('script').forEach(script => script.remove());
        const scroll = { x: window.scrollX, y: window.scrollY };
        const swap = () => {
            window.Alpine?.destroyTree?.(current);
            current.replaceWith(incoming);
            window.Alpine?.initTree?.(incoming);
        };
        if (window.Alpine?.mutateDom) window.Alpine.mutateDom(swap);
        else swap();
        window.StaffUi?.prepare(incoming);
        [...incoming.querySelectorAll(scrollSelector)].forEach((element, index) => {
            if (scrollPositions[index]) [element.scrollLeft, element.scrollTop] = scrollPositions[index];
        });
        if (focused?.isConnected && incoming.contains(focused)) {
            focused.focus({ preventScroll: true });
            if (selection) focused.setSelectionRange(...selection);
        }
        window.scrollTo(scroll.x, scroll.y);
    }

    async function refresh() {
        if (controller || isPaused()) { schedule(); return false; }
        const regions = [...document.querySelectorAll('[data-live-region]')];
        if (!regions.length && !document.getElementById('app')) { schedule(); return false; }
        const url = window.location.href;
        if (signatureUrl !== url) { signatures.clear(); signatureUrl = url; }
        const version = generation;
        const active = controller = new AbortController();
        const timeout = setTimeout(() => active.abort(), 20000);
        let wait = delay;
        try {
            if (document.getElementById('app')) {
                await window.refreshWorkspaceLive?.();
            } else if (regions.length) {
                const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal: active.signal,
                    headers: { Accept: 'text/html', 'X-Live-Update': '1' } });
                if (version !== generation || url !== window.location.href) return false;
                if ([401, 403, 419].includes(response.status) || (response.redirected && /\/(login|confirm-password|two-factor-challenge)(\/|$)/.test(new URL(response.url).pathname))) {
                    accessChanged();
                    return false;
                }
                if (!response.ok || response.redirected) throw new Error('Live data unavailable');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                if (version !== generation || url !== window.location.href || isPaused()) return false;
                for (const current of regions) {
                    if (!current.isConnected) continue;
                    const name = current.dataset.liveRegion;
                    const incoming = [...page.querySelectorAll('[data-live-region]')].find(region => region.dataset.liveRegion === name);
                    if (!incoming) continue;
                    // Flux generates fresh control IDs on each render; those do not represent a data change.
                    const signature = incoming.innerHTML.replace(/flux-[\w-]+/g, 'flux-generated');
                    const key = `${url}|${name}`;
                    if (signatures.get(key) === signature) continue;
                    replaceRegion(current, incoming);
                    signatures.set(key, signature);
                }
            }
            document.dispatchEvent(new CustomEvent('site:live-updated'));
            return true;
        } catch (error) {
            if (error.name !== 'AbortError') wait = 15000;
            // Retain the last usable data during outages; never navigate or blank the page on a polling failure.
            return false;
        } finally {
            clearTimeout(timeout);
            if (controller === active) controller = null;
            if (version === generation) schedule(wait);
        }
    }

    for (const type of ['input', 'change']) document.addEventListener(type, event => {
        lastInput = Date.now();
        const form = event.target.closest?.('form');
        if (form) dirtyForms.add(form);
    }, true);
    document.addEventListener('pointerdown', () => { lastInput = Date.now(); }, true);
    document.addEventListener('visibilitychange', () => { cancel(); if (!document.hidden) schedule(0); });
    document.addEventListener('livewire:navigate', () => { navigating = true; cancel(); });
    document.addEventListener('livewire:navigated', () => { navigating = false; signatures.clear(); schedule(); });
    window.addEventListener('pagehide', () => { navigating = true; cancel(); });
    window.addEventListener('pageshow', () => { navigating = false; schedule(); });
    window.addEventListener('online', () => schedule(0));
    window.SiteLiveUpdates = { refresh, isPaused, accessChanged, get signal() { return controller?.signal; } };
    schedule();
})();
