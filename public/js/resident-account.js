let residentIdentityGeneration = 0;

async function ensurePortalIdentity() {
    const config = window.RESIDENT_PORTAL;
    if (!config?.authenticated) {
        window.location.assign(config?.loginUrl || '/portal/login');
        return false;
    }
    const generation = ++residentIdentityGeneration;
    try {
        const response = await fetch(config.identityUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
        const identity = await response.json();
        if (generation !== residentIdentityGeneration) return false;
        if (!response.ok) {
            if (response.status === 401) window.location.assign(config.loginUrl);
            throw new Error(identity.message || 'Please contact the barangay for account assistance.');
        }
        for (const key of ['name', 'address', 'email', 'dob']) {
            const field = document.getElementById('f-' + key);
            if (field) { field.value = identity[key]; field.readOnly = true; }
        }
        return true;
    } catch (error) {
        if (generation !== residentIdentityGeneration) return false;
        const feedback = document.getElementById('portal-form-error');
        if (feedback) { feedback.textContent = error.message; feedback.hidden = false; }
        if (typeof toast === 'function') toast(error.message, 'red');
        return false;
    }
}

function clearResidentClientState() {
    residentIdentityGeneration++;
    try { sessionStorage.removeItem('smartbrgy_session'); } catch (_) {}
    if (typeof resetPortalSession === 'function') resetPortalSession();
    document.querySelectorAll('[data-resident-private]').forEach(element => element.replaceChildren());
    document.querySelectorAll('input:not([type="hidden"]), textarea').forEach(field => { field.value = ''; });
}

window.addEventListener('pagehide', clearResidentClientState);
window.addEventListener('pageshow', event => {
    document.querySelectorAll('[data-resident-form]').forEach(form => {
        form.removeAttribute('data-submitting');
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = false; if (button.dataset.label) button.textContent = button.dataset.label; });
    });
    if (event.persisted) { clearResidentClientState(); window.location.reload(); }
});

try {
    if (sessionStorage.getItem('smartbrgy_portal_theme') === 'dark') document.body.classList.add('dark-mode');
} catch (_) {}
const residentThemeButton = document.querySelector('[data-resident-theme]');
if (residentThemeButton) {
    residentThemeButton.setAttribute('aria-pressed', String(document.body.classList.contains('dark-mode')));
    residentThemeButton.addEventListener('click', () => {
        const dark = document.body.classList.toggle('dark-mode');
        residentThemeButton.setAttribute('aria-pressed', String(dark));
        try { sessionStorage.setItem('smartbrgy_portal_theme', dark ? 'dark' : 'light'); } catch (_) {}
    });
}
const residentMenuButton = document.querySelector('[data-menu-toggle]');
const residentNavigation = document.getElementById('site-nav');
if (residentMenuButton && residentNavigation) {
    const setResidentMenu = open => {
        residentMenuButton.setAttribute('aria-expanded', String(open));
        residentNavigation.classList.toggle('is-open', open);
    };
    document.body.classList.add('nav-collapsible');
    residentMenuButton.addEventListener('click', () => setResidentMenu(residentMenuButton.getAttribute('aria-expanded') !== 'true'));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && residentNavigation.classList.contains('is-open')) {
            setResidentMenu(false);
            residentMenuButton.focus();
        }
    });
}
const residentLogoutDialog = document.getElementById('resident-logout-dialog');
document.querySelector('[data-resident-logout]')?.addEventListener('click', () => residentLogoutDialog.showModal());
document.querySelector('[data-resident-cancel]')?.addEventListener('click', () => residentLogoutDialog.close());
residentLogoutDialog?.addEventListener('cancel', event => {
    if (residentLogoutDialog.querySelector('[data-submitting]')) event.preventDefault();
});
document.querySelectorAll('[data-resident-form]').forEach(form => {
    form.addEventListener('submit', event => {
        if (form.hasAttribute('data-submitting')) { event.preventDefault(); return; }
        if (!form.checkValidity()) return;
        form.setAttribute('data-submitting', 'true');
        form.querySelectorAll('button').forEach(button => { button.disabled = true; });
        const button = form.querySelector('button[type="submit"]');
        if (button) { button.dataset.label = button.textContent; button.textContent = 'Please wait...'; }
        if (form.hasAttribute('data-resident-logout-form')) clearResidentClientState();
    });
});
