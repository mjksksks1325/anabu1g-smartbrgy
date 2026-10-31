(() => {
    let searchTimer;
    let archiveUrl = null;

    document.addEventListener('click', (event) => {
        const archiveButton = event.target.closest?.('[data-folder-archive]');
        if (archiveButton) {
            const dialog = document.getElementById('cabinet-folder-archive-dialog');
            const form = dialog?.querySelector('[data-folder-archive-form]');
            if (!dialog || !form) {
                return;
            }

            archiveUrl = archiveButton.dataset.archiveUrl;
            form.reset();
            dialog.querySelector('[data-folder-archive-reference]').textContent = archiveButton.dataset.fileReference || '';
            const error = dialog.querySelector('[data-folder-archive-error]');
            error.hidden = true;
            error.textContent = '';
            dialog.showModal();
            dialog.querySelector('[name="super_admin_password"]').focus();
            return;
        }

        if (event.target.closest?.('[data-folder-archive-cancel]')) {
            document.getElementById('cabinet-folder-archive-dialog')?.close();
        }
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest?.('[data-folder-archive-form]');
        if (!form) {
            return;
        }

        event.preventDefault();
        if (!archiveUrl) {
            return;
        }

        const submitButton = form.querySelector('[data-folder-archive-submit]');
        const passwordInput = form.querySelector('[name="super_admin_password"]');
        const error = form.querySelector('[data-folder-archive-error]');
        submitButton.disabled = true;
        error.hidden = true;
        error.textContent = '';

        try {
            const response = await fetch(archiveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ super_admin_password: passwordInput.value }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                error.textContent = payload.message || 'Hindi na-archive ang folder. Pakisubukan ulit.';
                error.hidden = false;
                passwordInput.focus();
                return;
            }

            window.location.reload();
        } catch {
            error.textContent = 'Hindi makakonekta sa server. Walang binagong folder.';
            error.hidden = false;
        } finally {
            submitButton.disabled = false;
        }
    });

    document.addEventListener('input', (event) => {
        const input = event.target;
        const form = input.closest?.('[data-rfid-auto-filter]');

        if (!form || input.type !== 'search') {
            return;
        }

        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => form.requestSubmit(), 350);
    });

    document.addEventListener('change', (event) => {
        const input = event.target;
        const form = input.closest?.('[data-rfid-auto-filter]');

        if (!form || !(input.matches('select') || input.type === 'date')) {
            return;
        }

        window.clearTimeout(searchTimer);
        form.requestSubmit();
    });
})();
