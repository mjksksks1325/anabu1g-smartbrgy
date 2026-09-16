function toast(msg, type='') {
    const wrap = document.getElementById('toast-wrap');
    const t = document.createElement('div');

    t.className = 'toast ' + type;
    t.innerHTML = `<span>${type==='green'?'✅':type==='red'?'❌':'ℹ️'}</span><span>${msg}</span>`;

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

function showScreen(id) {
    document.querySelectorAll('.screen').forEach(s =>
        s.classList.remove('active')
    );

    document.getElementById(id).classList.add('active');

    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });

    _saveSession();
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