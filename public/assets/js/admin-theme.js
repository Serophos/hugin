(function () {
    'use strict';

    // AdminLTE's ColorMode controller also reads this key. Sharing the native
    // key prevents its DOM-ready initializer from overriding Hugin's choice.
    const STORAGE_KEY = 'lte-theme';
    const LEGACY_STORAGE_KEY = 'hugin-admin-color-mode';
    const VALID_MODES = new Set(['light', 'dark', 'auto']);
    const media = window.matchMedia('(prefers-color-scheme: dark)');

    function storedMode() {
        try {
            const value = window.localStorage.getItem(STORAGE_KEY);
            if (VALID_MODES.has(value)) return value;

            const legacyValue = window.localStorage.getItem(LEGACY_STORAGE_KEY);
            if (VALID_MODES.has(legacyValue)) {
                window.localStorage.setItem(STORAGE_KEY, legacyValue);
                window.localStorage.removeItem(LEGACY_STORAGE_KEY);
                return legacyValue;
            }
        } catch (error) {}
        return 'auto';
    }

    function resolvedTheme(mode) {
        return mode === 'auto' ? (media.matches ? 'dark' : 'light') : mode;
    }

    function apply(mode, persist) {
        const selected = VALID_MODES.has(mode) ? mode : 'auto';
        const theme = resolvedTheme(selected);
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.documentElement.style.colorScheme = theme;
        document.documentElement.dataset.adminColorMode = selected;
        if (persist) {
            try {
                window.localStorage.setItem(STORAGE_KEY, selected);
                window.localStorage.removeItem(LEGACY_STORAGE_KEY);
            } catch (error) {}
        }

        let activeButton = null;
        document.querySelectorAll('[data-bs-theme-value]').forEach(button => {
            const active = button.dataset.bsThemeValue === selected
                || button.getAttribute('data-bs-theme-value') === selected;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            if (active) activeButton = button;
        });
        const label = document.querySelector('[data-admin-theme-current]');
        if (label && activeButton) label.textContent = activeButton.textContent.trim();
        document.dispatchEvent(new CustomEvent('hugin:themechange', {
            detail: { mode: selected, theme },
        }));
    }

    apply(storedMode(), false);

    document.addEventListener('DOMContentLoaded', () => {
        apply(storedMode(), false);
        document.querySelectorAll('[data-bs-theme-value]').forEach(button => {
            button.addEventListener('click', () => {
                const mode = button.getAttribute('data-bs-theme-value');
                apply(mode, true);
            });
        });
    });

    const syncAutoMode = () => {
        if (storedMode() === 'auto') apply('auto', false);
    };
    if (typeof media.addEventListener === 'function') media.addEventListener('change', syncAutoMode);
    else if (typeof media.addListener === 'function') media.addListener(syncAutoMode);

    window.HuginAdminTheme = { apply, storedMode, resolvedTheme, storageKey: STORAGE_KEY };
}());
