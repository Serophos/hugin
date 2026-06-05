<?php
$formId = 'template';
$template = $templateModel ?? [];
$title = !empty($template['id']) ? __('templates.edit_title') : __('templates.create_title');
$landscapeJson = json_encode($landscapeSpec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$portraitJson = json_encode($portraitSpec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$mediaJson = json_encode(array_map(static function (array $asset): array {
    return [
        'id' => (int)$asset['id'],
        'name' => (string)$asset['name'],
        'original_name' => (string)$asset['original_name'],
        'kind' => (string)$asset['media_kind'],
        'url' => ($asset['file_path'] ?? '') !== '' ? url((string)$asset['file_path']) : '',
    ];
}, $mediaAssets ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$editorI18n = [
    'background' => __('templates.element_background'),
    'element_text' => __('templates.element_text'),
    'element_media' => __('templates.element_media'),
    'element_qr' => __('templates.element_qr'),
    'element_shape' => __('templates.element_shape'),
    'field' => __('templates.property_field'),
    'field_key' => __('templates.field_key'),
    'field_label' => __('templates.field_label'),
    'field_required' => __('templates.field_required'),
    'field_1' => __('templates.field_default_name', ['number' => '1']),
    'text' => __('templates.field_type_text'),
    'multiline' => __('templates.field_type_multiline'),
    'url' => __('templates.field_type_url'),
    'media_image' => __('templates.field_type_media_image'),
    'media_video' => __('templates.field_type_media_video'),
    'qr_url' => __('templates.field_type_qr_url'),
    'color' => __('templates.field_type_color'),
    'x' => __('templates.property_x'),
    'y' => __('templates.property_y'),
    'w' => __('templates.property_w'),
    'h' => __('templates.property_h'),
    'z' => __('templates.property_z'),
    'text_color' => __('templates.property_color'),
    'background_color' => __('templates.property_background'),
    'radius' => __('templates.property_radius'),
    'size' => __('templates.property_size'),
    'media' => __('templates.property_media'),
    'background_media' => __('templates.property_background_media'),
    'fit' => __('templates.property_fit'),
    'cover' => __('templates.fit_cover'),
    'contain' => __('templates.fit_contain'),
    'remove' => __('common.remove'),
    'delete' => __('common.delete'),
    'none' => __('common.none'),
    'inspector_element' => __('templates.inspector_element'),
    'inspector_fields' => __('templates.inspector_fields'),
    'inspector_layers' => __('templates.inspector_layers'),
    'section_position' => __('templates.section_position'),
    'section_binding' => __('templates.section_binding'),
    'section_content' => __('templates.section_content'),
    'section_appearance' => __('templates.section_appearance'),
    'empty_fields' => __('templates.empty_fields'),
    'no_field_selection' => __('templates.no_field_selection'),
    'element_has_no_field' => __('templates.element_has_no_field'),
    'element_cannot_use_fields' => __('templates.element_cannot_use_fields'),
    'create_and_bind_field' => __('templates.create_and_bind_field'),
    'delete_element' => __('templates.delete_element'),
    'add_field_tooltip' => __('templates.add_field_tooltip'),
    'remove_field_tooltip' => __('templates.remove_field_tooltip'),
    'select_layer_tooltip' => __('templates.select_layer_tooltip'),
    'import_invalid' => __('templates.import_invalid'),
    'open_color_picker' => __('templates.open_color_picker'),
    'color_opacity' => __('templates.color_opacity'),
];
$editorI18nJson = json_encode($editorI18n, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$editorIconsJson = json_encode([
    'add' => admin_icon('add'),
    'delete' => admin_icon('delete'),
    'move' => admin_icon('move'),
    'settings' => admin_icon('settings'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
require __DIR__ . '/../layouts/admin_header.php';
?>
<h1><?= e($title) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(!empty($template['id']) ? url('/admin/slide-templates/' . $template['id'] . '/edit') : url('/admin/slide-templates/create')) ?>" class="form-grid template-editor-form" data-template-editor-form>
    <?= csrf_field() ?>
    <div class="card full-width form-grid">
        <label><?= e(__('common.name')) ?>
            <input type="text" name="name" value="<?= e((string)old('name', $template['name'] ?? '', $formId)) ?>" required<?= field_attrs('name', $formId) ?>>
            <?= field_error_html('name', $formId) ?>
        </label>
        <label><?= e(__('common.description')) ?>
            <textarea name="description" rows="2"<?= field_attrs('description', $formId) ?>><?= e((string)old('description', $template['description'] ?? '', $formId)) ?></textarea>
        </label>
        <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= old_checked('is_active', $template['is_active'] ?? 1, $formId) ?>> <?= e(__('common.active')) ?></label>
    </div>

    <input type="hidden" name="landscape_spec_json" data-template-spec="landscape" value="<?= e((string)old('landscape_spec_json', $template['landscape_spec_json'] ?? '', $formId)) ?>">
    <input type="hidden" name="portrait_spec_json" data-template-spec="portrait" value="<?= e((string)old('portrait_spec_json', $template['portrait_spec_json'] ?? '', $formId)) ?>">
    <?= field_error_html('landscape_spec_json', $formId) ?>

    <section class="template-editor full-width" data-template-editor>
        <div class="template-editor__topbar">
            <div class="segmented-control" role="tablist">
                <button type="button" class="is-active" data-orientation-tab="landscape"><?= e(__('orientations.landscape')) ?></button>
                <button type="button" data-orientation-tab="portrait"><?= e(__('orientations.vertical')) ?></button>
            </div>
            <div class="template-editor__tools">
                <button type="button" class="template-tool-button template-tool-button--text" data-add-element="text">
                    <span class="template-tool-button__icon" aria-hidden="true"><span class="template-tool-glyph template-tool-glyph--text"></span></span>
                    <span class="template-tool-button__label"><?= e(__('templates.element_text')) ?></span>
                </button>
                <button type="button" class="template-tool-button template-tool-button--media" data-add-element="media">
                    <span class="template-tool-button__icon" aria-hidden="true"><span class="template-tool-glyph template-tool-glyph--media"></span></span>
                    <span class="template-tool-button__label"><?= e(__('templates.element_media')) ?></span>
                </button>
                <button type="button" class="template-tool-button template-tool-button--qr" data-add-element="qr">
                    <span class="template-tool-button__icon" aria-hidden="true"><span class="template-tool-glyph template-tool-glyph--qr"></span></span>
                    <span class="template-tool-button__label"><?= e(__('templates.element_qr')) ?></span>
                </button>
                <button type="button" class="template-tool-button template-tool-button--shape" data-add-element="shape">
                    <span class="template-tool-button__icon" aria-hidden="true"><span class="template-tool-glyph template-tool-glyph--shape"></span></span>
                    <span class="template-tool-button__label"><?= e(__('templates.element_shape')) ?></span>
                </button>
            </div>
        </div>
        <div class="template-editor__layout">
            <div class="template-editor__canvas-shell">
                <div class="template-editor__canvas-frame">
                    <div class="template-editor__canvas" data-editor-canvas></div>
                </div>
            </div>
            <aside class="template-editor__inspector" data-editor-inspector>
                <div class="template-editor__inspector-tabs" role="tablist" aria-label="<?= e(__('templates.properties')) ?>">
                    <button type="button" class="is-active" data-inspector-tab="element" title="<?= e(__('templates.inspector_element')) ?>" aria-label="<?= e(__('templates.inspector_element')) ?>"><?= admin_icon('settings') ?><span><?= e(__('templates.inspector_element')) ?></span></button>
                    <button type="button" data-inspector-tab="fields" title="<?= e(__('templates.inspector_fields')) ?>" aria-label="<?= e(__('templates.inspector_fields')) ?>"><?= admin_icon('add') ?><span><?= e(__('templates.inspector_fields')) ?></span></button>
                    <button type="button" data-inspector-tab="layers" title="<?= e(__('templates.inspector_layers')) ?>" aria-label="<?= e(__('templates.inspector_layers')) ?>"><?= admin_icon('move') ?><span><?= e(__('templates.inspector_layers')) ?></span></button>
                </div>
                <div class="template-editor__inspector-body">
                    <section class="template-editor__inspector-panel" data-inspector-panel="element"></section>
                    <section class="template-editor__inspector-panel" data-inspector-panel="fields" hidden></section>
                    <section class="template-editor__inspector-panel" data-inspector-panel="layers" hidden></section>
                </div>
            </aside>
        </div>
    </section>

    <details class="card full-width template-json-panel">
        <summary><?= e(__('templates.advanced_json')) ?></summary>
        <div class="template-json-panel__grid">
            <section class="template-json-panel__item">
                <div class="template-json-panel__head">
                    <h2><?= e(__('orientations.landscape')) ?></h2>
                    <div class="template-json-panel__actions">
                        <button type="button" class="button button--normal button--small" data-json-export="landscape"><?= admin_icon('open') ?><span><?= e(__('templates.export_json')) ?></span></button>
                        <button type="button" class="button button--normal button--small" data-json-import-trigger="landscape"><?= admin_icon('upload') ?><span><?= e(__('templates.import_json')) ?></span></button>
                        <input type="file" accept="application/json,.json" data-json-import="landscape" hidden>
                    </div>
                </div>
                <textarea rows="8" data-json-debug="landscape" spellcheck="false" readonly></textarea>
            </section>
            <section class="template-json-panel__item">
                <div class="template-json-panel__head">
                    <h2><?= e(__('orientations.vertical')) ?></h2>
                    <div class="template-json-panel__actions">
                        <button type="button" class="button button--normal button--small" data-json-export="portrait"><?= admin_icon('open') ?><span><?= e(__('templates.export_json')) ?></span></button>
                        <button type="button" class="button button--normal button--small" data-json-import-trigger="portrait"><?= admin_icon('upload') ?><span><?= e(__('templates.import_json')) ?></span></button>
                        <input type="file" accept="application/json,.json" data-json-import="portrait" hidden>
                    </div>
                </div>
                <textarea rows="8" data-json-debug="portrait" spellcheck="false" readonly></textarea>
            </section>
        </div>
    </details>

    <div class="form-actions">
        <button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
        <a class="button button--normal" href="<?= e(url('/admin/slide-templates')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
    </div>
</form>
<script>
(() => {
    const mediaAssets = <?= $mediaJson ?: '[]' ?>;
    const i18n = <?= $editorI18nJson ?: '{}' ?>;
    const specs = {
        landscape: <?= $landscapeJson ?: '{}' ?>,
        portrait: <?= $portraitJson ?: 'null' ?>,
    };
    const defaults = {
        landscape: () => ({ version: 1, canvas: { width: 1920, height: 1080 }, fields: [], elements: [{ id: uid('background'), type: 'background', x: 0, y: 0, w: 1, h: 1, z: 0, style: { backgroundColor: '#0f172a' } }] }),
        portrait: () => ({ version: 1, canvas: { width: 1080, height: 1920 }, fields: [], elements: [{ id: uid('background'), type: 'background', x: 0, y: 0, w: 1, h: 1, z: 0, style: { backgroundColor: '#0f172a' } }] }),
    };
    const icons = <?= $editorIconsJson ?: '{}' ?>;
    let orientation = 'landscape';
    let selectedId = null;
    let activeInspectorTab = 'element';

    const editor = document.querySelector('[data-template-editor]');
    const form = document.querySelector('[data-template-editor-form]');
    const canvas = editor.querySelector('[data-editor-canvas]');
    const inspectorTabs = editor.querySelectorAll('[data-inspector-tab]');
    const inspectorPanels = editor.querySelectorAll('[data-inspector-panel]');
    const elementPanel = editor.querySelector('[data-inspector-panel="element"]');
    const fieldsPanel = editor.querySelector('[data-inspector-panel="fields"]');
    const layersPanel = editor.querySelector('[data-inspector-panel="layers"]');
    const hiddenLandscape = form.querySelector('[data-template-spec="landscape"]');
    const hiddenPortrait = form.querySelector('[data-template-spec="portrait"]');
    const debugLandscape = form.querySelector('[data-json-debug="landscape"]');
    const debugPortrait = form.querySelector('[data-json-debug="portrait"]');

    function uid(prefix) { return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`; }
    function spec() { if (!specs[orientation]) specs[orientation] = defaults[orientation](); return specs[orientation]; }
    function clamp(value, min, max) { return Math.max(min, Math.min(max, Number(String(value).replace(',', '.')) || 0)); }
    function rounded(value, min = 0, max = 1) { return Number(clamp(value, min, max).toFixed(4)); }
    function coordinateDisplay(value, min = 0, max = 1) { return rounded(value, min, max).toFixed(4); }
    function pct(value) { return `${clamp(value, 0, 1) * 100}%`; }
    function selectedElement() { return spec().elements.find(element => element.id === selectedId) || null; }
    function escapeHtml(value) { const div = document.createElement('div'); div.textContent = String(value ?? ''); return div.innerHTML; }
    function attr(value) { return escapeHtml(value).replace(/"/g, '&quot;'); }
    function isBackground(element) { return element?.type === 'background'; }
    function fieldOptions(selected) { return [`<option value="">${escapeHtml(i18n.none)}</option>`].concat(spec().fields.map(field => `<option value="${attr(field.key)}" ${field.key === selected ? 'selected' : ''}>${escapeHtml(field.label || field.key)}</option>`)).join(''); }
    function mediaOptions() { return [`<option value="0">${escapeHtml(i18n.none)}</option>`].concat(mediaAssets.map(asset => `<option value="${asset.id}">${escapeHtml(asset.name)} (${escapeHtml(asset.kind)})</option>`)).join(''); }
    function cssColor(value) { return String(value || '').trim() || 'transparent'; }

    function syncHidden() {
        hiddenLandscape.value = JSON.stringify(normalizedSpecForSave(specs.landscape || defaults.landscape()));
        hiddenPortrait.value = specs.portrait ? JSON.stringify(normalizedSpecForSave(specs.portrait)) : '';
        debugLandscape.value = JSON.stringify(JSON.parse(hiddenLandscape.value || '{}'), null, 2);
        debugPortrait.value = hiddenPortrait.value ? JSON.stringify(JSON.parse(hiddenPortrait.value), null, 2) : '';
    }

    function normalizedSpecForSave(inputSpec) {
        const copy = JSON.parse(JSON.stringify(inputSpec));
        copy.elements = (copy.elements || []).map(element => Object.assign({}, element, {
            x: rounded(element.x),
            y: rounded(element.y),
            w: rounded(element.w, 0.02, 1),
            h: rounded(element.h, 0.02, 1),
        }));
        return copy;
    }

    function renderCanvas() {
        const current = spec();
        const ratio = `${current.canvas.width} / ${current.canvas.height}`;
        const ratioValue = current.canvas.width / current.canvas.height;
        canvas.style.aspectRatio = ratio;
        canvas.style.width = ratioValue >= 1 ? '100%' : `min(100%, ${Number((ratioValue * 76).toFixed(3))}vh)`;
        canvas.innerHTML = '';
        current.elements.slice().sort((a, b) => (a.z || 0) - (b.z || 0)).forEach(element => {
            const node = document.createElement('button');
            node.type = 'button';
            node.className = `template-editor__element template-editor__element--${element.type}`;
            if (element.id === selectedId) node.classList.add('is-selected');
            node.style.left = pct(element.x);
            node.style.top = pct(element.y);
            node.style.width = pct(element.w);
            node.style.height = pct(element.h);
            node.style.zIndex = String(element.z || 0);
            node.style.color = element.style?.color || '';
            node.style.background = element.style?.backgroundColor || (element.type === 'background' ? '#0f172a' : 'rgba(255,255,255,0.18)');
            node.style.borderRadius = `${Number(element.style?.radius || 0)}cqw`;
            node.textContent = elementLabel(element);
            node.dataset.elementId = element.id;
            node.addEventListener('pointerdown', startDrag);
            node.addEventListener('click', () => { selectElement(element.id); });
            if (!isBackground(element)) {
                const handle = document.createElement('span');
                handle.className = 'template-editor__resize';
                handle.addEventListener('pointerdown', startResize);
                node.appendChild(handle);
            }
            canvas.appendChild(node);
        });
    }

    function renderLiveCanvas() {
        renderCanvas();
        renderLayersPanel();
        syncHidden();
    }

    function render() {
        renderCanvas();
        renderInspector();
        syncHidden();
    }

    function elementLabel(element) {
        if (element.type === 'background') return i18n.background;
        if (element.field) return element.field;
        return i18n[`element_${element.type}`] || element.type;
    }

    function setInspectorTab(tab) {
        activeInspectorTab = tab;
        renderInspectorTabs();
    }

    function selectElement(id, focusElementTab = false) {
        selectedId = id;
        if (focusElementTab) activeInspectorTab = 'element';
        render();
    }

    function renderInspector() {
        renderInspectorTabs();
        renderElementInspector(selectedElement());
        renderFieldsPanel();
        renderLayersPanel();
    }

    function renderInspectorTabs() {
        inspectorTabs.forEach(tab => {
            const active = tab.dataset.inspectorTab === activeInspectorTab;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        inspectorPanels.forEach(panel => { panel.hidden = panel.dataset.inspectorPanel !== activeInspectorTab; });
    }

    function panelTitle(label, action = '') {
        return `<div class="template-editor__panel-title"><h2>${escapeHtml(label)}</h2>${action}</div>`;
    }

    function iconButton(dataName, label, iconName, extraClass = '') {
        return `<button type="button" class="template-editor__icon-button ${extraClass}" data-${dataName} aria-label="${attr(label)}" title="${attr(label)}">${icons[iconName] || ''}</button>`;
    }

    function propertySection(title, body) {
        if (!body) return '';
        return `<section class="template-editor__property-section"><h3>${escapeHtml(title)}</h3>${body}</section>`;
    }

    function propertyRow(label, control, modifier = '') {
        return `<label class="template-editor__property-row ${modifier}"><span>${escapeHtml(label)}</span>${control}</label>`;
    }

    function numberInput(dataKind, key, value, attrs = '') {
        return `<input type="number" ${attrs} data-${dataKind}="${attr(key)}" value="${attr(value)}">`;
    }


    function parsedColor(value) {
        const raw = String(value || '').trim();
        const shortHex = raw.match(/^#([0-9a-f]{3})$/i);
        if (shortHex) return { hex: `#${shortHex[1].split('').map(part => part + part).join('')}`, alpha: 1 };
        if (/^#[0-9a-f]{6}$/i.test(raw)) return { hex: raw, alpha: 1 };
        const rgb = raw.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*([0-9.]+))?/i);
        if (!rgb) return { hex: '#000000', alpha: 1 };
        return {
            hex: '#' + rgb.slice(1, 4).map(channel => Math.max(0, Math.min(255, Number(channel) || 0)).toString(16).padStart(2, '0')).join(''),
            alpha: clamp(rgb[4] ?? 1, 0, 1),
        };
    }

    function colorInput(key, value, allowAlpha = false) {
        const color = window.HuginColorPicker?.parseColor(value, '#000000', 1) || parsedColor(value);
        return `<div class="template-editor__color-input admin-color-picker ${allowAlpha ? 'template-editor__color-input--rgba' : ''}" data-admin-color-picker data-color-format="${allowAlpha ? 'rgba' : 'hex'}" data-color-alpha="${allowAlpha ? 'true' : 'false'}" data-color-preserve-empty="true" data-default-color="${attr(color.hex)}" data-default-alpha="${attr(color.alpha)}" style="--template-color-preview: ${attr(cssColor(value || color.hex))}; --admin-color-preview: ${attr(cssColor(value || color.hex))}"><span class="template-editor__color-swatch" title="${attr(i18n.open_color_picker)}"><span aria-hidden="true"></span><input type="color" data-color-picker-swatch value="${attr(color.hex)}" aria-label="${attr(i18n.open_color_picker)}"></span><input type="text" data-style="${attr(key)}" data-color-value value="${attr(value)}"></div>`;
    }

    function selectInput(dataKind, key, value, options) {
        return `<select data-${dataKind}="${attr(key)}">${options}</select>`;
    }

    function renderElementInspector(element) {
        if (!element) {
            elementPanel.innerHTML = `${panelTitle(i18n.inspector_element)}<div class="template-editor__empty-state">${escapeHtml(i18n.no_selection)}</div>`;
            return;
        }

        const mediaOptionHtml = mediaOptions();
        const fitOptions = `<option value="cover">${escapeHtml(i18n.cover)}</option><option value="contain">${escapeHtml(i18n.contain)}</option>`;
        const binding = ['text', 'media', 'qr'].includes(element.type)
            ? propertySection(i18n.section_binding, propertyRow(i18n.field, selectInput('prop', 'field', element.field || '', fieldOptions(element.field || ''))))
            : '';
        const position = isBackground(element) ? '' : propertySection(i18n.section_position, `
            <div class="template-editor__quad">
                ${propertyRow(i18n.x, numberInput('prop', 'x', coordinateDisplay(element.x), 'step="0.0001" min="0" max="1"'))}
                ${propertyRow(i18n.y, numberInput('prop', 'y', coordinateDisplay(element.y), 'step="0.0001" min="0" max="1"'))}
                ${propertyRow(i18n.w, numberInput('prop', 'w', coordinateDisplay(element.w, 0.02, 1), 'step="0.0001" min="0.02" max="1"'))}
                ${propertyRow(i18n.h, numberInput('prop', 'h', coordinateDisplay(element.h, 0.02, 1), 'step="0.0001" min="0.02" max="1"'))}
            </div>
            ${propertyRow(i18n.z, numberInput('prop', 'z', element.z || 0, 'step="1" min="0" max="999"'))}
        `);

        let content = '';
        if (element.type === 'media') {
            content = propertySection(i18n.section_content, propertyRow(i18n.media, selectInput('style', 'mediaAssetId', element.style?.mediaAssetId || 0, mediaOptionHtml)) + propertyRow(i18n.fit, selectInput('style', 'fit', element.style?.fit || 'cover', fitOptions)));
        } else if (element.type === 'background') {
            content = propertySection(i18n.section_content, propertyRow(i18n.background_media, selectInput('style', 'backgroundMediaAssetId', element.style?.backgroundMediaAssetId || 0, mediaOptionHtml)) + propertyRow(i18n.fit, selectInput('style', 'fit', element.style?.fit || 'cover', fitOptions)));
        }

        const appearanceRows = [];
        if (['text', 'qr'].includes(element.type)) appearanceRows.push(propertyRow(i18n.text_color, colorInput('color', element.style?.color || '')));
        if (['text', 'media', 'qr', 'shape', 'background'].includes(element.type)) appearanceRows.push(propertyRow(i18n.background_color, colorInput('backgroundColor', element.style?.backgroundColor || '', true)));
        if (element.type === 'text') appearanceRows.push(propertyRow(i18n.size, numberInput('style', 'fontSize', element.style?.fontSize || 4, 'step="0.1" min="0.5"')));
        if (!isBackground(element)) appearanceRows.push(propertyRow(i18n.radius, numberInput('style', 'radius', element.style?.radius || 0, 'step="0.1" min="0"')));
        const appearance = propertySection(i18n.section_appearance, appearanceRows.join(''));
        const actions = isBackground(element) ? '' : `<div class="template-editor__property-actions">${iconButton('delete-element', i18n.delete_element, 'delete', 'template-editor__icon-button--danger')}</div>`;

        elementPanel.innerHTML = `${panelTitle(elementLabel(element))}${binding}${position}${content}${appearance}${actions}`;
        elementPanel.querySelectorAll('select[data-style]').forEach(input => { if (input.dataset.style === 'mediaAssetId') input.value = String(element.style?.mediaAssetId || 0); if (input.dataset.style === 'backgroundMediaAssetId') input.value = String(element.style?.backgroundMediaAssetId || 0); if (input.dataset.style === 'fit') input.value = element.style?.fit || 'cover'; });
        window.HuginColorPicker?.init(elementPanel);
        bindElementInspector(element);
    }

    function bindElementInspector(element) {
        elementPanel.querySelectorAll('[data-prop]').forEach(input => {
            const update = () => {
                if (input.dataset.prop === 'field') {
                    element.field = input.value;
                    render();
                    return;
                }
                element[input.dataset.prop] = ['x', 'y'].includes(input.dataset.prop) ? rounded(input.value) : (['w', 'h'].includes(input.dataset.prop) ? rounded(input.value, 0.02, 1) : Number(input.value));
                renderLiveCanvas();
            };
            input.addEventListener('input', update);
            input.addEventListener('change', update);
        });
        elementPanel.querySelectorAll('[data-style]').forEach(input => {
            const update = () => {
                element.style = element.style || {};
                element.style[input.dataset.style] = input.type === 'number' || (input.tagName === 'SELECT' && /AssetId$/.test(input.dataset.style)) ? Number(input.value) : input.value;
                renderLiveCanvas();
            };
            input.addEventListener('input', update);
            input.addEventListener('change', update);
        });
        elementPanel.querySelectorAll('[data-delete-element]').forEach(button => button.addEventListener('click', () => {
            if (isBackground(element)) return;
            spec().elements = spec().elements.filter(item => item.id !== element.id);
            selectedId = null;
            render();
        }));
    }

    function renderFieldsPanel() {
        const element = selectedElement();
        fieldsPanel.innerHTML = panelTitle(i18n.inspector_fields);
        if (!element) {
            fieldsPanel.innerHTML += `<div class="template-editor__empty-state">${escapeHtml(i18n.no_field_selection)}</div>`;
            return;
        }
        if (!fieldCapableElement(element)) {
            fieldsPanel.innerHTML += `<div class="template-editor__empty-state">${escapeHtml(i18n.element_cannot_use_fields)}</div>`;
            return;
        }
        const field = fieldForElement(element);
        if (!field) {
            fieldsPanel.innerHTML += `<div class="template-editor__empty-state">${escapeHtml(i18n.element_has_no_field)}</div><button type="button" class="button button--normal button--small" data-create-bind-field>${icons.add || ''}<span>${escapeHtml(i18n.create_and_bind_field)}</span></button>`;
            fieldsPanel.querySelector('[data-create-bind-field]').addEventListener('click', () => { createAndBindField(element); });
            return;
        }
        const index = spec().fields.indexOf(field);
        const row = document.createElement('div');
        row.className = 'template-editor__field-row';
        row.innerHTML = `
            <div class="template-editor__field-row-head">
                <strong>${escapeHtml(field.label || field.key || i18n.field_1.replace('1', String(index + 1)))}</strong>
                ${iconButton('remove-bound-field', i18n.remove_field_tooltip, 'delete', 'template-editor__icon-button--danger')}
            </div>
            <div class="template-editor__field-grid">
                <label>${escapeHtml(i18n.field_key)}<input value="${attr(field.key)}" data-field-key></label>
                <label>${escapeHtml(i18n.field_label)}<input value="${attr(field.label)}" data-field-label></label>
                <label>${escapeHtml(i18n.field)}<select data-field-type><option value="text">${escapeHtml(i18n.text)}</option><option value="multiline">${escapeHtml(i18n.multiline)}</option><option value="url">${escapeHtml(i18n.url)}</option><option value="media_image">${escapeHtml(i18n.media_image)}</option><option value="media_video">${escapeHtml(i18n.media_video)}</option><option value="qr_url">${escapeHtml(i18n.qr_url)}</option><option value="color">${escapeHtml(i18n.color)}</option></select></label>
                <label class="checkbox-row"><input type="checkbox" data-field-required ${field.required ? 'checked' : ''}> ${escapeHtml(i18n.field_required)}</label>
            </div>`;
        row.querySelector('[data-field-type]').value = field.type || defaultFieldTypeForElement(element);
        row.querySelectorAll('input, select').forEach(input => {
            const update = () => {
                const oldKey = field.key;
                const newKey = normalizeKey(row.querySelector('[data-field-key]').value || `field_${index + 1}`);
                field.key = newKey;
                field.label = row.querySelector('[data-field-label]').value || field.key;
                field.type = row.querySelector('[data-field-type]').value;
                field.required = row.querySelector('[data-field-required]').checked;
                if (oldKey !== newKey) {
                    spec().elements.forEach(item => { if (item.field === oldKey) item.field = newKey; });
                }
                element.field = newKey;
                render();
            };
            input.addEventListener('input', update);
            input.addEventListener('change', update);
        });
        row.querySelector('[data-remove-bound-field]').addEventListener('click', () => {
            const key = field.key;
            spec().fields = spec().fields.filter(item => item !== field);
            clearFieldReferences(key);
            render();
        });
        const list = document.createElement('div');
        list.className = 'template-editor__field-list';
        list.appendChild(row);
        fieldsPanel.appendChild(list);
    }

    function fieldCapableElement(element) {
        return ['text', 'media', 'qr'].includes(element?.type || '');
    }

    function fieldForElement(element) {
        if (!fieldCapableElement(element) || !element.field) return null;
        return spec().fields.find(field => field.key === element.field) || null;
    }

    function defaultFieldTypeForElement(element) {
        if (element?.type === 'media') return 'media_image';
        if (element?.type === 'qr') return 'qr_url';
        return 'text';
    }

    function createAndBindField(element) {
        if (!fieldCapableElement(element)) return;
        activeInspectorTab = 'fields';
        const fieldNumber = spec().fields.length + 1;
        const key = normalizeKey(`field_${fieldNumber}`);
        const field = { key, label: `${i18n.field_1.replace('1', String(fieldNumber))}`, type: defaultFieldTypeForElement(element), required: false, default: '' };
        spec().fields.push(field);
        element.field = key;
        render();
    }

    function clearFieldReferences(fieldKey) {
        spec().elements.forEach(element => { if (element.field === fieldKey) element.field = ''; });
    }

    function renderLayersPanel() {
        layersPanel.innerHTML = panelTitle(i18n.inspector_layers);
        const list = document.createElement('div');
        list.className = 'template-editor__layer-list';
        spec().elements.slice().sort((a, b) => (b.z || 0) - (a.z || 0)).forEach(element => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'template-editor__layer';
            if (element.id === selectedId) button.classList.add('is-selected');
            button.title = i18n.select_layer_tooltip;
            button.setAttribute('aria-label', `${i18n.select_layer_tooltip}: ${elementLabel(element)}`);
            button.innerHTML = `<span class="template-editor__layer-icon" aria-hidden="true">${icons.move || ''}</span><span>${escapeHtml(elementLabel(element))}</span><small>${escapeHtml(element.type)}</small>`;
            button.addEventListener('click', () => { selectElement(element.id); });
            list.appendChild(button);
        });
        layersPanel.appendChild(list);
    }

    function normalizeKey(value) { return String(value || '').toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '') || 'field'; }

    function addElement(type) {
        const z = Math.max(0, ...spec().elements.map(element => Number(element.z || 0))) + 1;
        const element = { id: uid(type), type, field: '', x: 0.2, y: 0.2, w: 0.35, h: 0.22, z, style: { backgroundColor: type === 'shape' ? 'rgba(255,255,255,0.35)' : 'rgba(15,23,42,0.55)', color: '#ffffff', fontSize: 4, radius: 1, fit: 'cover' } };
        if (type === 'qr') { element.w = 0.18; element.h = 0.32; element.style.backgroundColor = 'rgba(255,255,255,1)'; element.style.color = 'rgba(15,23,42,1)'; }
        if (type === 'media') { element.w = 0.42; element.h = 0.32; }
        spec().elements.push(element);
        selectedId = element.id;
        activeInspectorTab = 'element';
        render();
    }

    function startDrag(event) {
        if (event.target.classList.contains('template-editor__resize')) return;
        const element = spec().elements.find(item => item.id === event.currentTarget.dataset.elementId);
        if (!element) return;
        selectedId = element.id;
        if (isBackground(element)) { render(); return; }
        const box = canvas.getBoundingClientRect();
        const start = { x: event.clientX, y: event.clientY, left: element.x, top: element.y };
        event.currentTarget.setPointerCapture(event.pointerId);
        const move = (moveEvent) => { element.x = rounded(start.left + ((moveEvent.clientX - start.x) / box.width), 0, 1 - element.w); element.y = rounded(start.top + ((moveEvent.clientY - start.y) / box.height), 0, 1 - element.h); render(); };
        const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    function startResize(event) {
        event.stopPropagation();
        const element = spec().elements.find(item => item.id === event.currentTarget.parentElement.dataset.elementId);
        if (!element || isBackground(element)) return;
        const box = canvas.getBoundingClientRect();
        const start = { x: event.clientX, y: event.clientY, w: element.w, h: element.h };
        const move = (moveEvent) => { element.w = rounded(start.w + ((moveEvent.clientX - start.x) / box.width), 0.02, 1 - element.x); element.h = rounded(start.h + ((moveEvent.clientY - start.y) / box.height), 0.02, 1 - element.y); render(); };
        const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    editor.querySelectorAll('[data-orientation-tab]').forEach(button => button.addEventListener('click', () => {
        editor.querySelectorAll('[data-orientation-tab]').forEach(tab => tab.classList.toggle('is-active', tab === button));
        orientation = button.dataset.orientationTab;
        selectedId = null;
        spec();
        render();
    }));
    inspectorTabs.forEach(button => button.addEventListener('click', () => { setInspectorTab(button.dataset.inspectorTab); }));
    editor.querySelectorAll('[data-add-element]').forEach(button => button.addEventListener('click', () => addElement(button.dataset.addElement)));
    function exportJson(orientationName) {
        const source = orientationName === 'portrait' ? specs.portrait : specs.landscape;
        const json = source ? JSON.stringify(normalizedSpecForSave(source), null, 2) : '';
        const blob = new Blob([json], { type: 'application/json' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `hugin-template-${orientationName}.json`;
        link.click();
        window.setTimeout(() => { URL.revokeObjectURL(link.href); }, 0);
    }

    function importJson(orientationName, file) {
        if (!file) return;
        const reader = new FileReader();
        reader.addEventListener('load', () => {
            try {
                const parsed = JSON.parse(String(reader.result || ''));
                if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) throw new Error('Invalid JSON');
                specs[orientationName] = parsed;
                selectedId = null;
                render();
            } catch (error) {
                window.alert(i18n.import_invalid || 'The selected JSON file is invalid.');
            }
        });
        reader.readAsText(file);
    }

    form.querySelectorAll('[data-json-export]').forEach(button => button.addEventListener('click', () => { exportJson(button.dataset.jsonExport); }));
    form.querySelectorAll('[data-json-import-trigger]').forEach(button => button.addEventListener('click', () => {
        form.querySelector(`[data-json-import="${button.dataset.jsonImportTrigger}"]`)?.click();
    }));
    form.querySelectorAll('[data-json-import]').forEach(input => input.addEventListener('change', () => {
        importJson(input.dataset.jsonImport, input.files?.[0] || null);
        input.value = '';
    }));
    form.addEventListener('submit', syncHidden);
    render();
})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
