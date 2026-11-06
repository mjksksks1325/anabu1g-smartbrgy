/*
 * Shared behaviour for Flux dialogs on migrated staff pages (flux:modal is a <ui-modal> wrapper around a native
 * <dialog>; Flux opens it with showModal()). Flux handles opening from flux:modal.trigger, Escape, the close button
 * and the backdrop. This adds what server-rendered pages need on top:
 *  - name each dialog by its heading: data-labelledby on the flux:modal wrapper becomes aria-labelledby;
 *  - reopen the dialog marked data-open-on-load (after a validation error, or a link to one record), focusing
 *    the first invalid field;
 *  - return focus to the control that opened a dialog when it closes;
 *  - move focus into a dialog when it opens (Flux leaves it on the page behind): to the control marked
 *    data-ui-initial-focus (Cancel, on a confirmation), else to the dialog itself, which announces its heading;
 *  - mark a dialog that opens over another open one (a confirmation over a side panel) data-ui-stacked, so
 *    staff-ui.css dims everything beneath it.
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

    /** Called when a Flux dialog's open state changes. */
    function opened(dialog) {
        if (!dialog.open) {
            dialog.removeAttribute('data-ui-stacked');
            return;
        }

        const others = [...document.querySelectorAll('ui-modal > dialog[open]')].filter((other) => other !== dialog);
        if (others.length) {
            dialog.setAttribute('data-ui-stacked', '');
        }

        // Flux focuses a placeholder at the top of the dialog and hides it straight away, which drops focus to the
        // page. Wait for that to finish, then focus a real control unless a script has already placed focus inside.
        const nextFrame = typeof requestAnimationFrame === 'function' ? requestAnimationFrame : (callback) => setTimeout(callback, 0);
        nextFrame(() => settle(dialog));
    }

    function settle(dialog) {
        const active = document.activeElement;
        if (!dialog.open || (dialog.contains(active) && !active.matches('[data-flux-focus-placeholder]'))) {
            return;
        }
        const target = dialog.querySelector('[data-ui-initial-focus], [autofocus]');
        if (target) {
            target.focus();
            return;
        }
        if (!dialog.hasAttribute('tabindex')) {
            dialog.setAttribute('tabindex', '-1');
        }
        dialog.focus();
    }

    if (typeof MutationObserver === 'function') {
        new MutationObserver((records) => {
            for (const record of records) {
                if (record.target instanceof HTMLDialogElement && record.target.parentElement?.matches('ui-modal')) {
                    opened(record.target);
                }
            }
        }).observe(document.documentElement, { subtree: true, attributes: true, attributeFilter: ['open'] });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => prepare(), { once: true });
    } else {
        prepare();
    }
    document.addEventListener('livewire:navigated', () => prepare());

    window.StaffUi = { prepare, opened, settle };
})();
