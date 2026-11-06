(() => {
    let archiveUrl = null;
    let archiveRecord = null;
    let archiveTrigger = null;

    /*
     * Every dialog on this page is a Flux modal: a <ui-modal> wrapper (which carries the data-* hooks) around a
     * native <dialog>. Flux opens a modal by calling showModal() on that dialog, so this script does the same:
     * Flux keeps its transitions, close button, Escape and backdrop handling, and this script keeps the page's
     * own behaviour (reopen after a validation error, focus return, password-confirmed archiving).
     */
    const dialogIn = (wrapper) => wrapper?.querySelector('dialog') ?? null;
    const createWrapper = () => document.querySelector('[data-announcement-create-dialog]');
    const editWrapper = (id) => document.querySelector(`[data-announcement-edit-dialog="${CSS.escape(String(id))}"]`);

    const openDialog = (dialog, focusTarget) => {
        if (!dialog || dialog.open) {
            return;
        }
        dialog.showModal();
        focusTarget?.focus();
    };

    // Flux puts unknown attributes on the wrapper, so name each dialog by its heading here.
    const labelDialogs = () => {
        document.querySelectorAll('[data-labelledby]').forEach((wrapper) => {
            const dialog = dialogIn(wrapper);
            if (dialog && !dialog.hasAttribute('aria-labelledby')) {
                dialog.setAttribute('aria-labelledby', wrapper.dataset.labelledby);
            }
        });
    };

    /*
     * New announcement dialog. Closing it keeps what was typed (the form is not reset), so reopening continues
     * the draft; after a server validation error the page reopens it with the fields and messages filled in.
     * Edit flyouts work the same way for the announcement whose save failed.
     */
    const openCreateDialog = () => {
        const dialog = dialogIn(createWrapper());
        openDialog(dialog, dialog?.querySelector('[aria-invalid="true"]') || dialog?.querySelector('[name="title"]'));
    };

    const openEditDialog = (id) => {
        const dialog = dialogIn(editWrapper(id));
        openDialog(dialog, dialog?.querySelector('[aria-invalid="true"]') || dialog?.querySelector('[name="title"]'));
    };

    document.addEventListener('click', (event) => {
        if (event.target.closest?.('[data-announcement-create-open]')) {
            openCreateDialog();
            return;
        }
        if (event.target.closest?.('[data-announcement-create-close]')) {
            dialogIn(createWrapper())?.close();
            return;
        }
        const editButton = event.target.closest?.('[data-announcement-edit-open]');
        if (editButton) {
            openEditDialog(editButton.dataset.announcementEditOpen);
        }
    });

    document.addEventListener('close', (event) => {
        if (!(event.target instanceof HTMLDialogElement)) {
            return;
        }
        if (event.target.closest('[data-announcement-create-dialog]')) {
            document.querySelector('[data-announcement-create-open]')?.focus();
            return;
        }
        const edit = event.target.closest('[data-announcement-edit-dialog]');
        if (edit) {
            document.querySelector(`[data-announcement-edit-open="${CSS.escape(edit.dataset.announcementEditDialog)}"]`)?.focus();
        }
    }, true);

    const openOnLoad = () => {
        labelDialogs();
        if (createWrapper()?.hasAttribute('data-open-on-load')) {
            openCreateDialog();
            return;
        }
        const failedEdit = document.querySelector('[data-announcement-edit-dialog][data-open-on-load]');
        if (failedEdit) {
            openEditDialog(failedEdit.dataset.announcementEditDialog);
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openOnLoad, { once: true });
    } else {
        openOnLoad();
    }

    const archiveDialog = () => dialogIn(document.querySelector('[data-announcement-archive-dialog]'));

    document.addEventListener('click', (event) => {
        const archiveButton = event.target.closest?.('[data-announcement-archive]');
        const dialog = archiveDialog();

        if (archiveButton && dialog) {
            const form = dialog.querySelector('[data-announcement-archive-form]');
            archiveUrl = archiveButton.dataset.archiveUrl;
            archiveRecord = archiveButton.closest('[data-announcement-record]');
            archiveTrigger = archiveButton;
            form.reset();
            window.FormFeedback?.clearInvalid(form.querySelector('[name="super_admin_password"]'));
            dialog.querySelector('[data-announcement-archive-title]').textContent = archiveButton.dataset.announcementTitle || '';
            const error = dialog.querySelector('[data-announcement-archive-error]');
            error.hidden = true;
            error.textContent = '';
            openDialog(dialog, form.querySelector('[name="super_admin_password"]'));
            return;
        }

        if (event.target.closest?.('[data-announcement-archive-cancel]')) {
            dialog?.close();
        }
    });

    document.addEventListener('close', (event) => {
        if (event.target instanceof HTMLDialogElement && event.target.closest('[data-announcement-archive-dialog]')) {
            const form = event.target.querySelector('[data-announcement-archive-form]');
            form?.reset();
            window.PasswordVisibility?.hide(event.target);
            if (archiveTrigger?.isConnected) {
                archiveTrigger.focus();
            }
            archiveUrl = null;
            archiveRecord = null;
            archiveTrigger = null;
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

        const dialog = form.closest('dialog');
        const button = form.querySelector('[data-announcement-archive-submit]');
        const password = form.querySelector('[name="super_admin_password"]');
        const error = form.querySelector('[data-announcement-archive-error]');
        const record = archiveRecord;
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
                // A password error is shown under the field by form-feedback.js; anything else is shown here.
                if (!payload.errors?.super_admin_password || !window.FormFeedback) {
                    error.textContent = payload.message || 'The announcement was not archived. Please try again.';
                    error.hidden = false;
                }
                password.focus();
                return;
            }

            archiveTrigger = null;
            dialog.close();
            const feedback = document.querySelector('[data-announcement-feedback]');
            const feedbackText = feedback?.querySelector('[data-announcement-feedback-text]') ?? feedback;
            if (feedbackText) {
                feedbackText.textContent = payload.message || 'Announcement archived.';
            }
            if (feedback) {
                feedback.hidden = false;
            }
            record?.remove();
            window.setTimeout(() => window.location.reload(), 900);
        } catch {
            error.textContent = 'Could not reach the server. No announcement was changed.';
            error.hidden = false;
        } finally {
            button.disabled = false;
        }
    });
})();
