(() => {
    const forms = Array.from(document.querySelectorAll('[data-confirm-submit]'));
    const dialog = document.querySelector('[data-confirm-dialog]');
    const title = dialog?.querySelector('[data-confirm-dialog-title]');
    const message = dialog?.querySelector('[data-confirm-dialog-message]');
    const accept = dialog?.querySelector('[data-confirm-accept]');
    const acceptIcon = accept?.querySelector('.button-icon');
    const acceptLabel = accept?.querySelector('[data-default-label]');
    const cancelButtons = dialog?.querySelectorAll('[data-confirm-cancel]') || [];
    const defaultAcceptClassName = accept?.className || '';
    const defaultAcceptIcon = acceptIcon?.style.getPropertyValue('--button-icon-url') || '';
    const defaultAcceptLabel = acceptLabel?.dataset.defaultLabel || acceptLabel?.textContent || 'Yes';
    const alertAcceptLabel = acceptLabel?.dataset.alertLabel || 'Close';
    let pendingConfirm = null;

    function normalizeConfig(config = {}) {
        return typeof config === 'string' ? { message: config } : config;
    }

    function pendingValue(mode) {
        return mode === 'alert' ? undefined : false;
    }

    function cssUrl(value) {
        return `url("${String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"')}")`;
    }

    function configureAcceptButton(config, mode) {
        if (!accept) return;
        accept.className = defaultAcceptClassName;
        if (mode === 'alert' || config.variant === 'normal' || config.variant === 'info') {
            accept.classList.remove('button--danger');
            accept.classList.add('button--normal');
        }
        if (acceptIcon) {
            const alertIcon = accept.dataset.alertIcon || '';
            acceptIcon.style.setProperty('--button-icon-url', mode === 'alert' && alertIcon ? cssUrl(alertIcon) : defaultAcceptIcon);
        }
        if (acceptLabel) acceptLabel.textContent = config.accept || (mode === 'alert' ? alertAcceptLabel : defaultAcceptLabel);
    }

    function openDialog() {
        if (!dialog) return false;
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
            return true;
        }
        dialog.setAttribute('open', '');
        return true;
    }

    function closeDialogElement() {
        if (!dialog) return false;
        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
            return true;
        }
        dialog.removeAttribute('open');
        return false;
    }

    function prompt(config = {}, mode = 'confirm') {
        config = normalizeConfig(config);
        if (!dialog) {
            return Promise.resolve(pendingValue(mode));
        }
        if (pendingConfirm) {
            return Promise.resolve(pendingValue(mode));
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
                cancelButtons.forEach((button) => { button.hidden = false; });
                if (accept) accept.className = defaultAcceptClassName;
                if (acceptIcon) acceptIcon.style.setProperty('--button-icon-url', defaultAcceptIcon);
                if (acceptLabel) acceptLabel.textContent = defaultAcceptLabel;
                opener?.focus?.({ preventScroll: true });
            };

            const finish = (value) => {
                if (settled) return;
                settled = true;
                cleanup();
                resolve(value);
            };

            const closeDialog = () => {
                if (closeDialogElement()) {
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
            cancelButtons.forEach((button) => { button.hidden = mode === 'alert'; });
            configureAcceptButton(config, mode);

            accept?.addEventListener('click', onAccept);
            cancelButtons.forEach((button) => button.addEventListener('click', onCancelClick));
            dialog.addEventListener('cancel', onCancel);
            dialog.addEventListener('close', onClose);

            try {
                openDialog();
            } catch (error) {
                cleanup();
                resolve(pendingValue(mode));
            }
        });
    }

    function confirm(config = {}) {
        return prompt(config, 'confirm');
    }

    function alert(config = {}) {
        return prompt(config, 'alert').then(() => undefined);
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

    window.HuginConfirm = Object.assign(window.HuginConfirm || {}, { alert, confirm, info: alert });

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
