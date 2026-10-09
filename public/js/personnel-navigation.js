(() => {
  let restoringHistory = false;
  function syncStyles() {
    const layout = document.body.dataset.personnelLayout;
    const stylesByLayout = {
      'admin.css': 'admin',
      'figma-admin.css': 'admin',
      'admin-iot.css': 'iot',
      'figma-iot.css': 'iot',
    };

    document.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
      const pathname = new URL(link.href, window.location.href).pathname;
      const owner = stylesByLayout[pathname.split('/').pop()];
      if (owner) link.disabled = owner !== layout;
      else if (pathname.includes('/build/')) link.disabled = layout === 'admin' || layout === 'iot';
    });

    if (!layout) return;
    let theme;
    try { theme = localStorage.getItem('smartbrgy_theme') === 'dark' ? 'dark' : 'light'; }
    catch (_) { theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'; }
    document.documentElement.dataset.theme = theme;
    document.documentElement.classList.toggle('dark', theme === 'dark');
    if (layout === 'admin') document.body.classList.toggle('light-mode', theme === 'light');
  }

  window.PersonnelNavigation = { syncStyles };
  let logoutTrigger;
  document.addEventListener('submit', event => {
    if (!event.target.matches('form[data-personnel-logout]')) return;
    const dialog = document.getElementById('logout-dialog');
    if (!dialog?.showModal) return;
    event.preventDefault();
    logoutTrigger = event.target.closest('details')?.querySelector('summary') || document.activeElement;
    event.target.closest('details')?.removeAttribute('open');
    dialog.showModal();
    dialog.addEventListener('close', () => logoutTrigger?.focus(), { once: true });
  });
  document.addEventListener('click', event => {
    if (event.target.closest('[data-logout-cancel]')) {
      document.getElementById('logout-dialog')?.close();
    }
    document.querySelectorAll('details.personnel-account-menu[open]').forEach(menu => {
      if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('details.personnel-account-menu[open]').forEach(menu => {
      menu.removeAttribute('open');
      menu.querySelector('summary')?.focus();
    });
  });
  document.addEventListener('livewire:navigate', event => {
    restoringHistory = event.detail?.history === true;
  });
  document.addEventListener('livewire:navigated', () => {
    if (restoringHistory) window.PERSONNEL_LOGIN_GREETING = false;
    restoringHistory = false;
    syncStyles();
  });
})();
