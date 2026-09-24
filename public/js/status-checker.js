function escapeStatusText(value = '') {
    return String(value).replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[character]));
}

async function checkStatus() {
    const generation = typeof residentIdentityGeneration === 'number' ? residentIdentityGeneration : null;
    const code = document
        .getElementById('status-code')
        .value
        .trim()
        .toUpperCase();

    if (!code) {
        toast('Ilagay ang inyong reference code.', 'red');
        return;
    }

    const resultDiv = document.getElementById('status-result');

    resultDiv.style.display = 'block';
    resultDiv.textContent = 'Hinahanap ang request...';
    setLoading(true);

    try {
        const res = await fetch(
            `/portal/request/${encodeURIComponent(code)}`,
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            }
        );

        const data = await res.json().catch(() => ({}));
        if (generation !== null && generation !== residentIdentityGeneration) return;

        resultDiv.style.display = 'block';

        if (!res.ok || data.success !== true) {
            const message = res.status === 404
                ? `Walang record ng request na may code na ${code}.`
                : res.status === 429
                    ? 'Masyadong maraming pagsubok. Maghintay ng isang minuto bago subukan muli.'
                    : res.status === 401
                        ? 'Mag-log in sa Resident Portal para makita ang sarili mong request.'
                    : res.status === 403
                        ? 'Please contact the barangay for assistance with your resident account.'
                    : 'Hindi makuha ang status ngayon. Subukan muli mamaya.';
            resultDiv.innerHTML = `<div class="alert alert-red">${escapeStatusText(message)}</div>`;
            return;
        }

        const statusMap = {
            pending: 'Pending',
            processing: 'Pinoproseso',
            approved: 'Approved',
            ready_for_release: 'Handa nang Kunin',
            released: 'Nailabas na',
            rejected: 'Hindi Approved'
        };
        const resultClass = data.status === 'rejected' ? 'alert-red' : 'alert-green';

        resultDiv.innerHTML = `
            <div class="alert ${resultClass}" style="margin-top:8px;">
                <div>
                    <strong>✅ Request Nahanap</strong><br><br>

                    <strong>Reference Code:</strong>
                    ${escapeStatusText(data.reference_code)}<br>

                    <strong>Dokumento:</strong>
                    ${escapeStatusText(data.document_type)}<br>

                    <strong>Status:</strong>
                    ${escapeStatusText(statusMap[data.status] || data.status)}

                    ${
                        data.remarks
                            ? `<br><strong>Remarks:</strong> ${escapeStatusText(data.remarks)}`
                            : ''
                    }
                    ${
                        data.rejection_reason
                            ? `<br><strong>Reason for rejection:</strong> ${escapeStatusText(data.rejection_reason)}`
                            : ''
                    }
                </div>
            </div>
        `;

    } catch (error) {
        if (generation !== null && generation !== residentIdentityGeneration) return;
        console.error(error);

        resultDiv.style.display = 'block';
        resultDiv.innerHTML = `
            <div class="alert alert-red">
                ❌ May error sa pag-check ng request.
            </div>
        `;
    } finally {
        setLoading(false);
    }
}
