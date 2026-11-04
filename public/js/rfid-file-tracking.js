(() => {
    let searchTimer;
    let archiveUrl = null;

    document.addEventListener('click', (event) => {
        const archiveButton = event.target.closest?.('[data-folder-archive]');
        if (archiveButton) {
            const dialog = document.getElementById('cabinet-folder-archive-dialog');
            const form = dialog?.querySelector('[data-folder-archive-form]');
            if (!dialog || !form) {
                return;
            }

            archiveUrl = archiveButton.dataset.archiveUrl;
            form.reset();
            dialog.querySelector('[data-folder-archive-reference]').textContent = archiveButton.dataset.fileReference || '';
            const error = dialog.querySelector('[data-folder-archive-error]');
            error.hidden = true;
            error.textContent = '';
            dialog.showModal();
            dialog.querySelector('[name="super_admin_password"]').focus();
            return;
        }

        if (event.target.closest?.('[data-folder-archive-cancel]')) {
            document.getElementById('cabinet-folder-archive-dialog')?.close();
        }
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest?.('[data-folder-archive-form]');
        if (!form) {
            return;
        }

        event.preventDefault();
        if (!archiveUrl) {
            return;
        }

        const submitButton = form.querySelector('[data-folder-archive-submit]');
        const passwordInput = form.querySelector('[name="super_admin_password"]');
        const error = form.querySelector('[data-folder-archive-error]');
        submitButton.disabled = true;
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
                body: JSON.stringify({ super_admin_password: passwordInput.value }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                error.textContent = payload.message || 'Hindi na-archive ang folder. Pakisubukan ulit.';
                error.hidden = false;
                passwordInput.focus();
                return;
            }

            window.location.reload();
        } catch {
            error.textContent = 'Hindi makakonekta sa server. Walang binagong folder.';
            error.hidden = false;
        } finally {
            submitButton.disabled = false;
        }
    });

    let filterVersion = 0;

    /**
     * Load filtered results and swap only the results region, so the search field keeps its focus and cursor.
     * Falls back to an ordinary page load if the request fails.
     */
    async function loadFilteredResults(url, section) {
        const version = ++filterVersion;
        section.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
            if (!response.ok) {
                throw new Error('filter');
            }
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (version !== filterVersion) {
                return;
            }
            const region = page.querySelector('[data-filter-region]');
            if (!region) {
                throw new Error('filter');
            }
            section.querySelector('[data-filter-region]')?.replaceWith(region);
            const count = page.querySelector('[data-filter-count]');
            const currentCount = section.querySelector('[data-filter-count]');
            if (count && currentCount) {
                currentCount.textContent = count.textContent;
            }
            window.history.replaceState(window.history.state, '', url);
        } catch {
            window.location.href = url;
        } finally {
            if (version === filterVersion) {
                section.setAttribute('aria-busy', 'false');
            }
        }
    }

    function filterUrl(form) {
        // getAttribute, not form.action: the RFID form has a field named "action" that would shadow it.
        const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        new FormData(form).forEach((value, key) => {
            if (String(value).trim() !== '') {
                url.searchParams.set(key, String(value).trim());
            }
        });

        return url.toString();
    }

    function applyFilters(form) {
        const section = form.closest('[data-filter-results]');
        if (!section) {
            form.requestSubmit();
            return;
        }
        void loadFilteredResults(filterUrl(form), section);
    }

    document.addEventListener('submit', (event) => {
        const form = event.target.closest?.('[data-auto-filter]');
        if (!form || !form.closest('[data-filter-results]')) {
            return;
        }
        event.preventDefault();
        window.clearTimeout(searchTimer);
        applyFilters(form);
    });

    document.addEventListener('input', (event) => {
        const input = event.target;
        const form = input.closest?.('[data-auto-filter]');

        if (!form || input.type !== 'search') {
            return;
        }

        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => applyFilters(form), 350);
    });

    document.addEventListener('change', (event) => {
        const input = event.target;
        const form = input.closest?.('[data-auto-filter]');

        if (!form || !(input.matches('select') || input.type === 'date')) {
            return;
        }

        window.clearTimeout(searchTimer);
        applyFilters(form);
    });

    // Pager links inside a filtered region load in place too.
    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('[data-filter-results] [data-filter-region] .staff-pager a[href]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey) {
            return;
        }
        event.preventDefault();
        void loadFilteredResults(link.href, link.closest('[data-filter-results]'));
    });
})();
