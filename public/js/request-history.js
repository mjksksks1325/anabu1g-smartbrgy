(() => {
    const history = document.querySelector('[data-request-history-url]');
    if (!history) return;

    let timer;
    let controller;
    let stopped = false;

    const schedule = delay => {
        clearTimeout(timer);
        if (!stopped && !document.hidden) timer = setTimeout(poll, delay);
    };

    async function poll() {
        if (stopped || document.hidden || controller) return;

        const rows = [...history.querySelectorAll('[data-request-id]')];
        if (!rows.length) return;

        controller = new AbortController();
        const activeController = controller;
        let delay = 5000;

        try {
            const url = new URL(history.dataset.requestHistoryUrl, window.location.href);
            rows.forEach(row => url.searchParams.append('ids[]', row.dataset.requestId));
            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
                signal: activeController.signal,
            });
            if (stopped || document.hidden) return;
            if (response.status === 401 || response.status === 403) {
                stopped = true;
                window.location.reload();
                return;
            }
            if (!response.ok) throw new Error('Request history is temporarily unavailable.');

            const payload = await response.json();
            if (stopped || document.hidden) return;
            rows.forEach(row => {
                const item = payload.items?.[row.dataset.requestId];
                if (item && item.version !== row.dataset.requestVersion) row.outerHTML = item.html;
            });
        } catch (error) {
            if (error.name !== 'AbortError') delay = 15000;
        } finally {
            if (controller === activeController) controller = null;
            schedule(delay);
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearTimeout(timer);
            controller?.abort();
        } else {
            schedule(0);
        }
    });
    window.addEventListener('pagehide', () => {
        stopped = true;
        clearTimeout(timer);
        controller?.abort();
    });
    schedule(5000);
})();
