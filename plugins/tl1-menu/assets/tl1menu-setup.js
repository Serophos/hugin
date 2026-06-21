(function () {
    const root = document.querySelector('[data-tl1menu-setup]');
    if (!root) return;

    const pluginName = root.getAttribute('data-plugin-name') || 'tl1-menu';
    const actionBase = root.getAttribute('data-action-base') || '';
    const i18n = parseJson(root.getAttribute('data-i18n') || '{}') || {};
    let categoryIconChoices = normalizeCategoryIconChoices(parseJson(root.getAttribute('data-category-icons') || '[]'));
    const categoryIconStemRenames = {
        'beef-higher-welfare': 'beef_higher_welfare',
        'fish-higher-welfare': 'fish_higher_welfare',
        'lamb-higher-welfare': 'lamb_higher_welfare',
        'mensa-vital': 'vital',
        'pork-higher-welfare': 'pork_higher_welfare',
        'poultry-higher-welfare': 'poultry_higher_welfare'
    };
    const csrfInput = document.querySelector('input[name="_csrf"]');
    const csrfToken = csrfInput ? csrfInput.value : '';
    const menuUrlInput = root.querySelector('[data-tl1menu-menu-url]');
    const categoryIconFileInput = root.querySelector('[data-tl1menu-category-icon-file]');
    const categoryIconUploadButton = root.querySelector('[data-tl1menu-category-icon-upload-button]');
    const categoryIconUploadStatus = root.querySelector('[data-tl1menu-category-icon-upload-status]');
    const jsonEl = root.querySelector('[data-tl1menu-setup-json]');
    const setupGrid = root.querySelector('[data-tl1menu-setup-grid]') || root;
    const summaryEl = root.querySelector('[data-tl1menu-setup-summary]');
    const fieldEditor = root.querySelector('[data-tl1menu-setup-field-editor]');
    const editor = root.querySelector('[data-tl1menu-setup-editor]');
    const output = root.querySelector('[data-tl1menu-setup-preview-output]');
    const previewTabs = Array.from(root.querySelectorAll('[data-tl1menu-preview-tab]'));
    const previewReload = root.querySelector('[data-tl1menu-preview-reload]');
    const status = root.querySelector('[data-tl1menu-setup-status]');
    const valueDialog = root.querySelector('[data-tl1menu-value-dialog]');
    let state = parseJson(jsonEl ? jsonEl.value : '{}') || {};
    let sampleRows = [];
    let activePreview = 'schema';
    let schemaPreview = parseJson(output ? output.textContent : '{}') || {};
    let testPreview = null;

    function parseJson(value) {
        try { return JSON.parse(value || '{}'); } catch (e) { return {}; }
    }

    function normalizeCategoryIconChoices(value) {
        if (!Array.isArray(value)) return [];
        return value.map(choice => ({
            path: String(choice && choice.path || '').trim(),
            label: String(choice && choice.label || '').trim(),
            url: String(choice && choice.url || '').trim()
        })).filter(choice => choice.path !== '' && choice.url !== '')
            .sort((a, b) => compareSetupKeys(a.label || a.path, b.label || b.path));
    }

    function t(key) {
        return String(i18n[key] || key);
    }

    function setStatus(message, isError) {
        if (!status) return;
        status.textContent = message || '';
        status.classList.toggle('is-error', !!isError);
    }

    function setCategoryIconUploadStatus(message, isError) {
        if (!categoryIconUploadStatus) {
            setStatus(message, isError);
            return;
        }

        categoryIconUploadStatus.textContent = message || '';
        categoryIconUploadStatus.classList.toggle('is-error', !!isError);
    }

    function confirmAction(options) {
        const config = Object.assign({
            title: '',
            message: '',
            acceptLabel: t('dialog.accept_save'),
            variant: 'danger',
            icon: 'warning'
        }, options || {});

        if (typeof window.HuginDialog?.confirm === 'function') {
            const acceptPreset = config.variant === 'danger' ? 'delete' : 'yes';
            return Promise.resolve(window.HuginDialog.confirm({
                title: config.title,
                message: config.message,
                icon: config.icon || (config.variant === 'danger' ? 'warning' : 'question'),
                buttons: ['cancel', { preset: acceptPreset, label: config.acceptLabel }],
                acceptButton: acceptPreset,
                defaultButton: 'cancel',
                cancelButton: 'cancel'
            }));
        }

        return Promise.resolve(false);
    }

    function requestValue(options) {
        const config = Object.assign({
            title: '',
            label: '',
            value: '',
            inputMode: 'text',
            acceptLabel: t('dialog.accept_add')
        }, options || {});

        if (valueDialog && typeof valueDialog.showModal === 'function') {
            return showNativeValueDialog(config);
        }

        return showFallbackValueDialog(config);
    }

    function showNativeValueDialog(config) {
        return new Promise(resolve => {
            const title = valueDialog.querySelector('[data-tl1menu-value-title]');
            const label = valueDialog.querySelector('[data-tl1menu-value-label]');
            const input = valueDialog.querySelector('[data-tl1menu-value-input]');
            const accept = valueDialog.querySelector('[data-tl1menu-value-accept]');
            const acceptLabel = valueDialog.querySelector('[data-tl1menu-value-accept-label]');
            const cancelButtons = Array.from(valueDialog.querySelectorAll('[data-tl1menu-value-cancel]'));
            const previousFocus = document.activeElement && typeof document.activeElement.focus === 'function' ? document.activeElement : null;
            let settled = false;

            if (title) title.textContent = config.title;
            if (label) label.textContent = config.label;
            if (acceptLabel) acceptLabel.textContent = config.acceptLabel;
            if (input) {
                input.value = config.value || '';
                input.inputMode = config.inputMode || 'text';
            }

            function settle(result) {
                if (settled) return;
                settled = true;
                accept?.removeEventListener('click', onAccept);
                cancelButtons.forEach(button => button.removeEventListener('click', onCancelClick));
                input?.removeEventListener('keydown', onInputKeydown);
                valueDialog.removeEventListener('cancel', onCancel);
                valueDialog.removeEventListener('close', onClose);
                if (valueDialog.open) valueDialog.close();
                if (previousFocus) setTimeout(() => previousFocus.focus(), 0);
                resolve(result);
            }

            function onAccept() { settle(input ? input.value : ''); }
            function onCancelClick() { settle(null); }
            function onCancel(event) {
                event.preventDefault();
                settle(null);
            }
            function onClose() { settle(null); }
            function onInputKeydown(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    onAccept();
                }
            }

            accept?.addEventListener('click', onAccept);
            cancelButtons.forEach(button => button.addEventListener('click', onCancelClick));
            input?.addEventListener('keydown', onInputKeydown);
            valueDialog.addEventListener('cancel', onCancel);
            valueDialog.addEventListener('close', onClose);

            try {
                valueDialog.showModal();
            } catch (error) {
                settle(null);
                return;
            }
            setTimeout(() => {
                (input || accept || valueDialog).focus();
                input?.select();
            }, 0);
        });
    }

    function showFallbackValueDialog(config) {
        return new Promise(resolve => {
            const id = `tl1menu-value-${Date.now()}`;
            const overlay = document.createElement('div');
            const previousFocus = document.activeElement && typeof document.activeElement.focus === 'function' ? document.activeElement : null;
            overlay.className = 'tl1menu-confirm-fallback';
            overlay.innerHTML = `
                <div class="admin-dialog tl1menu-confirm-fallback__dialog" role="dialog" aria-modal="true" aria-labelledby="${escapeAttr(id)}-title">
                    <div class="admin-dialog__panel tl1menu-confirm-dialog__panel" role="document">
                        <h2 id="${escapeAttr(id)}-title">${escapeHtml(config.title)}</h2>
                        <label class="tl1menu-value-dialog__field"><span>${escapeHtml(config.label)}</span><input class="tl1menu-value-dialog__input" type="text" inputmode="${escapeAttr(config.inputMode || 'text')}" value="${escapeAttr(config.value || '')}" data-fallback-value></label>
                        <div class="form-actions">
                            <button type="button" class="button button--normal" data-fallback-cancel>${escapeHtml(t('dialog.cancel'))}</button>
                            <button type="button" class="button button--default" data-fallback-accept>${escapeHtml(config.acceptLabel)}</button>
                        </div>
                    </div>
                </div>`;
            const panel = overlay.querySelector('[role="dialog"]');
            const input = overlay.querySelector('[data-fallback-value]');
            const cancel = overlay.querySelector('[data-fallback-cancel]');
            const accept = overlay.querySelector('[data-fallback-accept]');
            let settled = false;

            function settle(result) {
                if (settled) return;
                settled = true;
                overlay.removeEventListener('click', onOverlayClick);
                panel?.removeEventListener('keydown', onKeydown);
                overlay.remove();
                if (previousFocus) previousFocus.focus();
                resolve(result);
            }
            function onOverlayClick(event) {
                if (event.target === overlay) settle(null);
            }
            function onKeydown(event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    settle(null);
                    return;
                }
                if (event.key === 'Enter' && document.activeElement === input) {
                    event.preventDefault();
                    settle(input ? input.value : '');
                    return;
                }
                if (event.key === 'Tab' && panel) trapFocus(panel, event);
            }

            cancel?.addEventListener('click', () => settle(null));
            accept?.addEventListener('click', () => settle(input ? input.value : ''));
            overlay.addEventListener('click', onOverlayClick);
            panel?.addEventListener('keydown', onKeydown);
            document.body.appendChild(overlay);
            setTimeout(() => {
                (input || accept || panel)?.focus();
                input?.select();
            }, 0);
        });
    }


    function trapFocus(container, event) {
        const focusable = Array.from(container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
            .filter(element => !element.disabled && element.offsetParent !== null);
        if (focusable.length === 0) return;
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

    function syncJson() {
        if (jsonEl) jsonEl.value = JSON.stringify(state || {}, null, 2);
    }

    function formatPreview(value) {
        return typeof value === 'string' ? value : JSON.stringify(value, null, 2);
    }

    function showOutput(value) {
        if (output) output.textContent = formatPreview(value);
    }

    function setPreviewTab(tab) {
        activePreview = tab === 'test' ? 'test' : 'schema';
        previewTabs.forEach(button => {
            const isActive = button.getAttribute('data-tl1menu-preview-tab') === activePreview;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function showSchemaPreview() {
        setPreviewTab('schema');
        showOutput(schemaPreview || state || {});
    }

    function refreshSchemaPreview() {
        schemaPreview = state._analysis || state || {};
        showSchemaPreview();
    }

    function reloadPreview() {
        if (activePreview === 'test') {
            runExtractionPreview();
            return;
        }
        refreshSchemaPreview();
        setStatus(t('status.schema_refreshed'));
    }

    function showTestPreview() {
        setPreviewTab('test');
        showOutput(testPreview || t('test_empty'));
    }

    function updateTestPreview(payload) {
        testPreview = payload.item || payload.row || payload;
    }

    function hasSetupConfig() {
        if (!state || typeof state !== 'object') return false;
        return ['field_definitions', 'field_mapping', 'price_groups', 'mensen', 'food_types', 'token_catalog', 'categories']
            .some(key => Object.prototype.hasOwnProperty.call(state, key));
    }

    async function preloadInitialPreviews() {
        if (!hasSetupConfig()) return;

        await Promise.allSettled([
            post('analyze-mapping', {config_json: JSON.stringify(state)}).then(payload => {
                schemaPreview = payload.analysis || schemaPreview || state;
                sampleRows = payload.sample_rows || sampleRows;
                if (activePreview === 'schema') showSchemaPreview();
            }),
            post('preview-row', {config_json: JSON.stringify(state), row_index: 0}).then(payload => {
                updateTestPreview(payload);
                if (activePreview === 'test') showTestPreview();
            })
        ]);
    }

    async function runExtractionPreview() {
        setPreviewTab('test');
        setStatus(t('status.previewing'));
        try {
            const payload = await post('preview-row', {config_json: JSON.stringify(state), row_index: 0});
            updateTestPreview(payload);
            showTestPreview();
            setStatus(t('status.preview_updated'));
        } catch (error) {
            setStatus(error.message, true);
        }
    }

    function fieldNames() {
        return (state.field_definitions || []).map(field => field.name).filter(Boolean);
    }

    function selectField(value, path) {
        const fields = fieldNames();
        return `<select data-path="${escapeAttr(path)}"><option value="">${escapeHtml(t('no_field'))}</option>${fields.map(name => `<option value="${escapeAttr(name)}" ${String(value || '') === name ? 'selected' : ''}>${escapeHtml(name)}</option>`).join('')}</select>`;
    }

    function categoryIconSelect(value, path) {
        if (!categoryIconChoices.length) {
            return input(value, path);
        }

        const iconPath = normalizeCategoryIconPath(value);
        const selectedChoice = categoryIconChoices.find(choice => choice.path === iconPath);
        const currentOption = selectedChoice ? '' : `<option value="${escapeAttr(iconPath)}" selected>${escapeHtml(categoryIconLabel(iconPath))}</option>`;
        const options = categoryIconChoices.map(choice => {
            const label = choice.label || choice.path.replace(/^.*\//, '');
            return `<option value="${escapeAttr(choice.path)}" ${choice.path === iconPath ? 'selected' : ''}>${escapeHtml(label)}</option>`;
        }).join('');

        return `<div class="tl1menu-setup__icon-select${selectedChoice ? '' : ' tl1menu-setup__icon-select--empty'}">
            <select data-path="${escapeAttr(path)}" data-category-icon-select>${currentOption}${options}</select>
            <span class="tl1menu-setup__icon-preview" aria-hidden="true">${categoryIconPreviewHtml(selectedChoice)}</span>
        </div>`;
    }

    function categoryIconLabel(path) {
        return String(path || '').replace(/^.*\//, '');
    }

    function categoryIconPreviewHtml(choice) {
        if (!choice) return '';
        const label = choice.label || categoryIconLabel(choice.path);
        const src = escapeAttr(choice.url);
        return `<img src="${src}" alt="">
            <span class="tl1menu-setup__icon-popover" role="presentation">
                <img src="${src}" alt="${escapeAttr(label)}">
            </span>`;
    }

    function normalizeCategoryIconPath(value) {
        const path = String(value || '').trim().replace(/\\/g, '/');
        if (path === '') return categoryIconAssetPath('default');

        if (categoryIconChoices.some(choice => choice.path === path)) {
            return path;
        }

        const parts = path.split('#');
        const pathOnly = parts.shift() || '';
        const fragment = parts.length ? `#${parts.join('#')}` : '';
        const match = pathOnly.match(/^assets\/img\/categories\/([^/]+)\.(svg|png|webp)$/i);
        if (!match) {
            return path;
        }

        const replacement = findCategoryIconAssetPath(match[1]);
        return replacement ? `${replacement}${fragment}` : path;
    }

    function categoryIconAssetPath(stem) {
        return findCategoryIconAssetPath(stem) || `assets/img/categories/${normalizeCategoryIconStem(stem)}.webp`;
    }

    function findCategoryIconAssetPath(stem) {
        const normalizedStem = normalizeCategoryIconStem(stem);
        const candidates = ['png', 'webp', 'svg'].map(extension => `assets/img/categories/${normalizedStem}.${extension}`);
        const match = candidates.find(candidate => categoryIconChoices.some(choice => choice.path === candidate));
        return match || '';
    }

    function normalizeCategoryIconStem(stem) {
        const normalized = String(stem || 'default').trim();
        return categoryIconStemRenames[normalized] || normalized.replace(/-/g, '_');
    }

    function updateCategoryIconPreview(select) {
        const wrapper = select.closest('.tl1menu-setup__icon-select');
        const preview = wrapper ? wrapper.querySelector('.tl1menu-setup__icon-preview') : null;
        if (!wrapper || !preview) return;

        const selectedChoice = categoryIconChoices.find(choice => choice.path === normalizeCategoryIconPath(select.value));
        wrapper.classList.toggle('tl1menu-setup__icon-select--empty', !selectedChoice);
        preview.innerHTML = categoryIconPreviewHtml(selectedChoice);
    }

    function input(value, path, placeholder) {
        return `<input type="text" value="${escapeAttr(value || '')}" data-path="${escapeAttr(path)}" placeholder="${escapeAttr(placeholder || '')}">`;
    }

    function textarea(value, path) {
        return `<textarea rows="2" data-path="${escapeAttr(path)}">${escapeHtml(Array.isArray(value) ? value.join(', ') : (value || ''))}</textarea>`;
    }

    function removeButton(type, key) {
        return `<button type="button" class="button button--normal button--small tl1menu-setup__row-action" data-remove-row="${escapeAttr(type)}" data-row-key="${escapeAttr(key)}">${escapeHtml(t('actions.remove'))}</button>`;
    }

    function sectionTools(type) {
        return `<div class="tl1menu-setup__section-tools"><button type="button" class="button button--normal button--small" data-add-row="${escapeAttr(type)}">${escapeHtml(t(`actions.add_${type}`))}</button></div>`;
    }

    function emptyRow(colspan) {
        return `<tr><td colspan="${escapeAttr(String(colspan))}" class="tl1menu-setup__empty-row">${escapeHtml(t('empty_table'))}</td></tr>`;
    }

    function compareSetupKeys(a, b) {
        return String(a || '').localeCompare(String(b || ''), undefined, {numeric: true, sensitivity: 'base'});
    }

    function sortedObjectEntries(value, keySelector) {
        return Object.entries(value || {}).sort((a, b) => compareSetupKeys(
            keySelector ? keySelector(a) : a[0],
            keySelector ? keySelector(b) : b[0]
        ));
    }

    function sortedKeyedRows(value, rowKey) {
        if (Array.isArray(value)) {
            return value
                .map((row, index) => ({
                    displayKey: String(row && row[rowKey] || index),
                    pathKey: String(index),
                    row: row && typeof row === 'object' ? row : {}
                }))
                .sort((a, b) => compareSetupKeys(a.displayKey, b.displayKey));
        }

        return sortedObjectEntries(value).map(([key, row]) => ({
            displayKey: key,
            pathKey: key,
            row: row && typeof row === 'object' ? row : {}
        }));
    }

    function sortedObjectFromEntries(entries) {
        return Object.fromEntries(entries.sort((a, b) => compareSetupKeys(a[0], b[0])));
    }

    function sortSetupState(config) {
        if (!config || typeof config !== 'object') return {};

        if (Array.isArray(config.price_groups)) {
            config.price_groups = config.price_groups
                .slice()
                .sort((a, b) => compareSetupKeys(a && a.key, b && b.key));
        }

        if (config.mensen && typeof config.mensen === 'object' && !Array.isArray(config.mensen)) {
            config.mensen = sortedObjectFromEntries(Object.entries(config.mensen));
        }

        if (config.food_types && typeof config.food_types === 'object' && !Array.isArray(config.food_types)) {
            config.food_types = Object.fromEntries(Object.entries(config.food_types)
                .sort((a, b) => compareSetupKeys(
                    a[1] && a[1].key ? a[1].key : a[0],
                    b[1] && b[1].key ? b[1].key : b[0]
                )));
        }

        if (config.token_catalog && typeof config.token_catalog === 'object' && !Array.isArray(config.token_catalog)) {
            config.token_catalog = sortedObjectFromEntries(Object.entries(config.token_catalog));
        }

        if (config.categories && typeof config.categories === 'object' && !Array.isArray(config.categories)) {
            Object.values(config.categories).forEach(row => {
                if (row && typeof row === 'object' && !Array.isArray(row)) {
                    row.icon = normalizeCategoryIconPath(row.icon);
                }
            });
            config.categories = sortedObjectFromEntries(Object.entries(config.categories));
        }

        return config;
    }

    function setupSectionForType(type) {
        return {
            price_group: 'price_groups',
            location: 'locations',
            food_type: 'food_types',
            token: 'tokens',
            category: 'categories'
        }[type] || '';
    }

    function captureEditorView(extraOpenSection) {
        const openSections = new Set();
        const sectionScroll = {};
        setupGrid.querySelectorAll('[data-setup-section]').forEach(section => {
            const key = section.getAttribute('data-setup-section') || '';
            if (key && section.open) openSections.add(key);
            const scroller = section.querySelector('.tl1menu-setup__table-wrap');
            if (key && scroller) {
                sectionScroll[key] = {top: scroller.scrollTop, left: scroller.scrollLeft};
            }
        });
        if (extraOpenSection) openSections.add(extraOpenSection);
        return {openSections, sectionScroll, scrollY: window.scrollY, scrollX: window.scrollX};
    }

    function restoreEditorView(viewState, preserveSections) {
        const defaults = new Set(['field_mapping', 'price_groups']);
        const openSections = viewState && viewState.openSections instanceof Set ? viewState.openSections : defaults;
        setupGrid.querySelectorAll('[data-setup-section]').forEach(section => {
            const key = section.getAttribute('data-setup-section') || '';
            section.open = preserveSections ? openSections.has(key) : defaults.has(key);
            const scroller = section.querySelector('.tl1menu-setup__table-wrap');
            const scroll = viewState && viewState.sectionScroll ? viewState.sectionScroll[key] : null;
            if (scroller && scroll) {
                scroller.scrollTop = scroll.top || 0;
                scroller.scrollLeft = scroll.left || 0;
            }
        });
        if (preserveSections && viewState && typeof viewState.scrollY === 'number') {
            requestAnimationFrame(() => window.scrollTo(viewState.scrollX || 0, viewState.scrollY));
        }
    }

    function render(viewState, focusPath) {
        if (!editor) return;
        const preserveSections = !!viewState || setupGrid.querySelector('[data-setup-section]') !== null;
        const currentView = viewState || captureEditorView();
        const mapping = state.field_mapping || {};
        const analysis = state._analysis || {};
        const counts = analysis.counts || {};
        const summaryHtml = `
            <div class="tl1menu-setup__summary">
                <strong>${escapeHtml(String(counts.rows || 0))}</strong> ${escapeHtml(t('summary.rows'))} ·
                <strong>${escapeHtml(String(counts.locations || 0))}</strong> ${escapeHtml(t('summary.locations'))} ·
                <strong>${escapeHtml(String(counts.food_types || 0))}</strong> ${escapeHtml(t('summary.food_types'))} ·
                <strong>${escapeHtml(String(counts.tokens || 0))}</strong> ${escapeHtml(t('summary.tokens'))}
            </div>`;
        if (summaryEl) {
            summaryEl.innerHTML = summaryHtml;
        }
        if (fieldEditor) {
            fieldEditor.innerHTML = renderMapping(mapping);
        }
        editor.innerHTML = `
            ${fieldEditor ? '' : summaryHtml + renderMapping(mapping)}
            ${renderPriceGroups()}
            ${renderMensen()}
            ${renderFoodTypes()}
            ${renderTokens()}
            ${renderCategories()}
        `;
        restoreEditorView(currentView, preserveSections);
        setupGrid.querySelectorAll('[data-path]').forEach(control => {
            if (control.hasAttribute('data-token-kind-select')) return;
            control.addEventListener('input', () => updatePath(control.getAttribute('data-path'), control.value));
            control.addEventListener('change', () => updatePath(control.getAttribute('data-path'), control.value));
        });
        setupGrid.querySelectorAll('[data-token-kind-select]').forEach(select => {
            select.addEventListener('change', () => updateTokenKind(select));
        });
        setupGrid.querySelectorAll('[data-category-icon-select]').forEach(select => {
            select.addEventListener('change', () => updateCategoryIconPreview(select));
        });
        setupGrid.querySelectorAll('[data-add-row]').forEach(button => {
            button.addEventListener('click', () => addRow(button.getAttribute('data-add-row')));
        });
        setupGrid.querySelectorAll('[data-remove-row]').forEach(button => {
            button.addEventListener('click', () => removeRow(button.getAttribute('data-remove-row'), button.getAttribute('data-row-key')));
        });
        if (focusPath) {
            requestAnimationFrame(() => focusControlInTable(focusPath));
        }
    }

    function findControlByPath(path) {
        return Array.from(setupGrid.querySelectorAll('[data-path]'))
            .find(control => control.getAttribute('data-path') === path) || null;
    }

    function focusControlInTable(path) {
        const control = findControlByPath(path);
        if (!control) return;

        const section = control.closest('[data-setup-section]');
        if (section && 'open' in section) {
            section.open = true;
        }

        const prefersReducedMotion = typeof window.matchMedia === 'function'
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const row = control.closest('tr') || control;
        row.scrollIntoView({block: 'center', inline: 'nearest', behavior: prefersReducedMotion ? 'auto' : 'smooth'});

        window.setTimeout(() => {
            if (typeof control.focus === 'function') {
                control.focus({preventScroll: true});
            }
        }, prefersReducedMotion ? 0 : 120);
    }

    function renderMapping(mapping) {
        const scalar = ['id', 'date', 'mensa_name', 'location_id', 'location_name', 'type_id', 'type_name', 'spalte', 'allergen_codes'];
        return `<details class="tl1menu-setup__section" data-setup-section="field_mapping" open><summary>${escapeHtml(t('sections.field_mapping'))}</summary><div class="tl1menu-setup__table-wrap"><table><tbody>
            ${scalar.map(key => `<tr><th>${escapeHtml(t(`mapping.${key}`))}</th><td>${selectField(mapping[key], `field_mapping.${key}`)}</td></tr>`).join('')}
            <tr><th>${escapeHtml(t('mapping.title_de'))}</th><td>${textarea(mapping.title && mapping.title.de || [], 'field_mapping.title.de')}</td></tr>
            <tr><th>${escapeHtml(t('mapping.description_de'))}</th><td>${textarea(mapping.description && mapping.description.de || [], 'field_mapping.description.de')}</td></tr>
            <tr><th>${escapeHtml(t('mapping.title_en'))}</th><td>${textarea(mapping.title && mapping.title.en || [], 'field_mapping.title.en')}</td></tr>
            <tr><th>${escapeHtml(t('mapping.description_en'))}</th><td>${textarea(mapping.description && mapping.description.en || [], 'field_mapping.description.en')}</td></tr>
            <tr><th>${escapeHtml(t('mapping.token_fields'))}</th><td>${textarea(mapping.category_token_fields || [], 'field_mapping.category_token_fields')}</td></tr>
            <tr><th>${escapeHtml(t('mapping.allergen_names_de'))}</th><td>${selectField(mapping.allergen_names && mapping.allergen_names.de, 'field_mapping.allergen_names.de')}</td></tr>
            <tr><th>${escapeHtml(t('mapping.allergen_names_en'))}</th><td>${selectField(mapping.allergen_names && mapping.allergen_names.en, 'field_mapping.allergen_names.en')}</td></tr>
            ${['co2_value','co2_rating','co2_saving','water_value','water_rating','animal_welfare','rainforest'].map(key => `<tr><th>${escapeHtml(t(`mapping.${key}`))}</th><td>${selectField(mapping.environment && mapping.environment[key], `field_mapping.environment.${key}`)}</td></tr>`).join('')}
        </tbody></table></div></details>`;
    }

    function renderPriceGroups() {
        const rows = (Array.isArray(state.price_groups) ? state.price_groups : [])
            .map((row, index) => ({row, index}))
            .sort((a, b) => compareSetupKeys(a.row && a.row.key, b.row && b.row.key));
        return `<details class="tl1menu-setup__section" data-setup-section="price_groups" open><summary>${escapeHtml(t('sections.price_groups'))}</summary>${sectionTools('price_group')}<div class="tl1menu-setup__table-wrap"><table><thead><tr><th>${escapeHtml(t('fields.key'))}</th><th>${escapeHtml(t('fields.field'))}</th><th>${escapeHtml(t('fields.de'))}</th><th>${escapeHtml(t('fields.en'))}</th><th>${escapeHtml(t('fields.actions'))}</th></tr></thead><tbody>${rows.length ? rows.map(({row, index}) => `<tr><td>${input(row.key, `price_groups.${index}.key`)}</td><td>${selectField(row.field, `price_groups.${index}.field`)}</td><td>${input(row.labels && row.labels.de, `price_groups.${index}.labels.de`)}</td><td>${input(row.labels && row.labels.en, `price_groups.${index}.labels.en`)}</td><td>${removeButton('price_group', String(index))}</td></tr>`).join('') : emptyRow(5)}</tbody></table></div></details>`;
    }

    function renderMensen() {
        const rows = sortedObjectEntries(state.mensen);
        return `<details class="tl1menu-setup__section" data-setup-section="locations"><summary>${escapeHtml(t('sections.locations'))}</summary>${sectionTools('location')}<div class="tl1menu-setup__table-wrap"><table><thead><tr><th>${escapeHtml(t('fields.key'))}</th><th>${escapeHtml(t('fields.label'))}</th><th>${escapeHtml(t('fields.location_ids'))}</th><th>${escapeHtml(t('fields.actions'))}</th></tr></thead><tbody>${rows.length ? rows.map(([key, row]) => `<tr><td>${escapeHtml(key)}</td><td>${input(row.label, `mensen.${escapePath(key)}.label`)}</td><td>${textarea(row.locations || [], `mensen.${escapePath(key)}.locations`)}</td><td>${removeButton('location', key)}</td></tr>`).join('') : emptyRow(4)}</tbody></table></div></details>`;
    }

    function renderFoodTypes() {
        const rows = sortedObjectEntries(state.food_types, ([id, row]) => row && row.key ? row.key : id);
        return `<details class="tl1menu-setup__section" data-setup-section="food_types"><summary>${escapeHtml(t('sections.food_types'))}</summary>${sectionTools('food_type')}<div class="tl1menu-setup__table-wrap"><table><thead><tr><th>${escapeHtml(t('fields.id'))}</th><th>${escapeHtml(t('fields.key'))}</th><th>${escapeHtml(t('fields.de'))}</th><th>${escapeHtml(t('fields.en'))}</th><th>${escapeHtml(t('fields.categories'))}</th><th>${escapeHtml(t('fields.actions'))}</th></tr></thead><tbody>${rows.length ? rows.map(([id, row]) => `<tr><td>${escapeHtml(id)}</td><td>${input(row.key, `food_types.${escapePath(id)}.key`)}</td><td>${input(row.labels && row.labels.de, `food_types.${escapePath(id)}.labels.de`)}</td><td>${input(row.labels && row.labels.en, `food_types.${escapePath(id)}.labels.en`)}</td><td>${textarea(row.categories || [], `food_types.${escapePath(id)}.categories`)}</td><td>${removeButton('food_type', id)}</td></tr>`).join('') : emptyRow(6)}</tbody></table></div></details>`;
    }

    function renderTokens() {
        const rows = sortedObjectEntries(state.token_catalog);
        const kinds = ['allergen','additive','category','ignore'];
        return `<details class="tl1menu-setup__section" data-setup-section="tokens"><summary>${escapeHtml(t('sections.tokens'))}</summary>${sectionTools('token')}<div class="tl1menu-setup__table-wrap"><table><thead><tr><th>${escapeHtml(t('fields.code'))}</th><th>${escapeHtml(t('fields.kind'))}</th><th>${escapeHtml(t('fields.category'))}</th><th>${escapeHtml(t('fields.de'))}</th><th>${escapeHtml(t('fields.en'))}</th><th>${escapeHtml(t('fields.actions'))}</th></tr></thead><tbody>${rows.length ? rows.map(([code, row]) => `<tr><td>${escapeHtml(code)}</td><td><select data-path="${escapeAttr(`token_catalog.${escapePath(code)}.kind`)}" data-token-kind-select data-token-code="${escapeAttr(code)}">${kinds.map(kind => `<option value="${kind}" ${row.kind === kind ? 'selected' : ''}>${escapeHtml(t(`kind.${kind}`))}</option>`).join('')}</select></td><td>${input(row.category, `token_catalog.${escapePath(code)}.category`)}</td><td>${input(row.labels && row.labels.de, `token_catalog.${escapePath(code)}.labels.de`)}</td><td>${input(row.labels && row.labels.en, `token_catalog.${escapePath(code)}.labels.en`)}</td><td>${removeButton('token', code)}</td></tr>`).join('') : emptyRow(6)}</tbody></table></div></details>`;
    }

    function renderCategories() {
        const rows = sortedKeyedRows(state.categories, 'key');
        return `<details class="tl1menu-setup__section" data-setup-section="categories"><summary>${escapeHtml(t('sections.categories'))}</summary>${sectionTools('category')}<div class="tl1menu-setup__table-wrap"><table><thead><tr><th>${escapeHtml(t('fields.key'))}</th><th>${escapeHtml(t('fields.icon'))}</th><th>${escapeHtml(t('fields.de'))}</th><th>${escapeHtml(t('fields.en'))}</th><th>${escapeHtml(t('fields.actions'))}</th></tr></thead><tbody>${rows.length ? rows.map(({displayKey, pathKey, row}) => `<tr><td>${escapeHtml(displayKey)}</td><td>${categoryIconSelect(row.icon, `categories.${escapePath(pathKey)}.icon`)}</td><td>${input(row.labels && row.labels.de, `categories.${escapePath(pathKey)}.labels.de`)}</td><td>${input(row.labels && row.labels.en, `categories.${escapePath(pathKey)}.labels.en`)}</td><td>${removeButton('category', pathKey)}</td></tr>`).join('') : emptyRow(5)}</tbody></table></div></details>`;
    }

    async function addRow(type) {
        if (!type) return;
        const viewState = captureEditorView(setupSectionForType(type));
        if (type === 'price_group') {
            state.price_groups = Array.isArray(state.price_groups) ? state.price_groups : [];
            state.price_groups.push({key: '', field: '', labels: {de: '', en: ''}});
            const index = state.price_groups.length - 1;
            syncJson();
            render(viewState, `price_groups.${index}.key`);
            return;
        }

        const key = await requestValue({
            title: t(`actions.add_${type}`),
            label: t(`prompts.${type}`),
            inputMode: type === 'food_type' ? 'numeric' : 'text',
            acceptLabel: t('dialog.accept_add')
        });
        if (key === null) return;
        const normalizedKey = normalizeNewKey(key, type);
        if (normalizedKey === '') {
            setStatus(t('errors.empty_key'), true);
            return;
        }

        if (type === 'location') {
            state.mensen = state.mensen && typeof state.mensen === 'object' ? state.mensen : {};
            if (state.mensen[normalizedKey]) return setStatus(t('errors.duplicate_key'), true);
            state.mensen[normalizedKey] = {label: normalizedKey, locations: []};
        } else if (type === 'food_type') {
            state.food_types = state.food_types && typeof state.food_types === 'object' ? state.food_types : {};
            if (state.food_types[normalizedKey]) return setStatus(t('errors.duplicate_key'), true);
            state.food_types[normalizedKey] = {key: '', labels: {de: '', en: ''}, categories: []};
        } else if (type === 'token') {
            state.token_catalog = state.token_catalog && typeof state.token_catalog === 'object' ? state.token_catalog : {};
            if (state.token_catalog[normalizedKey]) return setStatus(t('errors.duplicate_key'), true);
            state.token_catalog[normalizedKey] = {kind: 'ignore', category: '', labels: {de: normalizedKey, en: normalizedKey}};
        } else if (type === 'category') {
            state.categories = state.categories && typeof state.categories === 'object' ? state.categories : {};
            if (state.categories[normalizedKey]) return setStatus(t('errors.duplicate_key'), true);
            state.categories[normalizedKey] = {icon: categoryIconAssetPath('default'), labels: {de: normalizedKey, en: normalizedKey}};
        }

        syncJson();
        render(viewState, newRowFocusPath(type, normalizedKey));
    }

    function newRowFocusPath(type, key) {
        const pathKey = escapePath(key);
        if (type === 'location') return `mensen.${pathKey}.label`;
        if (type === 'food_type') return `food_types.${pathKey}.key`;
        if (type === 'token') return `token_catalog.${pathKey}.kind`;
        if (type === 'category') return `categories.${pathKey}.icon`;
        return '';
    }

    async function removeRow(type, key) {
        if (!type || key == null) return;
        if (!await confirmAction({
            title: t('dialog.remove_title'),
            message: t('confirm_remove'),
            acceptLabel: t('dialog.accept_remove'),
            variant: 'danger'
        })) return;
        const viewState = captureEditorView(setupSectionForType(type));

        if (type === 'price_group') {
            state.price_groups = Array.isArray(state.price_groups) ? state.price_groups : [];
            state.price_groups.splice(Number(key), 1);
        } else if (type === 'location' && state.mensen) {
            delete state.mensen[key];
        } else if (type === 'food_type' && state.food_types) {
            delete state.food_types[key];
        } else if (type === 'token' && state.token_catalog) {
            delete state.token_catalog[key];
        } else if (type === 'category' && state.categories) {
            delete state.categories[key];
        }

        syncJson();
        render(viewState);
    }

    function normalizeNewKey(value, type) {
        const key = String(value || '').trim();
        if (type === 'food_type') {
            return /^\d+$/.test(key) ? key : '';
        }
        return key.replace(/\s+/g, '_');
    }

    function updatePath(path, rawValue) {
        if (!path) return;
        const segments = path.split('.').map(unescapePath);
        let target = state;
        while (segments.length > 1) {
            const segment = segments.shift();
            if (!target[segment] || typeof target[segment] !== 'object') target[segment] = {};
            target = target[segment];
        }
        const key = segments[0];
        target[key] = path.endsWith('.locations') || path.endsWith('.categories') || path.includes('.title.') || path.includes('.description.') || path.endsWith('category_token_fields')
            ? splitList(rawValue)
            : rawValue;
        syncJson();
    }

    function updateTokenKind(select) {
        const pathSegments = String(select.getAttribute('data-path') || '').split('.').map(unescapePath);
        const code = pathSegments[0] === 'token_catalog' && pathSegments[2] === 'kind'
            ? pathSegments[1]
            : (select.getAttribute('data-token-code') || '');
        if (code === '') return;

        state.token_catalog = state.token_catalog && typeof state.token_catalog === 'object' ? state.token_catalog : {};
        const row = state.token_catalog[code];
        if (!row || typeof row !== 'object') return;

        row.kind = select.value;
        if (row.kind === 'category') {
            const categoryKey = safeCategoryKey(row.labels && row.labels.en, code);
            row.category = categoryKey;
            ensureCategory(categoryKey, row.labels || {}, code);
        } else {
            row.category = '';
        }

        syncJson();
        render(captureEditorView('tokens'));
    }

    function ensureCategory(key, labels, fallback) {
        if (!key) return;
        state.categories = state.categories && typeof state.categories === 'object' ? state.categories : {};
        if (state.categories[key]) return;

        const labelEn = String(labels && labels.en || fallback || key).trim() || key;
        const labelDe = String(labels && labels.de || labelEn).trim() || labelEn;
        state.categories[key] = {
            icon: categoryIconAssetPath('default'),
            labels: {de: labelDe, en: labelEn}
        };
    }

    function safeCategoryKey(label, fallback) {
        const source = String(label || fallback || '').trim();
        const normalized = source.normalize ? source.normalize('NFKD') : source;
        const ascii = normalized.replace(/[\u0300-\u036f]/g, '');
        const key = ascii
            .toLowerCase()
            .replace(/&/g, ' and ')
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .replace(/_{2,}/g, '_');

        return key || 'category';
    }

    function splitList(value) {
        return String(value || '').split(',').map(part => part.trim()).filter(Boolean).map(part => /^\d+$/.test(part) ? Number(part) : part);
    }

    function collectGlobalSettings() {
        const values = {};
        root.querySelectorAll('[name^="plugin_global_settings["]').forEach(control => {
            if (control.type === 'file') return;
            const match = control.name.match(/^plugin_global_settings\[[^\]]+\]\[([^\]]+)\](?:\[([^\]]*)\])?/);
            if (!match) return;
            const key = match[1];
            const child = match[2];
            if ((control.type === 'checkbox' || control.type === 'radio') && !control.checked) return;
            if (child === '') {
                values[key] = Array.isArray(values[key]) ? values[key] : [];
                values[key].push(control.value);
            } else if (child) {
                values[key] = values[key] && typeof values[key] === 'object' && !Array.isArray(values[key]) ? values[key] : {};
                values[key][child] = control.value;
            } else {
                values[key] = control.value;
            }
        });
        return values;
    }

    async function post(action, data) {
        if (!actionBase) throw new Error(t('errors.missing_action'));
        const params = new URLSearchParams();
        params.set('_csrf', csrfToken);
        Object.entries(data || {}).forEach(([key, value]) => params.set(`plugin_action[${key}]`, value));
        const response = await fetch(`${actionBase}/${action}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken},
            body: params.toString()
        });
        const payload = await parseJsonResponse(response);
        if (!payload.ok) throw new Error(payload.error || t('errors.request_failed'));
        return payload;
    }

    async function postFile(action, file) {
        if (!actionBase) throw new Error(t('errors.missing_action'));
        const formData = new FormData();
        formData.set('_csrf', csrfToken);
        formData.set(`plugin_settings[${pluginName}][category_icon_file]`, file);
        const response = await fetch(`${actionBase}/${action}`, {
            method: 'POST',
            headers: {'X-CSRF-Token': csrfToken},
            body: formData
        });
        const payload = await parseJsonResponse(response);
        if (!payload.ok) throw new Error(payload.error || t('errors.request_failed'));
        return payload;
    }

    async function parseJsonResponse(response) {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (error) {
            const message = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            throw new Error(message || `${t('errors.request_failed')} (${response.status})`);
        }
    }

    categoryIconUploadButton?.addEventListener('click', async () => {
        const file = categoryIconFileInput && categoryIconFileInput.files ? categoryIconFileInput.files[0] : null;
        if (!file) {
            setCategoryIconUploadStatus(t('icon_upload.choose_file'), true);
            return;
        }

        categoryIconUploadButton.disabled = true;
        setCategoryIconUploadStatus(t('icon_upload.uploading'));
        try {
            const payload = await postFile('upload-category-icon', file);
            categoryIconChoices = normalizeCategoryIconChoices(payload.category_icons || []);
            if (categoryIconFileInput) categoryIconFileInput.value = '';
            render(captureEditorView());
            setCategoryIconUploadStatus(t('icon_upload.uploaded'));
        } catch (error) {
            setCategoryIconUploadStatus(error.message, true);
        } finally {
            categoryIconUploadButton.disabled = false;
        }
    });

    root.querySelector('[data-tl1menu-setup-analyze]')?.addEventListener('click', async () => {
        if (!await confirmAction({
            title: t('dialog.analyze_title'),
            message: t('confirm_analyze'),
            acceptLabel: t('dialog.accept_analyze'),
            variant: 'danger'
        })) return;
        setStatus(t('status.analyzing'));
        try {
            const payload = await post('analyze-url', {menu_url: menuUrlInput ? menuUrlInput.value : ''});
            state = sortSetupState(payload.generated_config || {});
            state._analysis = payload.analysis || {};
            sampleRows = payload.sample_rows || [];
            schemaPreview = payload.analysis || state;
            testPreview = null;
            syncJson();
            render();
            showSchemaPreview();
            setStatus(t('status.analysis_complete'));
        } catch (error) {
            setStatus(error.message, true);
        }
    });

    previewTabs.forEach(button => {
        button.addEventListener('click', () => {
            const tab = button.getAttribute('data-tl1menu-preview-tab');
            if (tab === 'test') {
                if (testPreview) {
                    showTestPreview();
                    setStatus(t('status.preview_updated'));
                } else {
                    runExtractionPreview();
                }
                return;
            }
            showSchemaPreview();
            setStatus(t('status.showing_schema'));
        });
    });

    previewReload?.addEventListener('click', reloadPreview);

    root.querySelector('[data-tl1menu-setup-save]')?.addEventListener('click', async () => {
        if (!await confirmAction({
            title: t('dialog.save_title'),
            message: t('confirm_save'),
            acceptLabel: t('dialog.accept_save'),
            variant: 'danger'
        })) return;
        setStatus(t('status.saving'));
        try {
            await post('save-config', {config_json: JSON.stringify(state), global_settings_json: JSON.stringify(collectGlobalSettings())});
            if (activePreview === 'test') {
                showTestPreview();
            } else {
                showSchemaPreview();
            }
            setStatus(t('status.saved'));
        } catch (error) {
            setStatus(error.message, true);
        }
    });

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    }
    function escapeAttr(value) { return escapeHtml(value); }
    function escapePath(value) { return String(value).replace(/\./g, '%2E'); }
    function unescapePath(value) { return String(value).replace(/%2E/g, '.'); }

    state = sortSetupState(state);
    syncJson();

    if (hasSetupConfig()) {
        schemaPreview = state._analysis || state || schemaPreview;
    }
    showSchemaPreview();
    render();
    preloadInitialPreviews();
})();
