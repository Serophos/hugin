(function () {
    document.querySelectorAll('.alert.success, .alert.warning, .alert-success, .alert-warning').forEach((alert) => {
        if (!alert.hasAttribute('role')) alert.setAttribute('role', 'status');
        if (!alert.hasAttribute('aria-live')) alert.setAttribute('aria-live', 'polite');
    });
    document.querySelectorAll('.alert.error, .alert-danger, .field-error, .invalid-feedback').forEach((alert) => {
        if (!alert.hasAttribute('role')) alert.setAttribute('role', 'alert');
    });

    const syncCardToggleLabel = (card) => {
        const toggle = card?.querySelector(':scope > .card-header [data-lte-toggle="card-collapse"]');
        if (!toggle) return;
        const collapsed = card.classList.contains('collapsed-card');
        toggle.setAttribute('aria-label', collapsed ? toggle.dataset.labelExpand : toggle.dataset.labelCollapse);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    };
    document.querySelectorAll('.card [data-lte-toggle="card-collapse"]').forEach((toggle) => {
        syncCardToggleLabel(toggle.closest('.card'));
    });
    document.addEventListener('collapsed.lte.card-widget', event => syncCardToggleLabel(event.target));
    document.addEventListener('expanded.lte.card-widget', event => syncCardToggleLabel(event.target));

})();
