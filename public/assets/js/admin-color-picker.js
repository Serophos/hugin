(function () {
    'use strict';

    const PICKER_SELECTOR = '[data-admin-color-picker]';
    const DIALOG_LABELS = {
        title: 'Color',
        opacity: 'Opacity',
        cancel: 'Cancel',
        apply: 'Apply',
        open: 'Open color picker',
    };

    let dialogState = null;

    function clampNumber(value, min, max) {
        const number = Number(value);
        if (!Number.isFinite(number)) {
            return min;
        }
        return Math.max(min, Math.min(max, number));
    }

    function normalizeHex(value, fallback) {
        const raw = String(value || '').trim();
        const match = raw.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
        if (!match) {
            return fallback;
        }

        let hex = match[1].toLowerCase();
        if (hex.length === 3) {
            hex = hex.split('').map(char => char + char).join('');
        }
        return '#' + hex;
    }

    function rgbToHex(red, green, blue) {
        return '#' + [red, green, blue]
            .map(value => clampNumber(value, 0, 255).toString(16).padStart(2, '0'))
            .join('');
    }

    function hexToRgb(hex) {
        const normalized = normalizeHex(hex, '#000000').slice(1);
        return {
            red: parseInt(normalized.slice(0, 2), 16),
            green: parseInt(normalized.slice(2, 4), 16),
            blue: parseInt(normalized.slice(4, 6), 16),
        };
    }

    function formatAlpha(value) {
        const alpha = clampNumber(value, 0, 1);
        return alpha.toFixed(3).replace(/0+$/, '').replace(/\.$/, '') || '0';
    }

    function parseAlphaChannel(value, fallback) {
        const raw = String(value ?? '').trim();
        if (raw === '') {
            return fallback;
        }
        if (raw.endsWith('%')) {
            return clampNumber(parseFloat(raw) / 100, 0, 1);
        }
        return clampNumber(parseFloat(raw), 0, 1);
    }

    function parseRgbChannel(value, scale) {
        const raw = String(value ?? '').trim();
        if (raw.endsWith('%')) {
            return clampNumber(Math.round((parseFloat(raw) / 100) * 255), 0, 255);
        }
        return clampNumber(Math.round(parseFloat(raw) * scale), 0, 255);
    }

    function parseColor(value, defaultColor, defaultAlpha) {
        const fallback = {
            hex: normalizeHex(defaultColor, '#000000'),
            alpha: clampNumber(defaultAlpha, 0, 1),
        };
        const input = String(value || '').trim();
        if (input === '') {
            return fallback;
        }

        if (input.toLowerCase() === 'transparent') {
            return { hex: '#000000', alpha: 0 };
        }

        const hexMatch = input.match(/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i);
        if (hexMatch) {
            let hex = hexMatch[1].toLowerCase();
            if (hex.length === 3 || hex.length === 4) {
                hex = hex.split('').map(char => char + char).join('');
            }
            return {
                hex: '#' + hex.slice(0, 6),
                alpha: hex.length === 8 ? clampNumber(parseInt(hex.slice(6, 8), 16) / 255, 0, 1) : fallback.alpha,
            };
        }

        const rgbaMatch = input.match(/^rgba?\(\s*([0-9.]+%?)\s*(?:,|\s)\s*([0-9.]+%?)\s*(?:,|\s)\s*([0-9.]+%?)(?:\s*(?:,|\/)\s*([0-9.]+%?)\s*)?\)$/i);
        if (rgbaMatch) {
            const red = parseRgbChannel(rgbaMatch[1], 1);
            const green = parseRgbChannel(rgbaMatch[2], 1);
            const blue = parseRgbChannel(rgbaMatch[3], 1);
            const alpha = rgbaMatch[4] === undefined ? fallback.alpha : parseAlphaChannel(rgbaMatch[4], fallback.alpha);
            return { hex: rgbToHex(red, green, blue), alpha };
        }

        const srgbMatch = input.match(/^color\(\s*srgb\s+([0-9.]+%?)\s+([0-9.]+%?)\s+([0-9.]+%?)(?:\s*\/\s*([0-9.]+%?))?\s*\)$/i);
        if (srgbMatch) {
            const red = parseRgbChannel(srgbMatch[1], 255);
            const green = parseRgbChannel(srgbMatch[2], 255);
            const blue = parseRgbChannel(srgbMatch[3], 255);
            const alpha = srgbMatch[4] === undefined ? fallback.alpha : parseAlphaChannel(srgbMatch[4], fallback.alpha);
            return { hex: rgbToHex(red, green, blue), alpha };
        }

        return fallback;
    }

    function formatColor(hex, alpha, format, allowAlpha) {
        const rgb = hexToRgb(hex);
        const selectedFormat = ['hex', 'rgb', 'rgba'].includes(format) ? format : 'hex';
        const selectedAlpha = allowAlpha ? clampNumber(alpha, 0, 1) : 1;

        if (selectedFormat === 'rgb') {
            return `rgb(${rgb.red}, ${rgb.green}, ${rgb.blue})`;
        }
        if (selectedFormat === 'rgba') {
            return `rgba(${rgb.red}, ${rgb.green}, ${rgb.blue}, ${formatAlpha(selectedAlpha)})`;
        }
        return normalizeHex(hex, '#000000');
    }

    function previewColor(hex, alpha, allowAlpha) {
        const rgb = hexToRgb(hex);
        return `rgba(${rgb.red}, ${rgb.green}, ${rgb.blue}, ${formatAlpha(allowAlpha ? alpha : 1)})`;
    }

    function dispatchValueEvents(valueInput, control, includeChange) {
        valueInput.dispatchEvent(new Event('input', { bubbles: true }));
        if (includeChange) {
            valueInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        control.dispatchEvent(new CustomEvent('hugin-color-change', {
            bubbles: true,
            detail: { value: valueInput.value },
        }));
    }

    function pickerConfig(control, colorInput, valueInput) {
        return {
            format: String(control.dataset.colorFormat || 'hex').toLowerCase(),
            allowAlpha: control.dataset.colorAlpha === 'true',
            defaultColor: control.dataset.defaultColor || colorInput.value || '#000000',
            defaultAlpha: control.dataset.defaultAlpha || '1',
            preserveEmpty: control.dataset.colorPreserveEmpty === 'true',
            colorInput,
            valueInput,
        };
    }

    function parsedControlValue(control, config) {
        return parseColor(config.valueInput.value || config.colorInput.value, config.defaultColor, config.defaultAlpha);
    }

    function syncPreview(control, parsed, allowAlpha) {
        const preview = previewColor(parsed.hex, parsed.alpha, allowAlpha);
        control.style.setProperty('--admin-color-preview', preview);
        control.style.setProperty('--template-color-preview', preview);
        control.querySelector('[data-color-picker-trigger]')?.style.setProperty('--admin-color-preview', preview);
    }

    function syncFromValue(control, config) {
        const parsed = parsedControlValue(control, config);
        config.colorInput.value = parsed.hex;
        syncPreview(control, parsed, config.allowAlpha);
        return parsed;
    }

    function syncToValue(control, config, parsed, includeChange) {
        config.colorInput.value = parsed.hex;
        config.valueInput.value = formatColor(parsed.hex, parsed.alpha, config.format, config.allowAlpha);
        syncPreview(control, parsed, config.allowAlpha);
        dispatchValueEvents(config.valueInput, control, includeChange);
    }

    function createDialog() {
        const existing = document.querySelector('[data-admin-color-dialog]');
        if (existing) {
            return existing;
        }

        const dialog = document.createElement('dialog');
        dialog.className = 'admin-color-dialog';
        dialog.dataset.adminColorDialog = '';
        dialog.innerHTML = `
            <form method="dialog" class="admin-color-dialog__panel">
                <div class="admin-color-dialog__head">
                    <h2>${DIALOG_LABELS.title}</h2>
                    <button type="button" class="admin-color-dialog__close" data-color-dialog-cancel aria-label="${DIALOG_LABELS.cancel}">&times;</button>
                </div>
                <div class="admin-color-dialog__body">
                    <label class="admin-color-dialog__field">
                        <span>${DIALOG_LABELS.title}</span>
                        <input type="color" value="#000000" data-color-dialog-color>
                    </label>
                    <label class="admin-color-dialog__field admin-color-dialog__field--alpha">
                        <span class="admin-color-dialog__alpha-head"><span>${DIALOG_LABELS.opacity}</span><output data-color-dialog-alpha-output>100%</output></span>
                        <input type="range" min="0" max="1" step="0.01" value="1" data-color-dialog-alpha>
                    </label>
                    <div class="admin-color-dialog__preview" data-color-dialog-preview aria-hidden="true"></div>
                    <input type="text" class="admin-color-dialog__value" data-color-dialog-value readonly tabindex="-1">
                </div>
                <div class="form-actions admin-color-dialog__actions">
                    <button type="button" class="button button--normal" data-color-dialog-cancel>${DIALOG_LABELS.cancel}</button>
                    <button type="button" class="button" data-color-dialog-apply>${DIALOG_LABELS.apply}</button>
                </div>
            </form>`;
        document.body.appendChild(dialog);

        const close = () => {
            dialogState = null;
            if (typeof dialog.close === 'function') {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
        };

        dialog.querySelectorAll('[data-color-dialog-cancel]').forEach(button => button.addEventListener('click', close));
        dialog.addEventListener('cancel', () => { dialogState = null; });
        dialog.addEventListener('click', event => {
            if (event.target === dialog) {
                close();
            }
        });
        dialog.querySelector('[data-color-dialog-apply]')?.addEventListener('click', () => {
            if (!dialogState) {
                close();
                return;
            }
            const color = dialog.querySelector('[data-color-dialog-color]')?.value || '#000000';
            const alpha = dialog.querySelector('[data-color-dialog-alpha]')?.value || '1';
            syncToValue(dialogState.control, dialogState.config, {
                hex: normalizeHex(color, '#000000'),
                alpha: clampNumber(alpha, 0, 1),
            }, true);
            close();
        });

        const updateDialogPreview = () => {
            const color = dialog.querySelector('[data-color-dialog-color]')?.value || '#000000';
            const alpha = clampNumber(dialog.querySelector('[data-color-dialog-alpha]')?.value || '1', 0, 1);
            const parsed = { hex: normalizeHex(color, '#000000'), alpha };
            const value = formatColor(parsed.hex, parsed.alpha, 'rgba', true);
            const preview = dialog.querySelector('[data-color-dialog-preview]');
            const output = dialog.querySelector('[data-color-dialog-alpha-output]');
            const valueOutput = dialog.querySelector('[data-color-dialog-value]');
            if (preview) {
                preview.style.setProperty('--admin-color-preview', previewColor(parsed.hex, parsed.alpha, true));
            }
            if (output) {
                output.textContent = `${Math.round(parsed.alpha * 100)}%`;
            }
            if (valueOutput) {
                valueOutput.value = value;
            }
        };
        dialog.querySelector('[data-color-dialog-color]')?.addEventListener('input', updateDialogPreview);
        dialog.querySelector('[data-color-dialog-alpha]')?.addEventListener('input', updateDialogPreview);
        dialog.updateColorPreview = updateDialogPreview;

        return dialog;
    }

    function openAlphaDialog(control, config) {
        const dialog = createDialog();
        const parsed = parsedControlValue(control, config);
        dialogState = { control, config };

        const color = dialog.querySelector('[data-color-dialog-color]');
        const alpha = dialog.querySelector('[data-color-dialog-alpha]');
        if (color) {
            color.value = parsed.hex;
        }
        if (alpha) {
            alpha.value = String(parsed.alpha);
        }
        dialog.updateColorPreview?.();

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
        color?.focus({ preventScroll: true });
    }

    function createTrigger(control, colorInput, config) {
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'admin-color-picker__trigger';
        trigger.dataset.colorPickerTrigger = '';
        trigger.setAttribute('aria-label', colorInput.getAttribute('aria-label') || colorInput.getAttribute('title') || DIALOG_LABELS.open);
        trigger.innerHTML = '<span aria-hidden="true"></span>';
        colorInput.before(trigger);
        colorInput.hidden = true;
        colorInput.tabIndex = -1;
        trigger.addEventListener('click', () => openAlphaDialog(control, config));
        return trigger;
    }

    function initControl(control) {
        if (!control || control.dataset.colorPickerReady === '1') {
            return;
        }

        const colorInput = control.querySelector('[data-color-picker-swatch]');
        const valueInput = control.querySelector('[data-color-value]');
        if (!colorInput || !valueInput) {
            return;
        }

        control.dataset.colorPickerReady = '1';
        const config = pickerConfig(control, colorInput, valueInput);

        if (config.allowAlpha) {
            createTrigger(control, colorInput, config);
            syncFromValue(control, config);
        } else {
            colorInput.removeAttribute('alpha');
            colorInput.setAttribute('colorspace', 'srgb');

            const syncHexFromValue = () => syncFromValue(control, config);
            const syncHexToValue = event => {
                const parsed = parseColor(colorInput.value, config.defaultColor, config.defaultAlpha);
                syncToValue(control, config, parsed, event?.type === 'change');
            };

            colorInput.addEventListener('input', syncHexToValue);
            colorInput.addEventListener('change', syncHexToValue);
            valueInput.addEventListener('change', syncHexFromValue);
            syncHexFromValue();
        }

        if (valueInput.value === '' && !config.preserveEmpty) {
            syncToValue(control, config, parsedControlValue(control, config), false);
        }
    }

    function init(root) {
        const scope = root || document;
        if (scope.matches && scope.matches(PICKER_SELECTOR)) {
            initControl(scope);
        }
        scope.querySelectorAll?.(PICKER_SELECTOR).forEach(initControl);
    }

    window.HuginColorPicker = { init, parseColor, formatColor };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init(document));
    } else {
        init(document);
    }
}());
