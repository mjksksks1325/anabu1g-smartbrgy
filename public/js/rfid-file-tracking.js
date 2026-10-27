(() => {
    let searchTimer;

    document.addEventListener('input', (event) => {
        const input = event.target;
        const form = input.closest?.('[data-rfid-auto-filter]');

        if (!form || input.type !== 'search') {
            return;
        }

        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => form.requestSubmit(), 350);
    });

    document.addEventListener('change', (event) => {
        const input = event.target;
        const form = input.closest?.('[data-rfid-auto-filter]');

        if (!form || !(input.matches('select') || input.type === 'date')) {
            return;
        }

        window.clearTimeout(searchTimer);
        form.requestSubmit();
    });
})();
