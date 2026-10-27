(() => {
    if (window.FormFeedback) return;

    const FIELD_SELECTOR = 'input:not([type="hidden"]):not([type="button"]):not([type="submit"]), select, textarea';
    const CONTAINER_SELECTOR = 'form, .modal, [role="dialog"], .modal-overlay';
    let lastInteraction = null;
    let pendingInvalid = null;
    let lastShownAt = 0;

    function translated(message) {
        return typeof window.adminTranslate === 'function' ? window.adminTranslate(message) : message;
    }

    function normalized(value) {
        return String(value ?? '').toLowerCase().replace(/\[\]$/, '').replace(/[^a-z0-9]/g, '');
    }

    function isVisible(field) {
        return Boolean(field && (field.offsetWidth || field.offsetHeight || field.getClientRects().length));
    }

    function openContainer() {
        const modal = [...document.querySelectorAll('.modal-overlay.show, .modal-overlay.open, dialog[open]')].pop();
        if (modal) return modal;
        const active = lastInteraction?.isConnected ? lastInteraction : document.activeElement;

        return active?.closest?.(CONTAINER_SELECTOR) ?? document;
    }

    function fieldLabel(field) {
        const group = field.closest('.form-group, .field, .gov-field, label');

        return group?.querySelector('.form-label, label, legend')?.textContent ?? '';
    }

    function isRequired(field) {
        return field.required || field.getAttribute('aria-required') === 'true' || /\*\s*$/.test(fieldLabel(field).trim());
    }

    function isEmpty(field) {
        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.name ? !field.form?.querySelector(`[name="${CSS.escape(field.name)}"]:checked`) && !field.checked : !field.checked;
        }
        if (field.type === 'file') return !field.files?.length;

        return String(field.value ?? '').trim() === '';
    }

    function findField(container, key) {
        const bracketKey = String(key).split('.').map((part, index) => (index === 0 ? part : `[${part}]`)).join('');
        for (const selector of [`[name="${key}"]`, `[name="${key}[]"]`, `[name="${bracketKey}"]`, `#${CSS.escape(key)}`, `[data-field="${key}"]`]) {
            const field = container.querySelector(selector);
            if (field) return field;
        }

        const target = normalized(String(key).split('.').pop());
        if (!target) return null;

        return [...container.querySelectorAll(FIELD_SELECTOR)].find(field => {
            const id = field.id ?? '';

            const suffix = normalized(id.slice(id.indexOf('-') + 1));

            return normalized(field.name) === target || normalized(id) === target || suffix === target || (suffix.length >= 5 && target.startsWith(suffix));
        }) ?? null;
    }

    function messageElement(field) {
        const id = `${field.id || field.name || 'field'}-feedback`.replace(/[^\w-]/g, '-');
        let element = document.getElementById(id);
        if (!element) {
            element = document.createElement('div');
            element.id = id;
            element.className = 'field-feedback-message';
            element.setAttribute('role', 'alert');
            (field.closest('.password-field, .input-group') ?? field).insertAdjacentElement('afterend', element);
        }

        return element;
    }

    function markInvalid(field, message) {
        field.classList.add('field-invalid');
        field.setAttribute('aria-invalid', 'true');
        if (message) {
            const element = messageElement(field);
            element.textContent = message;
            const describedBy = new Set((field.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean));
            describedBy.add(element.id);
            field.setAttribute('aria-describedby', [...describedBy].join(' '));
        }
    }

    function clearInvalid(field) {
        if (!field.classList.contains('field-invalid') && field.getAttribute('aria-invalid') !== 'true') return;
        field.classList.remove('field-invalid', 'field-shake');
        field.removeAttribute('aria-invalid');
        const element = document.getElementById(`${field.id || field.name || 'field'}-feedback`.replace(/[^\w-]/g, '-'));
        if (element?.classList.contains('field-feedback-message')) element.remove();
    }

    function focusFirst(fields) {
        const first = fields.find(isVisible);
        if (!first) return;

        const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        first.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
        first.focus({ preventScroll: true });
        fields.filter(isVisible).forEach(field => {
            field.classList.remove('field-shake');
            void field.offsetWidth;
            field.classList.add('field-shake');
        });
    }

    /**
     * Mark fields from a Laravel-style error bag ({ field: [messages] }) and bring the first one into view.
     * Required fields that are still empty are flagged too when no error key matches a field.
     */
    function showErrors(errors, container = openContainer()) {
        const fields = [];
        for (const [key, messages] of Object.entries(errors ?? {})) {
            const field = findField(container, key);
            if (field && !fields.includes(field)) {
                markInvalid(field, Array.isArray(messages) ? messages[0] : messages);
                fields.push(field);
            }
        }
        for (const field of missingFields(container)) {
            if (!fields.includes(field)) {
                markInvalid(field, translated('This field is required.'));
                fields.push(field);
            }
        }
        if (fields.length) lastShownAt = Date.now();
        focusFirst(orderedInDocument(fields, container));

        return fields;
    }

    function missingFields(container) {
        return [...container.querySelectorAll(FIELD_SELECTOR)].filter(field => !field.disabled && isVisible(field) && isRequired(field) && isEmpty(field));
    }

    function orderedInDocument(fields, container) {
        const all = [...container.querySelectorAll(FIELD_SELECTOR)];

        return [...fields].sort((first, second) => all.indexOf(first) - all.indexOf(second));
    }

    /**
     * Flag empty required fields (required attribute or a label ending in "*") in the open form or modal.
     * A single flagged field shows the given message; several show a generic required message.
     */
    function flagMissing(message = '', container = openContainer()) {
        const fields = missingFields(container);
        fields.forEach(field => markInvalid(field, fields.length === 1 && message ? message : translated('This field is required.')));
        if (fields.length) lastShownAt = Date.now();
        focusFirst(fields);

        return fields;
    }

    /**
     * Called before showing an error notification. Returns true when the error is already shown on the
     * form fields (just now from a server response, or by flagging empty required fields), so the caller
     * can skip the notification.
     */
    function handledOnFields(message = '') {
        if (Date.now() - lastShownAt < 1500) return true;

        return flagMissing(message).length > 0;
    }

    document.addEventListener('pointerdown', event => { lastInteraction = event.target; }, true);
    document.addEventListener('submit', event => { lastInteraction = event.submitter ?? event.target; }, true);

    document.addEventListener('invalid', event => {
        const field = event.target;
        markInvalid(field, field.validationMessage);
        if (!pendingInvalid) {
            pendingInvalid = [];
            requestAnimationFrame(() => {
                focusFirst(pendingInvalid);
                pendingInvalid = null;
            });
        }
        pendingInvalid.push(field);
    }, true);

    const clearOnEdit = event => {
        if (event.target.matches?.(FIELD_SELECTOR)) clearInvalid(event.target);
    };
    document.addEventListener('input', clearOnEdit, true);
    document.addEventListener('change', clearOnEdit, true);
    document.addEventListener('animationend', event => event.target.classList?.remove('field-shake'));

    if (typeof window.fetch === 'function') {
        const originalFetch = window.fetch.bind(window);
        window.fetch = async (...args) => {
            const response = await originalFetch(...args);
            if (response.status === 422 && (response.headers.get('content-type') ?? '').includes('json')) {
                const container = openContainer();
                try {
                    const payload = await response.clone().json();
                    if (payload?.errors) showErrors(payload.errors, container);
                } catch (_) {}
            }

            return response;
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
        const serverErrors = [...document.querySelectorAll('[aria-invalid="true"], .is-invalid')].filter(field => field.matches(FIELD_SELECTOR));
        serverErrors.forEach(field => field.classList.add('field-invalid'));
        focusFirst(serverErrors);

        let flagged = null;
        new MutationObserver(records => {
            for (const { target: field } of records) {
                if (!field.matches(FIELD_SELECTOR)) continue;
                if (field.getAttribute('aria-invalid') !== 'true') {
                    field.classList.remove('field-invalid');
                    continue;
                }
                if (field.classList.contains('field-invalid')) continue;
                field.classList.add('field-invalid');
                if (!flagged) {
                    flagged = [];
                    requestAnimationFrame(() => {
                        focusFirst(flagged);
                        flagged = null;
                    });
                }
                flagged.push(field);
            }
        }).observe(document.body, { attributes: true, attributeFilter: ['aria-invalid'], subtree: true });
    });

    window.FormFeedback = { showErrors, flagMissing, handledOnFields, markInvalid, clearInvalid };
})();
