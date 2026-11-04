(() => {
    document.querySelectorAll('[data-copy-reference]').forEach(button => {
        if (!navigator.clipboard) return;

        const label = button.querySelector('[data-portal-i18n]');
        let resetTimer;
        button.hidden = false;
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copyReference);
                portalSetText(label, 'Nakopya na');
                clearTimeout(resetTimer);
                resetTimer = setTimeout(() => portalSetText(label, 'Kopyahin ang code'), 2500);
            } catch (_) {
                button.hidden = true;
            }
        });
    });
})();
