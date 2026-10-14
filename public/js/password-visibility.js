(() => {
  const initialized = new WeakSet();

  function label(button, visible) {
    const text = visible ? 'Hide password' : 'Show password';
    for (const attribute of ['aria-label', 'title']) {
      if (button.hasAttribute('data-portal-i18n-' + attribute) && typeof portalSetAttribute === 'function') {
        portalSetAttribute(button, attribute, text);
      } else {
        button.setAttribute(attribute, text);
      }
    }
    button.setAttribute('aria-pressed', String(visible));
  }

  function hide(root) {
    root.querySelectorAll('[data-password-toggle]').forEach(button => {
      const field = document.getElementById(button.dataset.passwordToggle);
      if (!field) return;
      field.type = 'password';
      label(button, false);
    });
  }

  function initialize() {
    document.querySelectorAll('[data-password-toggle]').forEach(button => {
      if (initialized.has(button)) return;
      const field = document.getElementById(button.dataset.passwordToggle);
      if (!field) return;
      initialized.add(button);
      label(button, field.type === 'text');
      button.addEventListener('click', () => {
        const visible = field.type === 'password';
        field.type = visible ? 'text' : 'password';
        label(button, visible);
      });
    });
  }

  window.PasswordVisibility = { hide };
  document.addEventListener('DOMContentLoaded', initialize);
  document.addEventListener('livewire:navigated', initialize);
  document.addEventListener('livewire:navigating', () => hide(document));
  document.addEventListener('reset', event => hide(event.target));
  document.addEventListener('close', event => hide(event.target), true);
  window.addEventListener('pagehide', () => hide(document));
  if (document.readyState !== 'loading') initialize();
})();
