(() => {
    const forms = Array.from(document.querySelectorAll('[data-confirm-submit]'));
    if (forms.length === 0) return;

    const dialog = document.querySelector('[data-confirm-dialog]');
    let pendingForm = null;

    function submitPendingForm() {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = '1';
        pendingForm.submit();
    }

    if (dialog && typeof dialog.showModal === 'function') {
        const title = dialog.querySelector('[data-confirm-dialog-title]');
        const message = dialog.querySelector('[data-confirm-dialog-message]');
        const accept = dialog.querySelector('[data-confirm-accept]');
        const acceptLabel = accept?.querySelector('span');
        const cancelButtons = dialog.querySelectorAll('[data-confirm-cancel]');

        forms.forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === '1') return;
                event.preventDefault();
                pendingForm = form;
                if (title) title.textContent = form.dataset.confirmTitle || '';
                if (message) message.textContent = form.dataset.confirmMessage || '';
                if (acceptLabel) acceptLabel.textContent = form.dataset.confirmAccept || acceptLabel.dataset.defaultLabel || 'Yes';
                dialog.showModal();
            });
        });

        cancelButtons.forEach((button) => {
            button.addEventListener('click', () => {
                pendingForm = null;
                dialog.close();
            });
        });
        accept?.addEventListener('click', submitPendingForm);
        return;
    }

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === '1') return;
            if (!window.confirm(form.dataset.confirmMessage || '')) {
                event.preventDefault();
                return;
            }
            form.dataset.confirmed = '1';
        });
    });
})();
