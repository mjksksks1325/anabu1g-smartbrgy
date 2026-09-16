async function checkStatus() {
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

        const data = await res.json();

        resultDiv.style.display = 'block';

        if (!res.ok || data.success !== true) {
            resultDiv.innerHTML = `
                <div class="alert alert-red" style="margin-top:8px;">
                    ❌ Walang record ng request na may code na
                    <strong>${code}</strong>.
                </div>
            `;
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

        resultDiv.innerHTML = `
            <div class="alert alert-green" style="margin-top:8px;">
                <div>
                    <strong>✅ Request Nahanap</strong><br><br>

                    <strong>Reference Code:</strong>
                    ${data.reference_code}<br>

                    <strong>Dokumento:</strong>
                    ${data.document_type}<br>

                    <strong>Status:</strong>
                    ${statusMap[data.status] || data.status}

                    ${
                        data.remarks
                            ? `<br><strong>Remarks:</strong> ${data.remarks}`
                            : ''
                    }
                </div>
            </div>
        `;

    } catch (error) {
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