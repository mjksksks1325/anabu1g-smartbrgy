(() => {
  let attempt = 0;
  const initializedDialogs = new WeakSet();
  let returnFocus = null;

  function navigate(url) {
    if (window.Livewire?.navigate) window.Livewire.navigate(url);
    else window.location.assign(url);
  }

  function errorMessage(dialog, message) {
    const error = dialog.querySelector('[role="alert"]');
    error.textContent = message;
    error.hidden = false;
  }

  function reset(dialog) {
    attempt++;
    dialog.querySelector('form').reset();
    dialog.querySelector('[role="alert"]').hidden = true;
    dialog.querySelector('[type="submit"]').disabled = false;
    returnFocus?.focus();
    returnFocus = null;
  }

  document.addEventListener('click', async event => {
    const dialog = document.getElementById('personnel-password-confirmation');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const destination = new URL(link.href, window.location.href);
    const securityUrl = new URL(dialog.dataset.securityUrl, window.location.href);
    if (destination.origin !== securityUrl.origin || destination.pathname !== securityUrl.pathname) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const version = ++attempt;
    returnFocus = link;
    try {
      const response = await fetch(dialog.dataset.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!response.ok) throw new Error('Unable to check password confirmation. Please try confirming your password.');
      const status = await response.json();
      if (version !== attempt || !dialog.isConnected) return;
      if (status.confirmed === true) { navigate(destination.href); return; }
      dialog.dataset.destination = destination.href;
      dialog.showModal();
    } catch (error) {
      if (version !== attempt || !dialog.isConnected) return;
      dialog.dataset.destination = destination.href;
      dialog.showModal();
      errorMessage(dialog, error.message);
    }
  }, true);

  document.addEventListener('submit', async event => {
    const form = event.target;
    const dialog = form.closest?.('#personnel-password-confirmation');
    if (!dialog || !dialog.open) return;
    event.preventDefault();
    const submit = form.querySelector('[type="submit"]');
    if (submit.disabled) return;
    submit.disabled = true;
    dialog.querySelector('[role="alert"]').hidden = true;
    const version = ++attempt;
    try {
      const response = await fetch(form.action, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json' }, body: new FormData(form),
      });
      if (version !== attempt || !dialog.isConnected || !dialog.open) return;
      if (!response.ok || response.status !== 201) {
        const payload = await response.json().catch(() => ({}));
        throw new Error(payload.errors?.password?.[0] || (response.status === 429 ? 'Too many attempts. Please wait before trying again.' : response.status === 419 || response.status === 401 ? 'Your session expired. Refresh the page and sign in again.' : payload.message || 'Unable to confirm your password. Please try again.'));
      }
      const destination = dialog.dataset.destination;
      dialog.close();
      navigate(destination);
    } catch (error) {
      if (version === attempt && dialog.isConnected && dialog.open) {
        errorMessage(dialog, error.message);
        form.elements.password.value = '';
        form.elements.password.focus();
      }
    } finally {
      if (version === attempt) submit.disabled = false;
    }
  });

  function initialize() {
    const dialog = document.getElementById('personnel-password-confirmation');
    if (!dialog || initializedDialogs.has(dialog)) return;
    initializedDialogs.add(dialog);
    dialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', () => reset(dialog));
    dialog.addEventListener('close', () => reset(dialog));
  }
  document.addEventListener('livewire:navigating', () => { attempt++; });
  document.addEventListener('DOMContentLoaded', initialize);
  document.addEventListener('livewire:navigated', initialize);
})();
