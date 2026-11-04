/* Account menu under the resident's name: a disclosure button that works with mouse, touch and keyboard. */
(() => {
    const menu = document.querySelector('[data-account-menu]');
    if (!menu) return;

    const button = menu.querySelector('[data-account-menu-button]');
    const list = document.getElementById(button.getAttribute('aria-controls'));
    const items = () => [...list.querySelectorAll('a, button')];

    const setOpen = (open, { focusButton = false, focusFirst = false } = {}) => {
        button.setAttribute('aria-expanded', String(open));
        list.hidden = !open;
        if (open && focusFirst) items()[0]?.focus();
        if (!open && focusButton) button.focus();
    };

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
})();
