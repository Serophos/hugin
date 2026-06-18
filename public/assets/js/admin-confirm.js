(() => {
    const forms = Array.from(document.querySelectorAll('[data-confirm-submit]'));
    const dialog = document.querySelector('[data-confirm-dialog]');
    const title = dialog?.querySelector('[data-confirm-dialog-title]');
    const message = dialog?.querySelector('[data-confirm-dialog-message]');
    const accept = dialog?.querySelector('[data-confirm-accept]');
    const acceptLabel = accept?.querySelector('span');
    const cancelButtons = dialog?.querySelectorAll('[data-confirm-cancel]') || [];
    let pendingConfirm = null;

    function fallbackConfirm(config) {
        return Promise.resolve(window.confirm(config.message || ''));
    }

    function confirm(config = {}) {
        if (!dialog || typeof dialog.showModal !== 'function') {
            return fallbackConfirm(config);
        }
        if (pendingConfirm) {
            return Promise.resolve(false);
        }

        return new Promise((resolve) => {
            const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            let accepted = false;
            let settled = false;

            const cleanup = () => {
                accept?.removeEventListener('click', onAccept);
                cancelButtons.forEach((button) => button.removeEventListener('click', onCancelClick));
                dialog.removeEventListener('cancel', onCancel);
                dialog.removeEventListener('close', onClose);
                pendingConfirm = null;
                opener?.focus?.({ preventScroll: true });
            };

            const finish = (value) => {
                if (settled) return;
                settled = true;
                cleanup();
                resolve(value);
            };

            const closeDialog = () => {
                if (dialog.open) {
                    dialog.close();
                    return;
                }
                finish(accepted);
            };

            function onAccept() {
                accepted = true;
                closeDialog();
            }

            function onCancelClick() {
                accepted = false;
                closeDialog();
            }

            function onCancel() {
                accepted = false;
            }

            function onClose() {
                finish(accepted);
            }

            pendingConfirm = { resolve };
            if (title) title.textContent = config.title || '';
            if (message) message.textContent = config.message || '';
            if (acceptLabel) acceptLabel.textContent = config.accept || acceptLabel.dataset.defaultLabel || 'Yes';

            accept?.addEventListener('click', onAccept);
            cancelButtons.forEach((button) => button.addEventListener('click', onCancelClick));
            dialog.addEventListener('cancel', onCancel);
            dialog.addEventListener('close', onClose);

            try {
                dialog.showModal();
            } catch (error) {
                cleanup();
                resolve(window.confirm(config.message || ''));
            }
        });
    }

    function submitConfirmedForm(form, submitter = null) {
        form.dataset.confirmed = '1';
        if (typeof form.requestSubmit === 'function') {
            if (submitter) {
                form.requestSubmit(submitter);
            } else {
                form.requestSubmit();
            }
            return;
        }
        form.submit();
    }

    window.HuginConfirm = Object.assign(window.HuginConfirm || {}, { confirm });

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === '1') return;
            event.preventDefault();
            const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
            confirm({
                title: form.dataset.confirmTitle || '',
                message: form.dataset.confirmMessage || '',
                accept: form.dataset.confirmAccept || '',
            }).then((accepted) => {
                if (!accepted) return;
                submitConfirmedForm(form, submitter);
            });
        });
    });
})();
