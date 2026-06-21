(() => {
    const dialog = document.querySelector('[data-hugin-dialog]');
    const panel = dialog?.querySelector('[data-hugin-dialog-panel]');
    const title = dialog?.querySelector('[data-hugin-dialog-title]');
    const message = dialog?.querySelector('[data-hugin-dialog-message]');
    const icon = dialog?.querySelector('[data-hugin-dialog-icon]');
    const iconSymbol = dialog?.querySelector('[data-hugin-dialog-icon-symbol]');
    const content = dialog?.querySelector('[data-hugin-dialog-content]');
    const actions = dialog?.querySelector('[data-hugin-dialog-actions]');
    const closeButton = dialog?.querySelector('[data-hugin-dialog-close]');
    const configEl = document.querySelector('[data-hugin-dialog-config]');
    const config = parseJson(configEl?.textContent || '{}');
    let pending = null;

    const labels = Object.assign({
        ok: 'OK',
        yes: 'Yes',
        no: 'No',
        cancel: 'Cancel',
        delete: 'Delete',
        close: 'Close'
    }, config.labels || {});

    const titles = Object.assign({
        default: 'Message',
        information: 'Information',
        question: 'Question',
        exclamation: 'Attention',
        warning: 'Warning',
        error: 'Error',
        trash: 'Delete confirmation'
    }, config.titles || {});

    const icons = Object.assign({}, config.icons || {});
    const buttonIcons = Object.assign({}, config.buttonIcons || {});
    const cancelKeys = new Set(['cancel', 'no', 'close']);

    const presetButtons = {
        ok: { key: 'ok', label: labels.ok, variant: 'default', icon: 'ok' },
        yes: { key: 'yes', label: labels.yes, variant: 'default', icon: 'yes' },
        no: { key: 'no', label: labels.no, variant: 'normal', icon: 'no', cancel: true },
        cancel: { key: 'cancel', label: labels.cancel, variant: 'normal', icon: 'cancel', cancel: true },
        delete: { key: 'delete', label: labels.delete, variant: 'danger', icon: 'delete' },
        close: { key: 'close', label: labels.close, variant: 'normal', icon: 'close', cancel: true }
    };

    function parseJson(value) {
        try {
            return JSON.parse(value || '{}') || {};
        } catch (error) {
            return {};
        }
    }

    function normalizeConfig(value = {}) {
        return typeof value === 'string' ? { message: value } : Object.assign({}, value || {});
    }

    function normalizeIcon(value) {
        const iconName = String(value || '').trim();
        return iconName && iconName !== 'none' && Object.prototype.hasOwnProperty.call(icons, iconName) ? iconName : 'none';
    }

    function cssUrl(value) {
        return `url("${String(value || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"')}")`;
    }

    function parseButtonList(value) {
        if (Array.isArray(value)) return value;
        if (typeof value !== 'string') return [];
        return value.split(',').map((item) => item.trim()).filter(Boolean);
    }

    function buttonFromPreset(key) {
        const preset = presetButtons[key] || null;
        return preset ? Object.assign({}, preset) : null;
    }

    function normalizeButton(button) {
        if (typeof button === 'string') {
            return buttonFromPreset(button);
        }
        if (!button || typeof button !== 'object') {
            return null;
        }

        const presetKey = String(button.preset || button.key || '').trim();
        const base = buttonFromPreset(presetKey) || {
            key: presetKey || `button-${Math.random().toString(36).slice(2)}`,
            label: presetKey || labels.ok,
            variant: 'normal',
            icon: ''
        };
        const key = String(button.key || base.key).trim() || base.key;

        return Object.assign({}, base, {
            key,
            label: String(button.label || base.label || key),
            variant: String(button.variant || base.variant || 'normal'),
            icon: String(button.icon || base.icon || ''),
            value: Object.prototype.hasOwnProperty.call(button, 'value') ? button.value : key,
            cancel: Object.prototype.hasOwnProperty.call(button, 'cancel') ? Boolean(button.cancel) : Boolean(base.cancel)
        });
    }

    function normalizeButtons(value, fallback = ['ok']) {
        const buttons = parseButtonList(value).map(normalizeButton).filter(Boolean);
        if (buttons.length > 0) return buttons;
        return fallback.map(normalizeButton).filter(Boolean);
    }

    function inferCancelButton(buttons, requestedKey) {
        if (requestedKey && buttons.some((button) => button.key === requestedKey)) {
            return requestedKey;
        }
        const explicit = buttons.find((button) => button.cancel);
        if (explicit) return explicit.key;
        const known = buttons.find((button) => cancelKeys.has(button.key));
        return known ? known.key : null;
    }

    function inferAcceptButtons(buttons, config) {
        const raw = config.acceptButtons || config.acceptButton || config.acceptKey || null;
        if (Array.isArray(raw)) return raw.map(String);
        if (typeof raw === 'string' && raw.trim() !== '') {
            return raw.split(',').map((item) => item.trim()).filter(Boolean);
        }
        const preferred = ['delete', 'yes', 'ok'];
        for (const key of preferred) {
            if (buttons.some((button) => button.key === key)) return [key];
        }
        const firstAction = buttons.find((button) => !button.cancel && !cancelKeys.has(button.key));
        return firstAction ? [firstAction.key] : [];
    }

    function renderButton(button) {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = `button button--${button.variant === 'danger' ? 'danger' : button.variant === 'normal' ? 'normal' : 'default'}`;
        element.dataset.dialogButton = button.key;

        const iconUrl = buttonIcons[button.icon] || buttonIcons[button.key] || '';
        if (iconUrl) {
            const iconEl = document.createElement('span');
            iconEl.className = 'button-icon';
            iconEl.setAttribute('aria-hidden', 'true');
            iconEl.style.setProperty('--button-icon-url', cssUrl(iconUrl));
            element.append(iconEl);
        }

        const label = document.createElement('span');
        label.textContent = button.label;
        element.append(label);
        element.addEventListener('click', () => finish(button.value ?? button.key));
        return element;
    }

    function setDialogIcon(iconName) {
        if (!dialog || !icon || !iconSymbol) return;

        dialog.classList.remove(
            'hugin-dialog--information',
            'hugin-dialog--question',
            'hugin-dialog--exclamation',
            'hugin-dialog--warning',
            'hugin-dialog--error',
            'hugin-dialog--trash'
        );
        dialog.dataset.dialogIcon = iconName;

        if (iconName === 'none') {
            icon.hidden = true;
            iconSymbol.style.removeProperty('--dialog-icon-url');
            return;
        }

        dialog.classList.add(`hugin-dialog--${iconName}`);
        icon.hidden = false;
        iconSymbol.style.setProperty('--dialog-icon-url', cssUrl(icons[iconName]));
    }

    function focusableElements() {
        if (!dialog) return [];
        return Array.from(dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
            .filter((element) => !element.disabled && !element.hidden && element.offsetParent !== null);
    }

    function focusInitial(buttons, config, cancelButton) {
        const requested = String(config.defaultButton || '').trim();
        const focusKey = requested || cancelButton || buttons[0]?.key || '';
        const target = focusKey
            ? Array.from(actions?.querySelectorAll('[data-dialog-button]') || []).find((button) => button.dataset.dialogButton === focusKey)
            : null;
        window.setTimeout(() => {
            (target || actions?.querySelector('button') || closeButton || dialog)?.focus?.({ preventScroll: true });
        }, 0);
    }

    function trapFocus(event) {
        if (event.key !== 'Tab') return;
        const focusable = focusableElements();
        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function openDialogElement() {
        if (!dialog) return false;
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
            document.body.classList.add('hugin-dialog-fallback-open');
        }
        return true;
    }

    function closeDialogElement() {
        if (!dialog) return;
        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        document.body.classList.remove('hugin-dialog-fallback-open');
    }

    function cleanup() {
        if (!pending || !dialog) return;
        dialog.removeEventListener('cancel', onNativeCancel);
        dialog.removeEventListener('close', onNativeClose);
        dialog.removeEventListener('click', onBackdropClick);
        dialog.removeEventListener('keydown', onKeydown);
        closeButton?.removeEventListener('click', onCloseButton);
        content?.replaceChildren();
        actions?.replaceChildren();
        pending.opener?.focus?.({ preventScroll: true });
        pending = null;
    }

    function finish(value) {
        if (!pending || pending.settled) return;
        const current = pending;
        current.settled = true;
        closeDialogElement();
        cleanup();
        current.resolve(value);
    }

    function onNativeCancel(event) {
        event.preventDefault();
        finish(pending?.cancelValue ?? null);
    }

    function onNativeClose() {
        finish(pending?.cancelValue ?? null);
    }

    function onBackdropClick(event) {
        if (event.target === dialog) {
            finish(pending?.cancelValue ?? null);
        }
    }

    function onCloseButton() {
        finish(pending?.cancelValue ?? null);
    }

    function onKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            finish(pending?.cancelValue ?? null);
            return;
        }
        trapFocus(event);
    }

    function open(configValue = {}) {
        const normalized = normalizeConfig(configValue);
        if (!dialog || pending) {
            return Promise.resolve(null);
        }

        return new Promise((resolve) => {
            const iconName = normalizeIcon(normalized.icon || 'none');
            const fallbackButtons = iconName === 'none' || iconName === 'information' ? ['ok'] : ['cancel', 'yes'];
            const buttons = normalizeButtons(normalized.buttons, fallbackButtons);
            const cancelButton = inferCancelButton(buttons, normalized.cancelButton);
            const cancelConfig = cancelButton ? buttons.find((button) => button.key === cancelButton) : null;
            const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;

            pending = {
                cancelValue: cancelConfig ? (cancelConfig.value ?? cancelConfig.key) : null,
                opener,
                resolve,
                settled: false
            };

            if (title) title.textContent = String(normalized.title || titles[iconName] || titles.default || '');
            if (message) message.textContent = String(normalized.message || '');
            setDialogIcon(iconName);

            if (content) {
                content.replaceChildren();
                if (normalized.content instanceof Node) {
                    content.append(normalized.content);
                }
                content.hidden = content.childNodes.length === 0;
            }

            actions?.replaceChildren(...buttons.map(renderButton));

            dialog.addEventListener('cancel', onNativeCancel);
            dialog.addEventListener('close', onNativeClose);
            dialog.addEventListener('click', onBackdropClick);
            dialog.addEventListener('keydown', onKeydown);
            closeButton?.addEventListener('click', onCloseButton);

            try {
                openDialogElement();
                focusInitial(buttons, normalized, cancelButton);
            } catch (error) {
                finish(pending.cancelValue);
            }
        });
    }

    function alert(configValue = {}) {
        const normalized = Object.assign({
            icon: 'information',
            buttons: ['ok'],
            defaultButton: 'ok'
        }, normalizeConfig(configValue));
        return open(normalized).then(() => undefined);
    }

    function confirm(configValue = {}) {
        const normalized = Object.assign({
            icon: 'question',
            buttons: ['cancel', 'yes'],
            defaultButton: 'cancel',
            cancelButton: 'cancel'
        }, normalizeConfig(configValue));
        const buttons = normalizeButtons(normalized.buttons, ['cancel', 'yes']);
        const accepted = inferAcceptButtons(buttons, normalized);
        const acceptedValues = buttons
            .filter((button) => accepted.includes(button.key))
            .map((button) => button.value ?? button.key);
        normalized.buttons = buttons;
        return open(normalized).then((value) => accepted.includes(String(value)) || acceptedValues.includes(value));
    }

    function submitConfirmedForm(form, submitter = null) {
        form.dataset.dialogConfirmed = '1';
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

    function submitButtons(form) {
        const buttons = parseButtonList(form.dataset.dialogButtons || 'cancel,delete');
        const acceptLabel = form.dataset.dialogAccept || '';
        const acceptKey = form.dataset.dialogAcceptKey || (buttons.includes('delete') ? 'delete' : buttons.includes('yes') ? 'yes' : buttons[buttons.length - 1] || 'yes');
        return buttons.map((button) => {
            if (button === acceptKey && acceptLabel !== '') {
                return { preset: button, label: acceptLabel };
            }
            return button;
        });
    }

    function submitAcceptKey(form) {
        const buttons = parseButtonList(form.dataset.dialogButtons || 'cancel,delete');
        return form.dataset.dialogAcceptKey || (buttons.includes('delete') ? 'delete' : buttons.includes('yes') ? 'yes' : buttons[buttons.length - 1] || 'yes');
    }

    function bindSubmitDialogs() {
        document.querySelectorAll('[data-dialog-submit]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.dialogConfirmed === '1') return;
                event.preventDefault();
                const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
                const acceptKey = submitAcceptKey(form);

                open({
                    title: form.dataset.dialogTitle || '',
                    message: form.dataset.dialogMessage || '',
                    icon: form.dataset.dialogIcon || 'trash',
                    buttons: submitButtons(form),
                    cancelButton: form.dataset.dialogCancelKey || 'cancel',
                    defaultButton: form.dataset.dialogDefault || 'cancel'
                }).then((result) => {
                    if (String(result) !== acceptKey) return;
                    submitConfirmedForm(form, submitter);
                });
            });
        });
    }

    window.HuginDialog = { open, alert, confirm };
    bindSubmitDialogs();
})();
