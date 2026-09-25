function escapePortalText(value = '') {
    return String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character]));
}

function portalSummaryList(rows) {
    return '<dl class="summary-list">' + rows.map(([label, value]) => `<dt>${escapePortalText(label)}</dt><dd>${escapePortalText(value || 'Wala')}</dd>`).join('') + '</dl>';
}

function renderConfirmationSummary(summary) {
    const rows = [['Dokumento', summary.document], ['Fee', summary.fee], ['Pangalan', summary.name], ['Address', summary.address], ['Purpose', summary.purpose]];
    document.getElementById('conf-summary').innerHTML = portalSummaryList(rows);
}

function renderReviewSummary(summary) {
    const rows = [['Dokumento', summary.document], ['Fee', summary.fee], ['Pangalan', summary.name], ['Address', summary.address], ['Email', summary.email], ['Purpose', summary.purpose]];
    if (summary.business !== null) rows.push(['Business name', summary.business]);
    rows.push(['Valid ID', summary.attachment]);
    document.getElementById('review-summary').innerHTML = portalSummaryList(rows);
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

const PORTAL_SCREENS = ['screen-terms', 'screen-doctype', 'screen-form', 'screen-attachment', 'screen-review', 'screen-confirm', 'screen-status'];

function validPortalScreen(id) {
    if (!PORTAL_SCREENS.includes(id)) return 'screen-terms';
    if (id === 'screen-status' || id === 'screen-terms') return id;
    if (lastCode) return 'screen-confirm';
    if (!document.getElementById('tnc-agree').checked) return 'screen-terms';
    if (id === 'screen-confirm') return 'screen-doctype';
    if (['screen-form', 'screen-attachment', 'screen-review'].includes(id) && !DOC_TYPES[selectedDocId]) return 'screen-doctype';
    return id;
}

function showScreen(id, recordHistory = true) {
    id = validPortalScreen(id);
    const screen = document.getElementById(id);
    if (!screen) return;
    const workflow = document.getElementById('request-flow');
    const wasHidden = workflow?.hidden || false;
    if (workflow) workflow.hidden = false;
    const previous = document.querySelector('.screen.active')?.id;
    document.querySelectorAll('.screen').forEach(element => element.classList.remove('active'));
    screen.classList.add('active');
    screen.setAttribute('tabindex', '-1');
    screen.focus({ preventScroll: true });
    document.querySelectorAll('.step.active').forEach(step => step.setAttribute('aria-current', 'step'));
    if (recordHistory && (previous !== id || wasHidden)) {
        try { window.history[id === 'screen-confirm' ? 'replaceState' : 'pushState']({ portalScreen: id }, ''); } catch (_) {}
    }
    const workflowTop = document.getElementById('request-flow')?.offsetTop || 0;
    window.scrollTo({ top: Math.max(0, workflowTop - 130), behavior: 'instant' });
}

function initializePortalNavigation() {
    const screen = document.getElementById('request-flow')?.hidden
        ? 'home'
        : validPortalScreen(document.querySelector('.screen.active')?.id);
    try { window.history.replaceState({ portalScreen: screen }, ''); } catch (_) {}
    window.addEventListener('popstate', event => {
        if (event.state?.portalScreen === 'home') {
            document.getElementById('request-flow').hidden = true;
            window.scrollTo({ top: 0, behavior: 'instant' });
            return;
        }
        showScreen(event.state?.portalScreen || 'screen-terms', false);
    });
}

async function copyReferenceCode() {
    if (!lastCode) return;
    try {
        await navigator.clipboard.writeText(lastCode);
        showCopiedReference();
        toast('Nakopya na ang reference code.', 'green');
    } catch (_) {
        const range = document.createRange();
        range.selectNodeContents(document.getElementById('conf-code'));
        const selection = window.getSelection();
        selection.removeAllRanges(); selection.addRange(range);
        toast('Naka-select ang code. Pindutin nang matagal at piliin ang Copy.', '');
    }
}

function showCopiedReference() {
    const button = document.querySelector('.copy-code-button');
    if (!button) return;
    button.classList.add('is-copied');
    button.textContent = 'Nakopya na';
    setTimeout(() => {
        button.classList.remove('is-copied');
        button.textContent = 'Kopyahin ang code';
    }, 2500);
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
    document.querySelector('[data-resident-theme]')?.setAttribute('aria-pressed', String(_portalDark));

    savePortalTheme();
}
