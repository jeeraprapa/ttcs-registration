const sidebar = document.querySelector('[data-admin-sidebar]');

if (sidebar) {
    const toggle = sidebar.querySelector('[data-admin-menu-toggle]');
    const menu = sidebar.querySelector('[data-admin-menu]');
    const mobile = window.matchMedia('(max-width: 700px)');
    const setOpen = open => {
        menu.hidden = mobile.matches && !open;
        toggle.setAttribute('aria-expanded', String(!menu.hidden));
        toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        toggle.querySelector('i').className = open ? 'bi bi-x-lg' : 'bi bi-list';
    };
    sidebar.dataset.menuReady = '';
    setOpen(false);
    toggle.addEventListener('click', () => setOpen(menu.hidden));
    sidebar.addEventListener('keydown', event => {
        if (event.key === 'Escape' && mobile.matches && !menu.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });
    document.addEventListener('click', event => {
        if (mobile.matches && !menu.hidden && !sidebar.contains(event.target)) setOpen(false);
    });
    mobile.addEventListener('change', () => {
        const focusWasInMenu = menu.contains(document.activeElement);
        setOpen(false);
        if (mobile.matches && focusWasInMenu) toggle.focus();
    });
}
