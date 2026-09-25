let portalSubmitting = false;

async function refreshPortalCsrfToken() {
    const response = await fetch('/portal/csrf-token', {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
        throw new Error('Hindi ma-refresh ang secure session. Paki-reload ang page.');
    }

    const data = await response.json();
    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', data.token);

    return data.token;
}

async function sendPortalDocumentRequest(formData) {
    const send = token => fetch('/portal/request', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: formData
    });

    let response = await send(await refreshPortalCsrfToken());

    if (response.status === 419) {
        response = await send(await refreshPortalCsrfToken());
    }

    return response;
}

async function submitRequest() {
    const generation = typeof residentIdentityGeneration === 'number' ? residentIdentityGeneration : null;
    if (portalSubmitting) return;
    if (lastCode) { showScreen('screen-confirm'); return; }
    if (!validatePortalForm()) return;
    const name = document.getElementById('f-name').value.trim();
    const address = document.getElementById('f-address').value.trim();
    const purpose = document.getElementById('f-purpose').value.trim();
    const emailInput = document.getElementById('f-email');
    const email = emailInput.value.trim();
    const biz = document.getElementById('f-business').value.trim();
    const attachment = document.getElementById('f-attachment').files[0];

    if (!name || !address || !email) {
        toast('Pakikumpleto ang mga kinakailangang impormasyon.', 'red');
        return;
    }

    if (!emailInput.checkValidity()) {
        toast('Maglagay ng wastong email address.', 'red');
        emailInput.focus();
        return;
    }

    const d = DOC_TYPES[selectedDocId];

    if (!d) {
        toast('Pumili muna ng dokumento.', 'red');
        return;
    }

    const finalPurpose = purpose;
    portalSubmitting = true;
    const submitButton = document.getElementById('submit-request-button');
    if (submitButton) { submitButton.disabled = true; submitButton.setAttribute('aria-busy', 'true'); submitButton.textContent = 'Isinusumite...'; }
    setLoading(true);

    try {
        const formData = new FormData();

        formData.append('document_type', d.label);
        formData.append('purpose', finalPurpose || '');
        formData.append('business_name', biz);

        if (attachment) {
            formData.append('attachment', attachment);
        }

        const response = await sendPortalDocumentRequest(formData);

        const data = await response.json().catch(() => ({}));
        if (generation !== null && generation !== residentIdentityGeneration) return;

        if (!response.ok) {
            console.error(data);
            const message = data.errors ? Object.values(data.errors).flat()[0] : data.message;
            toast(message || 'Hindi naisumite ang request. Subukan muli.', 'red');
            return;
        }

        lastCode = data.reference_code;

        document.getElementById('conf-code').textContent =
            data.reference_code;

        document.getElementById('conf-code-mini').textContent =
            data.reference_code;

        portalConfirmation = { name, document: d.label, fee: d.fee, address, purpose: finalPurpose };
        renderConfirmationSummary(portalConfirmation);

        showScreen('screen-confirm');

        toast(`Na-submit ang request ${data.reference_code}.`, 'green');

    } catch (error) {
        if (generation !== null && generation !== residentIdentityGeneration) return;
        console.error(error);
        toast(error.message || 'Hindi makakonekta sa server.', 'red');
    } finally {
        portalSubmitting = false;
        if (submitButton) { submitButton.disabled = false; submitButton.removeAttribute('aria-busy'); submitButton.textContent = 'I-submit ang request'; }
        setLoading(false);
    }
}
