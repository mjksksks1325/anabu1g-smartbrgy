(() => {
  let restoringHistory = false;
  let activeStylesLayout = null;
  const stylesheetLoadListeners = new WeakSet();
  const loadedStylesheets = new WeakSet();
  let navigationLoadingTimer = null;
  let navigationLoadingFailsafe = null;

  function showNavigationLoading() {
    if (document.getElementById('staff-navigation-loading')) return;

    const indicator = document.createElement('div');
    const style = document.createElement('style');
    const progress = document.createElement('span');

    indicator.id = 'staff-navigation-loading';
    indicator.setAttribute('role', 'progressbar');
    indicator.setAttribute('aria-label', document.documentElement.lang.toLowerCase().startsWith('fil')
      ? 'Inihahanda ang pahina'
      : 'Loading page');
    const isDark = document.documentElement.dataset.theme === 'dark';
    indicator.style.cssText = `position:fixed;top:0;left:0;right:0;height:3px;z-index:2147483000;overflow:hidden;pointer-events:none;background:${isDark ? 'rgba(232,242,250,.12)' : 'rgba(16,36,58,.12)'};`;
    style.textContent = '@keyframes staff-navigation-progress{0%{transform:translateX(-110%)}100%{transform:translateX(370%)}}';
    progress.setAttribute('aria-hidden', 'true');
    progress.style.cssText = `display:block;width:30%;height:100%;border-radius:0 3px 3px 0;background:${isDark ? '#65b7ff' : '#0759b5'};animation:staff-navigation-progress 1.1s ease-in-out infinite;`;
    indicator.append(style, progress);
    // Livewire replaces the entire body during SPA navigation. Keep this
    // non-blocking indicator outside it so it survives that swap and paints
    // over any transient unstyled frame.
    document.documentElement.append(indicator);
  }

  function hideNavigationLoading() {
    window.clearTimeout(navigationLoadingTimer);
    window.clearTimeout(navigationLoadingFailsafe);
    navigationLoadingTimer = null;
    navigationLoadingFailsafe = null;
    document.getElementById('staff-navigation-loading')?.remove();
  }

  function stylesheetIsLoaded(link) {
    if (loadedStylesheets.has(link)) return true;

    try {
      // `link.sheet` may exist while the browser is still fetching and parsing it.
      // A parsed rule list prevents disabling the active layout too early.
      if (link.sheet && link.sheet.cssRules.length > 0) {
        loadedStylesheets.add(link);
        return true;
      }
    } catch (_) {
      // Cross-origin sheets do not expose cssRules; this app's workspace sheets
      // are same-origin, so treat inaccessible sheets as not ready here.
    }

    return false;
  }

  function setTheme(theme) {
    theme = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.theme = theme;
    document.documentElement.classList.toggle('dark', theme === 'dark');
    if (document.body.dataset.personnelLayout === 'admin') {
      document.body.classList.toggle('light-mode', theme === 'light');
    }
    try { localStorage.setItem('smartbrgy_theme', theme); } catch (_) {}

    const label = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
    document.querySelectorAll('#theme-toggle, #iot-theme-toggle').forEach(button => {
      button.setAttribute('aria-label', label);
      button.title = label;
    });
    const themeLabel = document.getElementById('theme-label');
    if (themeLabel) themeLabel.textContent = label;
    return theme;
  }

  function toggleTheme() {
    const currentTheme = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
    return setTheme(currentTheme === 'dark' ? 'light' : 'dark');
  }

  function syncStyles() {
    const layout = document.body.dataset.personnelLayout;
    if (!layout) return true;

    const stylesByLayout = {
      'admin.css': 'admin',
      'figma-admin.css': 'admin',
      'admin-iot.css': 'iot',
      'figma-iot.css': 'iot',
    };

    const stylesheetLinks = [...document.querySelectorAll('link[rel="stylesheet"]')];
    const ownedStylesheets = stylesheetLinks.map(link => {
      const pathname = new URL(link.href, window.location.href).pathname;
      const owner = stylesByLayout[pathname.split('/').pop()]
        || (pathname.includes('/build/') ? 'settings' : null);

      return { link, owner };
    });

    const targetStylesheets = ownedStylesheets.filter(item => item.owner === layout);
    const pendingStylesheets = targetStylesheets.filter(({ link }) => !loadedStylesheets.has(link));
    const targetStylesReady = !pendingStylesheets.some(({ link }) => !stylesheetIsLoaded(link));

    // Livewire replaces the body before newly added stylesheets finish loading.
    // Keep the active layout's CSS enabled until every incoming layout sheet parses.
    if (!targetStylesReady) {
      ownedStylesheets.forEach(({ link, owner }) => {
        if (owner === layout || owner === activeStylesLayout || activeStylesLayout === null) {
          link.disabled = false;
        } else if (owner) {
          link.disabled = true;
        }
      });

      pendingStylesheets.forEach(({ link }) => {
        if (stylesheetIsLoaded(link)) return;
        if (stylesheetLoadListeners.has(link)) return;
        stylesheetLoadListeners.add(link);
        const synchronizeAfterStylesheetLoad = () => {
          loadedStylesheets.add(link);
          if (document.body.dataset.personnelLayout === layout && syncStyles()) hideNavigationLoading();
        };
        link.addEventListener('load', synchronizeAfterStylesheetLoad, { once: true });
        link.addEventListener('error', () => {
          stylesheetLoadListeners.delete(link);
          hideNavigationLoading();
        }, { once: true });
      });
    } else {
      activeStylesLayout = layout;
      ownedStylesheets.forEach(({ link, owner }) => {
        if (owner) link.disabled = owner !== layout;
        else if (new URL(link.href, window.location.href).pathname.includes('/build/')) {
          link.disabled = layout === 'admin' || layout === 'iot';
        }
      });
    }

    let theme;
    try { theme = localStorage.getItem('smartbrgy_theme') === 'dark' ? 'dark' : 'light'; }
    catch (_) { theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'; }
    setTheme(theme);
    return targetStylesReady;
  }

  window.PersonnelNavigation = { setTheme, toggleTheme, syncStyles };
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
    if (event.target.closest('#iot-theme-toggle')) {
      toggleTheme();
    }
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
    window.clearTimeout(navigationLoadingTimer);
    navigationLoadingTimer = window.setTimeout(() => {
      if (event.defaultPrevented) return;
      showNavigationLoading();
      navigationLoadingFailsafe = window.setTimeout(hideNavigationLoading, 60000);
    }, 120);
  });
  document.addEventListener('livewire:navigating', event => {
    // Livewire fires this after fetching the destination and immediately before
    // swapping the body. Keep the current page visible while fetching, then
    // cover the swap so an incoming layout stylesheet cannot expose a blank page.
    window.clearTimeout(navigationLoadingTimer);
    showNavigationLoading();
    window.clearTimeout(navigationLoadingFailsafe);
    navigationLoadingFailsafe = window.setTimeout(hideNavigationLoading, 60000);
    // Livewire replaces the whole body during navigation. Re-attach the cover
    // synchronously after that replacement, before the browser can paint it.
    event.detail?.onSwap?.(() => showNavigationLoading());
  });
  document.addEventListener('livewire:navigated', () => {
    if (restoringHistory) window.PERSONNEL_LOGIN_GREETING = false;
    restoringHistory = false;
    if (syncStyles()) hideNavigationLoading();
    else showNavigationLoading();
  });
  document.addEventListener('DOMContentLoaded', syncStyles, { once: true });
})();
