/* Header disclosure menus (the account menu and the language and display settings): work with mouse, touch and keyboard. */
document.querySelectorAll('[data-account-menu], [data-settings-menu]').forEach(menu => {
    const button = menu.querySelector('[data-account-menu-button], [data-settings-menu-button]');
    const list = document.getElementById(button.getAttribute('aria-controls'));
    const items = () => [...list.querySelectorAll('a, button')];
    /* Inside the collapsed mobile menu the settings are always shown, so they are never hidden there. */
    const inlineQuery = menu.hasAttribute('data-settings-menu') && typeof window !== 'undefined' ? window.matchMedia('(max-width: 1099.98px)') : null;
    const shownInline = () => Boolean(inlineQuery?.matches);

    const setOpen = (open, { focusButton = false, focusFirst = false } = {}) => {
        button.setAttribute('aria-expanded', String(open));
        list.hidden = !open && !shownInline();
        if (open && focusFirst) items()[0]?.focus();
        if (!open && focusButton) button.focus();
    };

    if (inlineQuery) {
        const syncInline = () => { if (button.getAttribute('aria-expanded') !== 'true') list.hidden = !shownInline(); };
        syncInline();
        inlineQuery.addEventListener('change', syncInline);
    }
    button.addEventListener('click', () => setOpen(button.getAttribute('aria-expanded') !== 'true'));
    button.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true, { focusFirst: true });
        }
    });
    list.addEventListener('keydown', event => {
        const links = items();
        const index = links.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const next = (index + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
            links[next].focus();
        }
        if (event.key === 'Home') { event.preventDefault(); links[0].focus(); }
        if (event.key === 'End') { event.preventDefault(); links[links.length - 1].focus(); }
    });
    list.querySelectorAll('[data-resident-logout]').forEach(logout => logout.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') setOpen(false, { focusButton: true });
    });
    document.addEventListener('click', event => {
        if (!menu.contains(event.target)) setOpen(false);
    });
    menu.addEventListener('focusout', event => {
        if (event.relatedTarget && !menu.contains(event.relatedTarget)) setOpen(false);
    });
});
