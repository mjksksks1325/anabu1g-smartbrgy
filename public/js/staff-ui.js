/*
 * Shared behaviour for Flux dialogs on migrated staff pages (flux:modal is a <ui-modal> wrapper around a native
 * <dialog>; Flux opens it with showModal()). Flux handles opening from flux:modal.trigger, Escape, the close button
 * and the backdrop. This adds what server-rendered pages need on top:
 *  - name each dialog by its heading: data-labelledby on the flux:modal wrapper becomes aria-labelledby;
 *  - reopen the dialog marked data-open-on-load (after a validation error, or a link to one record), focusing
 *    the first invalid field;
 *  - return focus to the control that opened a dialog when it closes.
 * It runs on first load and after every Livewire navigation.
 */
(() => {
    if (window.StaffUi) {
        return;
    }

    let lastTrigger = null;
    const dialogOf = (wrapper) => wrapper?.querySelector(':scope > dialog') ?? null;

    function prepare(root = document) {
        root.querySelectorAll('ui-modal[data-labelledby]').forEach((wrapper) => {
            const dialog = dialogOf(wrapper);
            if (dialog && !dialog.hasAttribute('aria-labelledby')) {
                dialog.setAttribute('aria-labelledby', wrapper.dataset.labelledby);
            }
        });

        const dialog = dialogOf(root.querySelector('ui-modal[data-open-on-load]'));
        if (dialog && !dialog.open) {
            dialog.showModal();
            dialog.querySelector('[aria-invalid="true"], [autofocus]')?.focus();
        }
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('[data-flux-modal-trigger]');
        if (trigger) {
            lastTrigger = trigger.querySelector('button, a[href]') ?? trigger;
        }
    }, true);

    document.addEventListener('close', (event) => {
        if (event.target instanceof HTMLDialogElement && event.target.closest('ui-modal') && lastTrigger?.isConnected) {
            const trigger = lastTrigger;
            lastTrigger = null;
            trigger.focus();
        }
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => prepare(), { once: true });
    } else {
        prepare();
    }
    document.addEventListener('livewire:navigated', () => prepare());

    window.StaffUi = { prepare };
})();
