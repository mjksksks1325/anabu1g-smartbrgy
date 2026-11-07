(() => {
  let restoringHistory = false;
  let activeStylesLayout = null;
  let activeStaffUi = false;
  const STAFF_UI = 'staff-ui';
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
    progress.style.cssText = `display:block;width:30%;height:100%;border-radius:0 3px 3px 0;background:${isDark ? '#9edbb5' : '#17663a'};animation:staff-navigation-progress 1.1s ease-in-out infinite;`;
    indicator.append(style, progress);
    // Livewire replaces the entire body during SPA navigation. Keep this
    // non-blocking indicator outside it so it survives that swap and paints
    // over any transient unstyled frame.
    document.documentElement.append(indicator);
  }

  /*
   * The staff menu keeps its scroll position from page to page. Livewire replaces the whole body when a menu link is
   * followed (and some pages open with a full page load), so the new menu would otherwise start at the top. The saved
   * position is restored once the page's styles are in place, and the menu moves only if the current item would be out
   * of view. On phones the menu is a closed drawer with no scroll area, so nothing changes there.
   */
  const NAV_SCROLL_KEY = 'smartbrgy_nav_scroll';
  let navScrollPending = false;

  function navScroller() {
    for (const element of document.querySelectorAll('#app .sidebar-scroll, #staff-side-nav .side-nav-scroll, .staff-settings-navigation')) {
      for (let node = element; node && node !== document.body; node = node.parentElement) {
        const overflow = getComputedStyle(node).overflowY;
        if ((overflow === 'auto' || overflow === 'scroll') && node.scrollHeight > node.clientHeight) return node;
      }
    }
    return null;
  }

  function rememberNavScroll() {
    const scroller = navScroller();
    if (!scroller) return;
    try { sessionStorage.setItem(NAV_SCROLL_KEY, String(Math.round(scroller.scrollTop))); } catch (_) {}
  }

  function restoreNavScroll() {
    navScrollPending = false;
    const scroller = navScroller();
    if (!scroller) return;
    let saved = null;
    try { saved = sessionStorage.getItem(NAV_SCROLL_KEY); } catch (_) {}
    if (saved !== null) scroller.scrollTop = Number(saved) || 0;
    const current = scroller.querySelector('[aria-current="page"]');
    if (!current) return;
    const item = current.getBoundingClientRect();
    const area = scroller.getBoundingClientRect();
    if (item.top < area.top) scroller.scrollTop -= area.top - item.top + 8;
    else if (item.bottom > area.bottom) scroller.scrollTop += item.bottom - area.bottom + 8;
  }

  function finishNavigation() {
    hideNavigationLoading();
    if (navScrollPending) restoreNavScroll();
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
    try {
      localStorage.setItem('smartbrgy_theme', theme);
      // Keep Flux's key in step so the staff sign-in and settings pages open in the same theme.
      localStorage.setItem('flux.appearance', theme);
    } catch (_) {}

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
    const stylesByLayout = {
      'admin.css': 'admin',
      'figma-admin.css': 'admin',
      'admin-iot.css': 'iot',
      'figma-iot.css': 'iot',
    };

    const stylesheetLinks = [...document.querySelectorAll('link[rel="stylesheet"]')];
    const ownedStylesheets = stylesheetLinks.map(link => {
      const pathname = new URL(link.href, window.location.href).pathname;
      const filename = pathname.split('/').pop();
      // The staff UI bundle (Flux + Tailwind without preflight) serves migrated pages in both workspace layouts.
      const owner = /^staff-ui(?:-[\w-]+)?\.css$/.test(filename) ? STAFF_UI
        : stylesByLayout[filename] || (pathname.includes('/build/') ? 'settings' : null);

      return { link, owner };
    });
    // Note sheets that already parsed before any is switched off, so switching back never waits for a load event.
    ownedStylesheets.forEach(({ link }) => { if (!link.disabled) stylesheetIsLoaded(link); });

    // Ordinary pages reached by in-app navigation: switch the workspace-only sheets off and the app bundle back on.
    if (!layout) {
      ownedStylesheets.forEach(({ link, owner }) => {
        if (owner) link.disabled = owner !== 'settings';
      });
      activeStylesLayout = null;
      activeStaffUi = false;
      return true;
    }

    // Only pages that opt in (body[data-staff-ui]) use the staff UI bundle; unmigrated pages render exactly as before.
    // The Flux settings layout has its own bundle (app.css), so the two never load together.
    const usesStaffUi = (layout === 'admin' || layout === 'iot') && document.body.hasAttribute('data-staff-ui');
    const targetStylesheets = ownedStylesheets.filter(item => item.owner === layout || (item.owner === STAFF_UI && usesStaffUi));
    const pendingStylesheets = targetStylesheets.filter(({ link }) => !loadedStylesheets.has(link));
    const targetStylesReady = !pendingStylesheets.some(({ link }) => !stylesheetIsLoaded(link));

    // Livewire replaces the body before newly added stylesheets finish loading.
    // Keep the active layout's CSS enabled until every incoming layout sheet parses.
    if (!targetStylesReady) {
      ownedStylesheets.forEach(({ link, owner }) => {
        if (owner === STAFF_UI) {
          link.disabled = !(usesStaffUi || activeStaffUi);
        } else if (owner === layout || owner === activeStylesLayout || activeStylesLayout === null) {
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
          if (document.body.dataset.personnelLayout === layout && syncStyles()) finishNavigation();
        };
        link.addEventListener('load', synchronizeAfterStylesheetLoad, { once: true });
        link.addEventListener('error', () => {
          stylesheetLoadListeners.delete(link);
          hideNavigationLoading();
        }, { once: true });
      });
    } else {
      activeStylesLayout = layout;
      activeStaffUi = usesStaffUi;
      ownedStylesheets.forEach(({ link, owner }) => {
        if (owner === STAFF_UI) link.disabled = !usesStaffUi;
        else if (owner) link.disabled = owner !== layout;
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
  /** Phone menu on the IoT/request layout: the toggle names the nav it controls and reports its state. */
  function setStaffMenu(open, { restoreFocus = false } = {}) {
    const toggle = document.querySelector('[data-staff-nav-toggle]');
    const nav = toggle && document.getElementById(toggle.getAttribute('aria-controls'));
    if (!toggle || !nav) return false;
    nav.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    if (open) nav.querySelector('a[href]')?.focus();
    else if (restoreFocus) toggle.focus();
    return true;
  }

  document.addEventListener('click', event => {
    if (event.target.closest('#iot-theme-toggle')) {
      toggleTheme();
    }
    const menuToggle = event.target.closest('[data-staff-nav-toggle]');
    if (menuToggle) {
      setStaffMenu(menuToggle.getAttribute('aria-expanded') !== 'true');
    } else if (event.target.closest('#staff-side-nav a[href]')) {
      setStaffMenu(false);
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
    if (document.querySelector('[data-staff-nav-toggle][aria-expanded="true"]')) {
      setStaffMenu(false, { restoreFocus: true });
    }
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
    rememberNavScroll();
    navScrollPending = true;
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
    if (syncStyles()) finishNavigation();
    else showNavigationLoading();
  });
  // A full page load (reload, or a page opened without in-app navigation) keeps the menu where it was too.
  window.addEventListener?.('pagehide', rememberNavScroll);
  document.addEventListener('DOMContentLoaded', () => {
    syncStyles();
    restoreNavScroll();
  }, { once: true });
})();
