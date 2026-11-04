/* Shows the chosen photo before upload. resident-photo-input.js runs first and clears files that are not allowed. */
(() => {
    document.addEventListener('change', event => {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || !input.dataset.photoPreview) return;

        const preview = document.getElementById(input.dataset.photoPreview);
        if (!preview) return;
        const image = preview.querySelector('img');
        const file = input.files?.[0];
        if (image?.src.startsWith('blob:')) URL.revokeObjectURL(image.src);

        if (!file || !image) {
            preview.hidden = true;
            return;
        }
        image.src = URL.createObjectURL(file);
        preview.hidden = false;
    });
})();
