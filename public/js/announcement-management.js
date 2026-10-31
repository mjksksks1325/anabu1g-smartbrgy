(() => {
    let archiveUrl = null;
    let archiveRecord = null;

    document.addEventListener('click', (event) => {
        const archiveButton = event.target.closest?.('[data-announcement-archive]');
        const dialog = document.querySelector('[data-announcement-archive-dialog]');

        if (archiveButton && dialog) {
            const form = dialog.querySelector('[data-announcement-archive-form]');
            archiveUrl = archiveButton.dataset.archiveUrl;
            archiveRecord = archiveButton.closest('[data-announcement-record]');
            form.reset();
            dialog.querySelector('[data-announcement-archive-title]').textContent = archiveButton.dataset.announcementTitle || '';
            const error = dialog.querySelector('[data-announcement-archive-error]');
            error.hidden = true;
            error.textContent = '';
            dialog.showModal();
            form.querySelector('[name="super_admin_password"]').focus();
            return;
        }

        if (event.target.closest?.('[data-announcement-archive-cancel]')) {
            dialog?.close();
        }
    });

    document.addEventListener('close', (event) => {
        if (event.target.matches?.('[data-announcement-archive-dialog]')) {
            const form = event.target.querySelector('[data-announcement-archive-form]');
            form?.reset();
            window.PasswordVisibility?.hide(event.target);
            archiveUrl = null;
            archiveRecord = null;
        }
    }, true);

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest?.('[data-announcement-archive-form]');
        if (!form) {
            return;
        }

        event.preventDefault();
        if (!archiveUrl) {
            return;
        }

        const dialog = form.closest('[data-announcement-archive-dialog]');
        const button = form.querySelector('[data-announcement-archive-submit]');
        const password = form.querySelector('[name="super_admin_password"]');
        const error = form.querySelector('[data-announcement-archive-error]');
        button.disabled = true;
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
                body: JSON.stringify({ super_admin_password: password.value }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                error.textContent = payload.message || 'Hindi na-archive ang anunsyo. Pakisubukan ulit.';
                error.hidden = false;
                password.focus();
                return;
            }

            dialog.close();
            const feedback = document.querySelector('[data-announcement-feedback]');
            feedback.textContent = payload.message || 'Announcement archived.';
            feedback.hidden = false;
            archiveRecord?.remove();
            window.setTimeout(() => window.location.reload(), 900);
        } catch {
            error.textContent = 'Hindi makakonekta sa server. Walang binagong anunsyo.';
            error.hidden = false;
        } finally {
            button.disabled = false;
        }
    });
})();
