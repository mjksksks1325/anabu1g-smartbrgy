(() => {
    const originalMessages = new WeakMap();
    const originalAttributes = new WeakMap();

    function adminMessage(source) {
        const catalogs = window.ADMIN_I18N?.catalogs;
        if (!catalogs) return { source };
        if (Object.hasOwn(catalogs.en ?? {}, source)) return { source };

        for (const locale of ['en', 'fil']) {
            for (const [key, translated] of Object.entries(catalogs[locale] ?? {})) {
                if (translated === source) return { source: key };
            }
        }

        return { source };
    }

    function adminTranslate(source) {
        const catalogs = window.ADMIN_I18N?.catalogs;
        return catalogs?.[window.ADMIN_I18N.locale]?.[source] ?? catalogs?.en?.[source] ?? source;
    }

    function translateAdminText(root = document) {
        if (!document.body?.hasAttribute('data-admin-localization') || !window.ADMIN_I18N?.catalogs) return;

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;

        while ((node = walker.nextNode())) {
            const parent = node.parentElement;
            if (!parent || parent.closest('script, style, textarea, [contenteditable="true"], [data-no-admin-translate]')) continue;

            let message = originalMessages.get(node);
            if (!message) {
                const text = node.nodeValue ?? '';
                const match = text.match(/^(\s*)([\s\S]*?)(\s*)$/);
                const content = match?.[2] ?? text;
                if (!content.trim()) continue;

                message = { ...adminMessage(content), leading: match?.[1] ?? '', trailing: match?.[3] ?? '' };
                originalMessages.set(node, message);
            }

            const value = message.leading + adminTranslate(message.source) + message.trailing;
            if (node.nodeValue !== value) node.nodeValue = value;
        }

        const elements = root.nodeType === Node.ELEMENT_NODE ? [root, ...root.querySelectorAll('[placeholder], [title], [aria-label], [alt]')] : [...root.querySelectorAll('[placeholder], [title], [aria-label], [alt]')];
        for (const element of elements) {
            if (element.matches('script, style, [data-no-admin-translate]')) continue;

            let messages = originalAttributes.get(element);
            if (!messages) {
                messages = new Map();
                originalAttributes.set(element, messages);
            }

            for (const name of ['placeholder', 'title', 'aria-label', 'alt']) {
                if (!element.hasAttribute(name)) continue;

                let message = messages.get(name);
                if (!message) {
                    const source = element.getAttribute(name) ?? '';
                    if (!source.trim()) continue;
                    message = adminMessage(source);
                    messages.set(name, message);
                }

                element.setAttribute(name, adminTranslate(message.source));
            }
        }
    }

    async function switchAdminLocale(locale, form) {
        const config = window.ADMIN_I18N;
        if (!config || !['en', 'fil'].includes(locale)) return;

        const buttons = form.querySelectorAll('[data-admin-locale]');
        const status = form.querySelector('.admin-locale-status');
        buttons.forEach(button => { button.disabled = true; });

        try {
            const token = document.querySelector('meta[name="csrf-token"]');
            const send = () => fetch(config.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token.content },
                body: JSON.stringify({ locale }),
            });

            let response = await send();
            if (response.status === 419) {
                const refreshed = await fetch(config.csrfUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
                if (!refreshed.ok) throw new Error('csrf');
                token.content = (await refreshed.json()).token;
                document.querySelectorAll('input[name="_token"]').forEach(field => { field.value = token.content; });
                response = await send();
            }

            const result = await response.json();
            if (!response.ok || result.locale !== locale) throw new Error('locale');

            config.locale = locale;
            document.documentElement.lang = locale;
            translateAdminText();
            buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.adminLocale === locale)));
            if (status) status.textContent = adminTranslate('Language updated.');
            document.dispatchEvent(new CustomEvent('admin:locale-changed', { detail: { locale } }));
        } catch (_) {
            if (status) status.textContent = adminTranslate('Could not change the language. Please try again.');
        } finally {
            buttons.forEach(button => { button.disabled = false; });
        }
    }

    document.addEventListener('submit', event => {
        const form = event.target.closest?.('[data-admin-language-form]');
        if (!form || !document.body?.hasAttribute('data-admin-localization')) return;

        event.preventDefault();
        switchAdminLocale(event.submitter?.value, form);
    });

    document.addEventListener('DOMContentLoaded', () => {
        if (!document.body.hasAttribute('data-admin-localization')) return;

        translateAdminText();
        new MutationObserver(records => {
            for (const record of records) {
                for (const addedNode of record.addedNodes) {
                    if (addedNode.nodeType === Node.ELEMENT_NODE) translateAdminText(addedNode);
                    else if (addedNode.nodeType === Node.TEXT_NODE) translateAdminText(addedNode.parentElement ?? document);
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    });

    document.addEventListener('admin:locale-changed', () => translateAdminText());
})();
