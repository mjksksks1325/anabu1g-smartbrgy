function selectDoc(id, el) {
    selectedDocId = id;

    document.querySelectorAll('.cert-btn').forEach(b =>
        b.classList.remove('selected')
    );

    el.classList.add('selected');

    const d = DOC_TYPES[id];

    document.getElementById('doc-sel-icon').textContent = d.icon;
    document.getElementById('doc-sel-label').textContent = d.label;
    document.getElementById('doc-sel-fee').textContent =
        d.fee + ' · ' + d.days;

    document.getElementById('doc-selected-info').style.display = 'flex';
    document.getElementById('btn-proceed-doc').disabled = false;

    _saveSession();
}

function proceedFromDoc() {
    if (!selectedDocId) return;

    const d = DOC_TYPES[selectedDocId];

    document.getElementById('form-doc-label').textContent =
        d.icon + ' ' + d.label;

    document.getElementById('form-doc-fee').textContent = d.fee;

    document.getElementById('business-field').style.display =
        selectedDocId === 'BBC' ? 'block' : 'none';

    showScreen('screen-form');
}

function goToAttachment() {
    const name = document.getElementById('f-name').value.trim();
    const address = document.getElementById('f-address').value.trim();
    const purpose = document.getElementById('f-purpose').value.trim();
    const biz = document.getElementById('f-business').value.trim();
    const dob = document.getElementById('f-dob').value;
    const emailInput = document.getElementById('f-email');
    const email = emailInput.value.trim();

    if (!name) {
        toast('Pakiusap ilagay ang inyong pangalan.', 'red');
        document.getElementById('f-name').focus();
        return;
    }

    if (!address) {
        toast('Pakiusap ilagay ang inyong address.', 'red');
        document.getElementById('f-address').focus();
        return;
    }

    if (!email) {
        toast('Pakiusap ilagay ang inyong email address.', 'red');
        emailInput.focus();
        return;
    }

    if (!emailInput.checkValidity()) {
        toast('Maglagay ng wastong email address.', 'red');
        emailInput.focus();
        return;
    }

    if (!dob) {
        toast('Pakiusap ilagay ang inyong petsa ng kapanganakan.', 'red');
        document.getElementById('f-dob').focus();
        return;
    }

    if (!purpose) {
        toast('Pakiusap ilagay ang layunin ng request.', 'red');
        document.getElementById('f-purpose').focus();
        return;
    }

    if (selectedDocId === 'BBC' && !biz) {
        toast('Pakiusap ilagay ang pangalan ng negosyo.', 'red');
        document.getElementById('f-business').focus();
        return;
    }

    showScreen('screen-attachment');
}

function newRequest() {
    sessionStorage.removeItem(_SK);

    selectedDocId = null;
    lastCode = '';

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

    showScreen('screen-doctype');
}
