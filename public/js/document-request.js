async function submitRequest() {
    const name = document.getElementById('f-name').value.trim();
    const address = document.getElementById('f-address').value.trim();
    const purpose = document.getElementById('f-purpose').value.trim();
    const emailInput = document.getElementById('f-email');
    const email = emailInput.value.trim();
    const dob = document.getElementById('f-dob').value;
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

    const finalPurpose =
        selectedDocId === 'BBC'
            ? `${purpose} — Negosyo: ${biz}`
            : purpose;

    setLoading(true);

    try {
        const formData = new FormData();

        formData.append('document_type', d.label);
        formData.append('full_name', name);
        formData.append('date_of_birth', dob || '');
        formData.append('address', address);
        formData.append('email', email);
        formData.append('purpose', finalPurpose || '');
        formData.append('business_name', biz);

        if (attachment) {
            formData.append('attachment', attachment);
        }

        const response = await fetch('/portal/request', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute('content')
            },
            body: formData
        });

        const data = await response.json();

        if (!response.ok) {
            console.error(data);
            toast(data.message || 'May error sa pag-submit.', 'red');
            return;
        }

        lastCode = data.reference_code;

        document.getElementById('conf-code').textContent =
            data.reference_code;

        document.getElementById('conf-code-mini').textContent =
            data.reference_code;

        document.getElementById('conf-summary').innerHTML =
            `<strong>📋 Buod ng Request:</strong><br>
            👤 Pangalan: <strong>${name}</strong><br>
            📄 Dokumento: <strong>${d.label}</strong><br>
            💰 Bayad: <strong>${d.fee}</strong><br>
            📍 Address: ${address}<br>
            🎯 Layunin: ${finalPurpose || 'N/A'}`;

        showScreen('screen-confirm');

        toast(
            `✅ Request ${data.reference_code} na-submit!`,
            'green'
        );

    } catch (error) {
        console.error(error);
        toast('Hindi makakonekta sa server.', 'red');
    } finally {
        setLoading(false);
    }
}
