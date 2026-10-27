(() => {
    const allowedExtensions = new Set(['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'avif', 'heic', 'heif']);
    const maximumSize = 5 * 1024 * 1024;
    const errorMessage = 'Choose an image file only (up to 5 MB). Documents are not accepted.';

    document.addEventListener('change', event => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || !input.matches('[data-resident-image-only]')) {
            return;
        }

        const error = document.getElementById(input.dataset.errorTarget);
        const file = input.files?.[0];
        if (!file) {
            if (error) {
                error.hidden = true;
            }
            input.removeAttribute('aria-invalid');
            return;
        }

        const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
        const validImage = allowedExtensions.has(extension)
            && (!file.type || file.type.startsWith('image/'))
            && file.type !== 'image/svg+xml'
            && file.size <= maximumSize;

        if (validImage) {
            if (error) {
                error.hidden = true;
            }
            input.removeAttribute('aria-invalid');
            return;
        }

        input.value = '';
        input.setAttribute('aria-invalid', 'true');
        if (error) {
            error.hidden = false;
            const message = error.querySelector('[data-portal-i18n]');
            if (message && typeof portalSetText === 'function') {
                portalSetText(message, errorMessage);
            } else if (message) {
                message.textContent = errorMessage;
            }
        }
    });
})();
