/*
 * Data Privacy Notice before a new resident application. The notice opens as a modal on page load (a modal
 * dialog keeps focus inside it). Continue is enabled only after the notice has been scrolled to the end and
 * the consent box is ticked. Cancel and Escape leave the application. The server still requires the box.
 */
(() => {
    const dialog = document.querySelector('[data-privacy-notice]');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }
    const body = dialog.querySelector('[data-privacy-notice-body]');
    const checkbox = dialog.querySelector('[data-privacy-notice-checkbox]');
    const continueButton = dialog.querySelector('[data-privacy-notice-continue]');
    const cancelLink = dialog.querySelector('[data-privacy-notice-cancel]');
    const hint = dialog.querySelector('[data-privacy-notice-hint]');
    const form = dialog.closest('form');
    let readToEnd = checkbox.checked;
    let opener = null;

    const reachedEnd = () => body.scrollHeight - body.clientHeight - body.scrollTop <= 4;
    const update = () => {
        if (!readToEnd && dialog.open && reachedEnd()) {
            readToEnd = true;
        }
        checkbox.disabled = !readToEnd;
        continueButton.disabled = !(readToEnd && checkbox.checked);
        hint.hidden = readToEnd;
    };
    const open = () => {
        opener = document.activeElement;
        if (dialog.open) {
            dialog.close();
        }
        dialog.showModal();
        update();
        body.focus();
    };
    const leave = () => { window.location.assign(cancelLink.href); };

    body.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    checkbox.addEventListener('change', update);
    continueButton.addEventListener('click', () => {
        if (!checkbox.checked) {
            return;
        }
        dialog.close();
        const target = opener instanceof HTMLElement && opener !== document.body ? opener : form.querySelector('input:not([type="hidden"]):not([type="checkbox"]), select, textarea');
        target?.focus();
    });
    // Tab and Shift+Tab wrap inside the notice rather than leaving it for the browser toolbar.
    dialog.addEventListener('keydown', event => {
        if (event.key !== 'Tab' || !dialog.matches(':modal')) {
            return;
        }
        const focusable = [...dialog.querySelectorAll('a[href], button, input, [tabindex="0"]')].filter(element => !element.disabled && element.offsetParent !== null);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    });
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        leave();
    });
    document.querySelectorAll('[data-privacy-notice-open]').forEach(button => button.addEventListener('click', open));
    // Runs before the shared form handler, so a submission without consent reopens the notice instead.
    document.addEventListener('submit', event => {
        if (event.target === form && !checkbox.checked) {
            event.preventDefault();
            event.stopImmediatePropagation();
            open();
        }
    }, true);

    dialog.close();
    if (!checkbox.checked || dialog.querySelector('.field-error')) {
        open();
    } else {
        update();
    }
})();
