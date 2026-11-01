/* Mirrors the active request screen and selected document in the desktop step panel. Read-only: it never changes the request. */
(() => {
    const flow = document.getElementById('request-flow');
    const aside = document.getElementById('request-progress');
    if (!flow || !aside) return;

    const stepByScreen = { 'screen-terms': 1, 'screen-doctype': 2, 'screen-form': 3, 'screen-attachment': 4, 'screen-review': 5 };
    const documentValue = document.getElementById('request-summary-document');
    const feeValue = document.getElementById('request-summary-fee');

    function update() {
        const screen = flow.querySelector('.screen.active')?.id;
        const current = stepByScreen[screen];
        aside.hidden = !current;
        aside.querySelectorAll('[data-step]').forEach(item => {
            const step = Number(item.dataset.step);
            item.classList.toggle('is-done', step < current);
            item.classList.toggle('is-current', step === current);
            if (step === current) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
            const marker = item.querySelector('.v2-step-now');
            if (marker) marker.hidden = step !== current;
        });

        const selected = typeof selectedDocId === 'string' && typeof DOC_TYPES === 'object' ? DOC_TYPES[selectedDocId] : null;
        portalSetText(documentValue, selected ? selected.label : 'Wala pang napili');
        if (selected) {
            portalSetText(feeValue, selected.fee);
        } else {
            portalClearText(feeValue);
            feeValue.textContent = '—';
        }
    }

    new MutationObserver(update).observe(flow, { subtree: true, attributes: true, attributeFilter: ['class'] });
    update();
})();
