let portalConfirmation = null;

function _saveSession() {
    try {
        sessionStorage.setItem(_SK, JSON.stringify({
            screen: document.querySelector('.screen.active')?.id || 'screen-terms',
            docId: selectedDocId, tncScrolled,
            tncChecked: document.getElementById('tnc-agree')?.checked || false,
            lastCode, theme: _portalDark, confirmation: portalConfirmation,
            statusCode: document.getElementById('status-code')?.value || '',
            form: Object.fromEntries(['name', 'address', 'email', 'purpose', 'business', 'dob'].map(key => [key, document.getElementById('f-' + key)?.value || ''])),
        }));
    } catch (_) {
        // Storage can be unavailable in private browsing; the current request still works.
    }
}

function _restoreSession() {
    try {
        const raw = sessionStorage.getItem(_SK);
        if (!raw) return;
        const saved = JSON.parse(raw);
        if (!saved || typeof saved !== 'object') return;
        if (saved.theme === true) togglePortalTheme();
        for (const key of ['name', 'address', 'email', 'purpose', 'business', 'dob']) {
            const value = saved.form?.[key];
            if (typeof value === 'string') document.getElementById('f-' + key).value = value;
        }
        tncScrolled = saved.tncScrolled === true;
        const checkbox = document.getElementById('tnc-agree');
        checkbox.disabled = !tncScrolled;
        checkbox.checked = tncScrolled && saved.tncChecked === true;
        document.getElementById('btn-proceed-terms').disabled = !checkbox.checked;
        if (tncScrolled) document.getElementById('tnc-notice').textContent = 'Nabasa na ang mga tuntunin. Maaari nang magpatuloy.';
        if (typeof saved.docId === 'string' && Object.hasOwn(DOC_TYPES, saved.docId)) {
            const button = document.querySelector(`.cert-btn[onclick*="'${saved.docId}'"]`);
            if (button) selectDoc(saved.docId, button);
            const doc = DOC_TYPES[saved.docId];
            document.getElementById('form-doc-label').textContent = doc.label;
            document.getElementById('form-doc-fee').textContent = doc.fee;
            document.getElementById('business-field').style.display = saved.docId === 'BBC' ? 'block' : 'none';
        }
        if (typeof saved.lastCode === 'string' && /^REQ-[A-Z0-9-]+$/.test(saved.lastCode)) {
            lastCode = saved.lastCode;
            document.getElementById('conf-code').textContent = lastCode;
            document.getElementById('conf-code-mini').textContent = lastCode;
            portalConfirmation = saved.confirmation && typeof saved.confirmation === 'object' ? saved.confirmation : {
                name: saved.form?.name, address: saved.form?.address, purpose: saved.form?.purpose,
                document: DOC_TYPES[selectedDocId]?.label, fee: DOC_TYPES[selectedDocId]?.fee,
            };
            renderConfirmationSummary(portalConfirmation);
        }
        if (typeof saved.statusCode === 'string') document.getElementById('status-code').value = saved.statusCode;
        showScreen(saved.screen, false);
        if (saved.screen === 'screen-attachment') toast('Kung may attachment dati, piliin itong muli bago mag-submit.', '');
    } catch (_) {
        try { sessionStorage.removeItem(_SK); } catch (_) {}
    }
}
