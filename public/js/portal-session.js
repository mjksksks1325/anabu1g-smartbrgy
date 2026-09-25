let portalConfirmation = null;
const PORTAL_THEME_KEY = 'smartbrgy_portal_theme';

function savePortalTheme() {
    try {
        sessionStorage.setItem(PORTAL_THEME_KEY, _portalDark ? 'dark' : 'light');
    } catch (_) {}
}

function resetPortalSession() {
    newRequest(false);
    tncScrolled = false;
    const checkbox = document.getElementById('tnc-agree');
    checkbox.checked = false;
    checkbox.disabled = true;
    document.getElementById('btn-proceed-terms').disabled = true;
    document.getElementById('tnc-scroll').scrollTop = 0;
    const notice = document.getElementById('tnc-notice');
    notice.textContent = 'I-scroll hanggang dulo ng terms para ma-check ang kahon sa ibaba.';
    notice.classList.remove('is-read');
    for (const id of ['status-code', 'f-name', 'f-address', 'f-email', 'f-dob', 'f-purpose', 'f-business']) {
        const field = document.getElementById(id);
        field.value = '';
        setPortalFieldError(field, false);
        field.removeAttribute('aria-invalid');
    }
    for (const id of ['status-result', 'conf-summary', 'review-summary', 'conf-code', 'conf-code-mini', 'form-doc-label', 'form-doc-fee']) {
        document.getElementById(id).textContent = '';
    }
    document.getElementById('business-field').style.display = 'none';
    document.querySelectorAll('.cert-btn').forEach(button => button.setAttribute('aria-pressed', 'false'));
    showScreen('screen-terms', false);
    document.getElementById('request-flow').hidden = true;
    window.scrollTo({ top: 0, behavior: 'instant' });
    try { window.history.replaceState({ portalScreen: 'home' }, ''); } catch (_) {}
}

function initializePortalSession() {
    try {
        sessionStorage.removeItem(_SK);
        if (sessionStorage.getItem(PORTAL_THEME_KEY) === 'dark' && !_portalDark) togglePortalTheme();
    } catch (_) {}
    resetPortalSession();
    if (window.RESIDENT_PORTAL?.startRequest) {
        showScreen('screen-terms', false);
        try { window.history.replaceState({ portalScreen: 'screen-terms' }, ''); } catch (_) {}
    }
}

window.addEventListener('pageshow', event => {
    if (event.persisted) resetPortalSession();
});
