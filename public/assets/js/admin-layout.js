(function () {
    const toggle = document.querySelector('[data-admin-sidebar-toggle]');
    const closeTargets = document.querySelectorAll('[data-admin-sidebar-close]');

    document.querySelectorAll('.alert.success, .alert.warning').forEach((alert) => {
        if (!alert.hasAttribute('role')) alert.setAttribute('role', 'status');
        if (!alert.hasAttribute('aria-live')) alert.setAttribute('aria-live', 'polite');
    });
    document.querySelectorAll('.alert.error, .field-error').forEach((alert) => {
        if (!alert.hasAttribute('role')) alert.setAttribute('role', 'alert');
    });

    if (!toggle) return;

    const setOpen = (open) => {
        document.body.classList.toggle('admin-nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    toggle.addEventListener('click', () => {
        setOpen(!document.body.classList.contains('admin-nav-open'));
    });

    closeTargets.forEach((target) => {
        target.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    window.addEventListener('resize', () => {
        if (window.matchMedia('(min-width: 901px)').matches) {
            setOpen(false);
        }
    });
})();
