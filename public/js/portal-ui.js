function escapePortalText(value = '') {
    return String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
}

function renderConfirmationSummary(summary) {
    const rows = [['Pangalan', summary.name], ['Dokumento', summary.document], ['Bayad', summary.fee], ['Address', summary.address], ['Layunin', summary.purpose]];
    document.getElementById('conf-summary').innerHTML = '<div><strong>Buod ng Request</strong><br>' + rows.map(([label, value]) => `${label}: <strong>${escapePortalText(value || 'N/A')}</strong>`).join('<br>') + '</div>';
}

function toast(msg, type='') {
    const wrap = document.getElementById('toast-wrap');
    const t = document.createElement('div');

    t.className = 'toast ' + type;
    t.textContent = msg;

    wrap.appendChild(t);

    setTimeout(() => {
        t.style.opacity = '0';
        t.style.transition = 'opacity .3s';

        setTimeout(() => t.remove(), 300);
    }, 3500);
}

function setLoading(v) {
    document.getElementById('loader').style.display = v ? 'flex' : 'none';
}

const PORTAL_SCREENS = ['screen-terms', 'screen-doctype', 'screen-form', 'screen-attachment', 'screen-confirm', 'screen-status'];

function validPortalScreen(id) {
    if (!PORTAL_SCREENS.includes(id)) return 'screen-terms';
    if (id === 'screen-status' || id === 'screen-terms') return id;
    if (lastCode) return 'screen-confirm';
    if (!document.getElementById('tnc-agree').checked) return 'screen-terms';
    if (id === 'screen-confirm') return 'screen-doctype';
    if (['screen-form', 'screen-attachment'].includes(id) && !DOC_TYPES[selectedDocId]) return 'screen-doctype';
    return id;
}

function showScreen(id, recordHistory = true) {
    id = validPortalScreen(id);
    const screen = document.getElementById(id);
    if (!screen) return;
    const previous = document.querySelector('.screen.active')?.id;
    document.querySelectorAll('.screen').forEach(element => element.classList.remove('active'));
    screen.classList.add('active');
    screen.setAttribute('tabindex', '-1');
    screen.focus({ preventScroll: true });
    document.querySelectorAll('.step.active').forEach(step => step.setAttribute('aria-current', 'step'));
    if (recordHistory && previous !== id) {
        try { window.history[id === 'screen-confirm' ? 'replaceState' : 'pushState']({ portalScreen: id }, ''); } catch (_) {}
    }
    window.scrollTo({ top: 0, behavior: 'instant' });
    _saveSession();
}

function initializePortalNavigation() {
    const screen = validPortalScreen(document.querySelector('.screen.active')?.id);
    try { window.history.replaceState({ portalScreen: screen }, ''); } catch (_) {}
    window.addEventListener('popstate', event => showScreen(event.state?.portalScreen || 'screen-terms', false));
}

async function copyReferenceCode() {
    if (!lastCode) return;
    try {
        await navigator.clipboard.writeText(lastCode);
        toast('Nakopya na ang reference code.', 'green');
    } catch (_) {
        const range = document.createRange();
        range.selectNodeContents(document.getElementById('conf-code'));
        const selection = window.getSelection();
        selection.removeAllRanges(); selection.addRange(range);
        toast('Naka-select ang code. Pindutin nang matagal at piliin ang Copy.', '');
    }
}

function goBack(screenId) {
    showScreen(screenId);
}

function goToStatus() {
    document.getElementById('status-code').value = lastCode;
    showScreen('screen-status');
}

function togglePortalTheme() {
    _portalDark = !_portalDark;

    document.body.classList.toggle('dark-mode', _portalDark);

    const icon = document.getElementById('portal-theme-icon');
    const label = document.getElementById('portal-theme-label');

    if (icon) {
        icon.textContent = _portalDark ? '☀️' : '🌙';
    }

    if (label) {
        label.textContent = _portalDark ? 'Light Mode' : 'Dark Mode';
    }

    _saveSession();
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.cert-btn, #att-dropzone').forEach(element => {
    element.setAttribute('role', 'button');
    element.tabIndex = 0;
    element.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); element.click(); }
    });
  });
});
