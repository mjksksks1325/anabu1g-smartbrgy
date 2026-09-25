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
                        ? 'Hindi magamit ng account ang online services. Magpatulong sa Barangay Hall.'
                    : 'Hindi makuha ang status ngayon. Subukan muli mamaya.';
            resultDiv.innerHTML = `<div class="alert alert-red">${escapeStatusText(message)}</div>`;
            return;
        }

        const statusMap = {
            pending: ['Received', 'Natanggap na. Hinihintay pa ang review ng barangay staff.', ''],
            processing: ['Processing', 'Pinoproseso na ng barangay staff.', 'status-progress'],
            approved: ['Approved', 'Aprubado na. Hintaying maging Ready for release bago pumunta sa Barangay Hall.', 'status-progress'],
            ready_for_release: ['Ready for release', 'Puwede nang kunin sa Barangay Hall. Dalhin ang valid ID at ang reference number.', 'status-ready'],
            released: ['Released', 'Nakuha na ang dokumento.', 'status-done'],
            rejected: ['Not approved', 'Hindi naaprubahan ang request.', 'status-rejected']
        };
        const [label, description, statusClass] = statusMap[data.status] || [data.status, '', ''];

        resultDiv.innerHTML = `
            <div class="request-item">
                <div class="request-item-head">
                    <div>
                        <h3>${escapeStatusText(data.document_type)}</h3>
                        <p class="request-meta"><span class="reference">${escapeStatusText(data.reference_code)}</span></p>
                    </div>
                    <span class="status ${statusClass}">${escapeStatusText(label)}</span>
                </div>
                ${description ? `<p class="request-status-note">${escapeStatusText(description)}</p>` : ''}
                ${
                    data.rejection_reason
                        ? `<div class="request-callout request-callout-rejected"><strong>Dahilan</strong>${escapeStatusText(data.rejection_reason)}</div>`
                        : ''
                }
                ${
                    data.remarks
                        ? `<div class="request-callout request-callout-remarks"><strong>Paalala mula sa barangay</strong>${escapeStatusText(data.remarks)}</div>`
                        : ''
                }
            </div>
        `;

    } catch (error) {
        if (generation !== null && generation !== residentIdentityGeneration) return;
        console.error(error);

        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div class="alert alert-red">Hindi ma-check ang request ngayon. Subukan ulit mamaya.</div>';
    } finally {
        setLoading(false);
    }
}
