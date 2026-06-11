<script>
(() => {
    const setDisabled = (container, disabled) => {
        container.querySelectorAll('input, select, textarea, button').forEach(control => {
            control.disabled = disabled;
        });
    };

    const isPluginSectionDisabled = root => {
        const section = root.closest('.plugin-settings-section');
        return section ? section.style.display === 'none' || section.hidden : false;
    };

    const syncEnvironmentPreview = root => {
        const preview = root.querySelector('[data-tl1menu-env-preview]');
        if (!preview) return;

        const styleSelect = root.querySelector('select[name$="[environment_display_style]"]');
        let style = styleSelect ? styleSelect.value : 'symbols';
        if (style === 'global') {
            style = preview.dataset.globalEnvironmentStyle || 'symbols';
        }
        if (style !== 'symbols' && style !== 'values') {
            style = 'symbols';
        }

        const checkedDisplayMode = root.querySelector('input[name$="[display_mode]"]:checked');
        const displayMode = checkedDisplayMode ? checkedDisplayMode.value : '';
        const layout = preview.dataset.tl1menuEnvPreviewLayout || 'dynamic';

        preview.querySelectorAll('[data-tl1menu-env-preview-mode]').forEach(mode => {
            const modeName = mode.dataset.tl1menuEnvPreviewMode || 'card';
            const activeMode = layout === 'all' || displayMode === '' || modeName === displayMode;
            mode.hidden = !activeMode;

            const surface = mode.querySelector('[data-tl1menu-env-preview-surface]');
            if (!surface) return;
            surface.hidden = !activeMode;

            let visibleCount = 0;
            surface.querySelectorAll('[data-tl1menu-env-preview-item]').forEach(item => {
                const setting = item.dataset.tl1menuEnvSetting;
                const checkbox = setting ? root.querySelector('input[name$="[' + setting + ']"]') : null;
                const enabled = setting ? !!checkbox?.checked : true;
                const visible = activeMode && item.dataset.tl1menuEnvStyle === style && enabled;
                item.hidden = !visible;
                if (visible) visibleCount += 1;
            });

            const empty = surface.querySelector('[data-tl1menu-env-preview-empty]');
            if (empty) {
                empty.hidden = !activeMode || visibleCount > 0;
            }
        });
    };

    const syncRoot = root => {
        const sectionDisabled = isPluginSectionDisabled(root);
        root.querySelectorAll('[data-tl1menu-toggle]').forEach(select => {
            const key = select.dataset.tl1menuToggle;
            const value = select.value;
            root.querySelectorAll('[data-tl1menu-toggle-target="' + key + '"]').forEach(target => {
                const allowed = (target.dataset.tl1menuShowWhen || '').split(',').map(item => item.trim()).filter(Boolean);
                const visible = allowed.includes(value);
                const preserveSlot = target.dataset.tl1menuPreserveSlot === 'true';
                target.hidden = !visible && !preserveSlot;
                target.classList.toggle('is-hidden', !visible && preserveSlot);
                if (!visible && preserveSlot) {
                    target.setAttribute('aria-hidden', 'true');
                } else {
                    target.removeAttribute('aria-hidden');
                }
                setDisabled(target, !visible || sectionDisabled);
            });
        });
        syncEnvironmentPreview(root);
    };

    document.querySelectorAll('[data-tl1menu-settings]').forEach(root => {
        if (root.dataset.tl1menuToggleBound === '1') return;
        root.dataset.tl1menuToggleBound = '1';

        root.querySelectorAll([
            '[data-tl1menu-toggle]',
            '[data-tl1menu-env-preview-control]',
            'input[name$="[display_mode]"]',
            'input[name$="[display_co2]"]',
            'input[name$="[display_water]"]',
            'input[name$="[display_animal_welfare]"]',
            'input[name$="[display_rainforest]"]'
        ].join(', ')).forEach(control => {
            control.addEventListener('change', () => syncRoot(root));
        });

        const slideType = document.getElementById('slide_type');
        if (slideType) {
            slideType.addEventListener('change', () => window.setTimeout(() => syncRoot(root), 0));
        }

        syncRoot(root);
        window.setTimeout(() => syncRoot(root), 0);
    });
})();
</script>
