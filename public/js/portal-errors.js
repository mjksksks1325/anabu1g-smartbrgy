/*
 * Links each server-side error in the summary to its field. The summary is announced as an alert;
 * form-feedback.js already moves focus to the first invalid field, so focus is left to it.
 */
(() => {
    const summary = document.querySelector('[data-error-summary]');
    if (!summary) return;

    summary.querySelectorAll('[data-error-field]').forEach(link => {
        const name = link.dataset.errorField;
        const field = document.querySelector(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`);
        if (!field) return;
        if (field.id) link.setAttribute('href', '#' + field.id);
        link.addEventListener('click', event => {
            event.preventDefault();
            field.scrollIntoView({ block: 'center' });
            field.focus({ preventScroll: true });
        });
    });
})();
