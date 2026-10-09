/* Interface copy only. Forms and uploads remain in their original DOM nodes. */
const portalLocaleState = { locale: window.PORTAL_I18N?.locale || 'en', messages: new WeakMap() };

function portalT(source, replacements = {}) {
    const catalog = window.PORTAL_I18N?.catalogs;
    let text = catalog?.[portalLocaleState.locale]?.[source] ?? catalog?.en?.[source] ?? source;
    for (const [name, value] of Object.entries(replacements)) text = text.replaceAll(':' + name, ['attribute', 'other', 'document', 'fee'].includes(name) ? portalT(String(value)) : String(value));
    return text;
}

function portalMessage(source) {
    const catalogs = window.PORTAL_I18N?.catalogs;
    if (!catalogs) return { source, replacements: {} };
    if (Object.hasOwn(catalogs.en, source)) return { source, replacements: {} };
    for (const locale of ['en', 'fil']) {
        for (const [key, template] of Object.entries(catalogs[locale])) {
            if (template === source) return { source: key, replacements: {} };
            if (!template.includes(':')) continue;
            const names = [];
            const pattern = template.split(/(:[a-z_]+)/g).map(part => {
                if (/^:[a-z_]+$/.test(part)) { names.push(part.slice(1)); return '(.+?)'; }
                return part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }).join('');
            const match = source.match(new RegExp('^' + pattern + '$'));
            if (match) return { source: key, replacements: Object.fromEntries(names.map((name, index) => [name, match[index + 1]])) };
        }
    }
    return { source, replacements: {} };
}

function portalSetText(element, source, replacements = {}) {
    if (!element) return;
    const message = portalMessage(source);
    if (!Object.keys(replacements).length) replacements = message.replacements;
    source = message.source;
    portalLocaleState.messages.set(element, { source, replacements });
    element.setAttribute('data-portal-i18n', source);
    element.textContent = portalT(source, replacements);
}

function portalSetAttribute(element, name, source) {
    element.setAttribute('data-portal-i18n-' + name, source);
    element.setAttribute(name, portalT(source));
}

function portalClearText(element) {
    portalLocaleState.messages.delete(element);
    element.removeAttribute('data-portal-i18n');
    element.textContent = '';
}

function portalSetHtml(element, html) {
    portalClearText(element);
    element.innerHTML = html;
}

function portalHtml(source, replacements = {}) {
    const message = portalMessage(source);
    source = message.source;
    if (!Object.keys(replacements).length) replacements = message.replacements;
    const escape = value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
    return `<span data-portal-i18n="${escape(source)}" data-portal-params="${escape(JSON.stringify(replacements))}">${escape(portalT(source, replacements))}</span>`;
}

function updatePortalValidity(field) {
    if (!field.setCustomValidity) return;
    field.setCustomValidity('');
    const fieldNames = { 'f-name': 'full name', 'f-address': 'Address', 'f-email': 'email', 'f-dob': 'date of birth', 'f-purpose': 'purpose', 'f-business': 'business name', 'f-attachment': 'attachment', 'status-code': 'Reference number', 'tnc-agree': 'Terms and Conditions' };
    const attribute = portalT(field.name?.replaceAll('_', ' ') || fieldNames[field.id] || field.id);
    const replacements = { attribute, min: field.minLength, max: field.maxLength, date: field.max || field.min };
    const validity = field.validity;
    const message = validity.valueMissing ? 'The :attribute field is required.'
        : validity.typeMismatch ? 'The :attribute field must be a valid email address.'
        : validity.tooShort ? 'The :attribute field must be at least :min characters.'
        : validity.tooLong ? 'The :attribute field must not be greater than :max characters.'
        : validity.rangeOverflow ? 'The :attribute field must be a date before or equal to :date.'
        : validity.patternMismatch || validity.badInput ? 'The :attribute field format is invalid.' : '';
    if (message) field.setCustomValidity(portalT(message, replacements));
}

function translatePortal(root = document) {
    if (!window.PORTAL_I18N?.catalogs) return;
    const update = element => {
        if (element.hasAttribute('data-portal-message') && !portalLocaleState.messages.has(element)) portalSetText(element, element.textContent.trim());
        if (element.hasAttribute('data-portal-i18n')) {
            const message = portalLocaleState.messages.get(element);
            let replacements = message?.replacements || {};
            try { replacements = JSON.parse(element.dataset.portalParams || '{}'); } catch (_) {}
            if (message) replacements = message.replacements;
            const value = portalT(message?.source || element.dataset.portalI18n, replacements);
            if (element.textContent !== value) element.textContent = value;
        }
        for (const name of ['placeholder', 'title', 'aria-label', 'alt']) {
            const source = element.getAttribute('data-portal-i18n-' + name);
            if (source) {
                const value = portalT(source);
                if (element.getAttribute(name) !== value) element.setAttribute(name, value);
            }
        }
    };
    if (root.nodeType === 1) update(root);
    root.querySelectorAll('[data-portal-i18n], [data-portal-message], [data-portal-i18n-placeholder], [data-portal-i18n-title], [data-portal-i18n-aria-label], [data-portal-i18n-alt]').forEach(update);
}

async function switchPortalLocale(locale) {
    const config = window.PORTAL_I18N;
    if (!config || !['en', 'fil'].includes(locale)) return;
    const buttons = document.querySelectorAll('[data-portal-locale]');
    buttons.forEach(button => { button.disabled = true; });
    const status = document.getElementById('portal-language-status');
    try {
        if (!config.catalogs) throw new Error('catalog');
        const token = document.querySelector('meta[name="csrf-token"]');
        const send = () => fetch(config.url, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token.content }, body: JSON.stringify({ locale }) });
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
        portalLocaleState.locale = locale;
        config.locale = locale;
        document.documentElement.lang = locale;
        translatePortal();
        document.querySelectorAll('input:not([type="hidden"]), textarea, select').forEach(updatePortalValidity);
        buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.portalLocale === locale)));
        portalSetText(status, 'Language updated.');
        document.dispatchEvent(new CustomEvent('portal:locale-changed', { detail: { locale } }));
    } catch (_) {
        portalSetText(status, 'Could not change the language. Please try again.');
    } finally {
        buttons.forEach(button => { button.disabled = false; });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.PORTAL_I18N) return;
    const title = document.querySelector('title');
    if (title) {
        const suffix = ' | Barangay Anabu I-G';
        const message = portalMessage(title.textContent.replace(suffix, ''));
        document.addEventListener('portal:locale-changed', () => { title.textContent = portalT(message.source) + suffix; });
    }
    translatePortal();
    document.querySelectorAll('input:not([type="hidden"]), textarea, select').forEach(field => {
        field.addEventListener('invalid', () => updatePortalValidity(field));
        field.addEventListener('input', () => field.setCustomValidity(''));
        field.addEventListener('change', () => field.setCustomValidity(''));
    });
    document.querySelector('[data-portal-language-form]')?.addEventListener('submit', event => {
        event.preventDefault();
        switchPortalLocale(event.submitter?.value);
    });
    new MutationObserver(records => {
        for (const record of records) for (const node of record.addedNodes) if (node.nodeType === 1) translatePortal(node);
    }).observe(document.body, { childList: true, subtree: true });
});
