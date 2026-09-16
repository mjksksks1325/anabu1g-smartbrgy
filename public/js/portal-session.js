function _saveSession() {
    sessionStorage.setItem(_SK, JSON.stringify({
        screen: document.querySelector('.screen.active')?.id || 'screen-terms',
        docId: selectedDocId,
        tncScrolled,
        tncChecked: document.getElementById('tnc-agree')?.checked || false,
        lastCode,
        theme: _portalDark,
        confirmSummary: document.getElementById('conf-summary')?.innerHTML || '',
        statusCode: document.getElementById('status-code')?.value || '',
        form: {
            name: document.getElementById('f-name')?.value || '',
            address: document.getElementById('f-address')?.value || '',
            email: document.getElementById('f-email')?.value || '',
            purpose: document.getElementById('f-purpose')?.value || '',
            business: document.getElementById('f-business')?.value || '',
            dob: document.getElementById('f-dob')?.value || '',
        }
    }));
}

function _restoreSession() {
    const raw = sessionStorage.getItem(_SK);

    if (!raw) return;

    try {
        const d = JSON.parse(raw);

        if (d.theme) {
            _portalDark = true;
            document.body.classList.add('dark-mode');

            const ti = document.getElementById('portal-theme-icon');
            const tl = document.getElementById('portal-theme-label');

            if (ti) ti.textContent = '☀️';
            if (tl) tl.textContent = 'Light Mode';
        }

        if (d.form) {
            ['name', 'address', 'email', 'purpose', 'business'].forEach(k => {
                const el = document.getElementById('f-' + k);
                if (el) el.value = d.form[k] || '';
            });

            const dob = document.getElementById('f-dob');
            if (dob) dob.value = d.form.dob || '';
        }

        if (d.tncScrolled) {
            tncScrolled = true;

            const btn = document.getElementById('tnc-agree');
            if (btn) btn.disabled = false;

            const notice = document.getElementById('tnc-notice');

            if (notice) {
                notice.innerHTML = '✅ Nabasa mo na ang lahat ng tuntunin — maaari nang kumilos';
                notice.style.background = '#d1fae5';
                notice.style.borderColor = '#6ee7b7';
                notice.style.color = '#065f46';
            }
        }

        if (d.tncChecked) {
            const cb = document.getElementById('tnc-agree');
            const pb = document.getElementById('btn-proceed-terms');

            if (cb) cb.checked = true;
            if (pb) pb.disabled = false;
        }

        if (d.docId) {
            selectedDocId = d.docId;

            const doc = DOC_TYPES[d.docId];
            const btn = document.querySelector(
                `.cert-btn[onclick*="'${d.docId}'"]`
            );

            if (btn) btn.classList.add('selected');

            if (doc) {
                const di = document.getElementById('doc-sel-icon');
                const dl = document.getElementById('doc-sel-label');
                const df = document.getElementById('doc-sel-fee');
                const di2 = document.getElementById('doc-selected-info');
                const pb = document.getElementById('btn-proceed-doc');

                if (di) di.textContent = doc.icon;
                if (dl) dl.textContent = doc.label;
                if (df) df.textContent = doc.fee + ' · ' + doc.days;
                if (di2) di2.style.display = 'flex';
                if (pb) pb.disabled = false;

                const fl = document.getElementById('form-doc-label');
                const ff = document.getElementById('form-doc-fee');
                const fbf = document.getElementById('business-field');

                if (fl) fl.textContent = doc.icon + ' ' + doc.label;
                if (ff) ff.textContent = doc.fee;
                if (fbf) {
                    fbf.style.display =
                        d.docId === 'BBC' ? 'block' : 'none';
                }
            }
        }

        if (d.lastCode) {
            lastCode = d.lastCode;

            const cc = document.getElementById('conf-code');
            const ccm = document.getElementById('conf-code-mini');
            const csum = document.getElementById('conf-summary');

            if (cc) cc.textContent = d.lastCode;
            if (ccm) ccm.textContent = d.lastCode;
            if (csum && d.confirmSummary) {
                csum.innerHTML = d.confirmSummary;
            }
        }

        if (d.statusCode) {
            const sc = document.getElementById('status-code');
            if (sc) sc.value = d.statusCode;
        }

        if (d.screen && d.screen !== 'screen-terms') {
            document.querySelectorAll('.screen').forEach(s =>
                s.classList.remove('active')
            );

            const target = document.getElementById(d.screen);

            if (target) target.classList.add('active');
        }

    } catch (e) {
        sessionStorage.removeItem(_SK);
    }
}
