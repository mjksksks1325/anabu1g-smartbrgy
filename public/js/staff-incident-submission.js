(() => {
    const form = document.querySelector('[data-incident-submission-form]');
    form?.addEventListener('submit', event => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = 'true';
        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.textContent = 'Submitting report...';
        }
    });

    const select = document.querySelector('[data-incident-type-select]');
    const field = document.querySelector('[data-custom-incident-type]');
    const input = document.querySelector('[data-custom-incident-input]');
    if (!select || !field || !input) return;

    const update = () => {
        const custom = select.value === 'Iba pa';
        field.hidden = !custom;
        input.disabled = !custom;
        input.required = custom;
    };
    select.addEventListener('change', () => {
        update();
        if (!input.disabled) input.focus();
    });
    update();
})();
