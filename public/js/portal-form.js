function selectDoc(id, el) {
    selectedDocId = id;

    document.querySelectorAll('.cert-btn').forEach(b =>
        b.classList.remove('selected')
    );

    el.classList.add('selected');
    document.querySelectorAll('.cert-btn').forEach(button => button.setAttribute('aria-pressed', String(button === el)));

    const d = DOC_TYPES[id];

    document.getElementById('doc-sel-icon').textContent = d.icon;
    document.getElementById('doc-sel-label').textContent = d.label;
    document.getElementById('doc-sel-fee').textContent =
        d.fee + ' · ' + d.days;

    document.getElementById('doc-selected-info').style.display = 'flex';
    document.getElementById('btn-proceed-doc').disabled = false;
}

async function proceedFromDoc() {
    if (typeof ensurePortalIdentity === 'function' && !await ensurePortalIdentity()) return;
    if (!selectedDocId) return;

    const d = DOC_TYPES[selectedDocId];

    document.getElementById('form-doc-label').textContent =
        d.icon + ' ' + d.label;

    document.getElementById('form-doc-fee').textContent = d.fee;

    document.getElementById('business-field').style.display =
        selectedDocId === 'BBC' ? 'block' : 'none';

    showScreen('screen-form');
}

function validatePortalForm() {
    const messages = {
        'f-name': 'Ilagay ang inyong buong pangalan (hanggang 255 character).',
        'f-address': 'Ilagay ang inyong address sa barangay (hanggang 1000 character).',
        'f-email': 'Maglagay ng wastong email address.',
        'f-dob': 'Ilagay ang wastong petsa ng kapanganakan. Hindi ito maaaring nasa hinaharap.',
        'f-purpose': 'Ilagay ang layunin ng inyong request (hanggang 255 character).',
        'f-business': 'Ilagay ang pangalan ng negosyo (hanggang 255 character).',
    };
    const error = document.getElementById('portal-form-error');
    error.hidden = true;
    let firstInvalid = null;
    for (const [id, message] of Object.entries(messages)) {
        const field = document.getElementById(id);
        const required = id !== 'f-business' || selectedDocId === 'BBC';
        field.required = required;
        const invalid = required && (!field.value.trim() || !field.checkValidity());
        field.setAttribute('aria-invalid', String(invalid));
        if (invalid && !firstInvalid) firstInvalid = { field, message };
    }
    if (firstInvalid) {
        showScreen('screen-form');
        error.textContent = firstInvalid.message;
        error.hidden = false;
        firstInvalid.field.setAttribute('aria-describedby', 'portal-form-error');
        firstInvalid.field.focus();
        return false;
    }
    return true;
}

function goToAttachment() {
    if (validatePortalForm()) showScreen('screen-attachment');
}

function newRequest(recordHistory = true) {
    try { sessionStorage.removeItem(_SK); } catch (_) {}

    selectedDocId = null;
    lastCode = '';
    portalConfirmation = null;
    document.getElementById('portal-form-error').hidden = true;

    document.querySelectorAll('.cert-btn').forEach(b =>
        b.classList.remove('selected')
    );

    document.getElementById('btn-proceed-doc').disabled = true;
    document.getElementById('doc-selected-info').style.display = 'none';

    [
        'f-name',
        'f-address',
        'f-email',
        'f-purpose',
        'f-business'
    ].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });

    document.getElementById('f-dob').value = '';

    clearAttachment();

    showScreen('screen-doctype', recordHistory);
}
