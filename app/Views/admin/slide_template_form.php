<?php
$formId = 'template';
$template = $templateModel ?? [];
$title = !empty($template['id']) ? __('templates.edit_title') : __('templates.create_title');
$landscapeJson = json_encode($landscapeSpec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$portraitJson = json_encode($portraitSpec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$publicFonts = is_array($publicFonts ?? null) ? $publicFonts : [];
$fontToolOptions = [
    [
        'value' => '',
        'label' => __('templates.font_default'),
        'css' => '',
    ],
];
foreach (\App\Core\TemplateSlideService::systemFontOptions() as $fontKey => $fontStack) {
    $token = 'system:' . $fontKey;
    $fontToolOptions[] = [
        'value' => $token,
        'label' => __('templates.font_system_' . str_replace('-', '_', $fontKey)),
        'css' => \App\Core\TemplateSlideService::fontFamilyCssForToken($token, $publicFonts) ?? $fontStack,
    ];
}
foreach ($publicFonts as $fontFamily => $font) {
    $token = 'local:' . $fontFamily;
    $fontToolOptions[] = [
        'value' => $token,
        'label' => (string)($font['label'] ?? $fontFamily),
        'css' => \App\Core\TemplateSlideService::fontFamilyCssForToken($token, $publicFonts) ?? '',
    ];
}
$fontOptionsJson = json_encode($fontToolOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$mediaJson = json_encode(array_map(static function (array $asset): array {
    return [
        'id' => (int)$asset['id'],
        'name' => (string)$asset['name'],
        'original_name' => (string)$asset['original_name'],
        'kind' => (string)$asset['media_kind'],
        'url' => ($asset['file_path'] ?? '') !== '' ? url((string)$asset['file_path']) : '',
        'preview_url' => ($asset['media_kind'] ?? '') === 'video' && !empty($asset['preview_file_path'])
            ? url('/api/media/' . (int)$asset['id'] . '/preview')
            : '',
    ];
}, $mediaAssets ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$shapeToolOptions = [
    ['key' => 'box', 'label' => __('templates.shape_box'), 'svg' => '<rect x="10" y="10" width="80" height="80" rx="8"></rect>'],
    ['key' => 'square', 'label' => __('templates.shape_square'), 'svg' => '<rect x="12" y="12" width="76" height="76" rx="2"></rect>'],
    ['key' => 'circle', 'label' => __('templates.shape_circle'), 'svg' => '<ellipse cx="50" cy="50" rx="39" ry="39"></ellipse>'],
    ['key' => 'triangle', 'label' => __('templates.shape_triangle'), 'svg' => '<polygon points="50,10 90,88 10,88"></polygon>'],
    ['key' => 'diamond', 'label' => __('templates.shape_diamond'), 'svg' => '<polygon points="50,8 92,50 50,92 8,50"></polygon>'],
    ['key' => 'star', 'label' => __('templates.shape_star'), 'svg' => '<polygon points="50,8 62,35 91,38 69,58 75,88 50,72 25,88 31,58 9,38 38,35"></polygon>'],
    ['key' => 'hexagon', 'label' => __('templates.shape_hexagon'), 'svg' => '<polygon points="50,8 86,29 86,71 50,92 14,71 14,29"></polygon>'],
    ['key' => 'pentagon', 'label' => __('templates.shape_pentagon'), 'svg' => '<polygon points="50,8 90,38 75,88 25,88 10,38"></polygon>'],
    ['key' => 'arrow', 'label' => __('templates.shape_arrow'), 'svg' => '<polygon points="10,30 56,30 56,10 92,50 56,90 56,70 10,70"></polygon>'],
];
$editorI18n = [
    'background' => __('templates.element_background'),
    'element_text' => __('templates.element_text'),
    'element_media' => __('templates.element_media'),
    'element_qr' => __('templates.element_qr'),
    'element_shape' => __('templates.element_shape'),
    'element_datetime' => __('templates.element_datetime'),
    'element_countdown' => __('templates.element_countdown'),
    'field' => __('templates.property_field'),
    'field_key' => __('templates.field_key'),
    'field_label' => __('templates.field_label'),
    'field_default' => __('templates.field_default'),
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
    'w_short' => __('templates.property_w_short'),
    'h' => __('templates.property_h'),
    'h_short' => __('templates.property_h_short'),
    'z' => __('templates.property_z'),
    'text_color' => __('templates.property_color'),
    'background_color' => __('templates.property_background'),
    'font' => __('templates.property_font'),
    'outline_color' => __('templates.property_outline_color'),
    'outline_width' => __('templates.property_outline_width'),
    'shape_type' => __('templates.property_shape_type'),
    'datetime_mode' => __('templates.property_datetime_mode'),
    'datetime_mode_clock' => __('templates.datetime_mode_clock'),
    'datetime_mode_date' => __('templates.datetime_mode_date'),
    'time_format' => __('templates.property_time_format'),
    'time_format_24h' => __('templates.time_format_24h'),
    'time_format_ampm' => __('templates.time_format_ampm'),
    'countdown_target' => __('templates.property_countdown_target'),
    'drop_shadow' => __('templates.property_drop_shadow'),
    'drop_shadow_enabled' => __('templates.property_drop_shadow_enabled'),
    'drop_shadow_direction' => __('templates.property_drop_shadow_direction'),
    'drop_shadow_offset' => __('templates.property_drop_shadow_offset'),
    'drop_shadow_blur' => __('templates.property_drop_shadow_blur'),
    'drop_shadow_color' => __('templates.property_drop_shadow_color'),
    'radius' => __('templates.property_radius'),
    'size' => __('templates.property_size'),
    'media' => __('templates.property_media'),
    'background_media' => __('templates.property_background_media'),
    'fit' => __('templates.property_fit'),
    'cover' => __('templates.fit_cover'),
    'contain' => __('templates.fit_contain'),
    'contain_blur' => __('templates.fit_contain_blur'),
    'remove' => __('common.remove'),
    'delete' => __('common.delete'),
    'none' => __('common.none'),
    'inspector_element' => __('templates.inspector_element'),
    'inspector_fields' => __('templates.inspector_fields'),
    'inspector_layers' => __('templates.inspector_layers'),
    'inspector_animations' => __('templates.inspector_animations'),
    'section_position' => __('templates.section_position'),
    'section_binding' => __('templates.section_binding'),
    'section_content' => __('templates.section_content'),
    'section_appearance' => __('templates.section_appearance'),
    'section_entrance_animation' => __('templates.section_entrance_animation'),
    'section_continuous_animation' => __('templates.section_continuous_animation'),
    'canvas_label' => __('templates.canvas_label'),
    'canvas_instructions' => __('templates.canvas_instructions'),
    'element_accessible_label' => __('templates.element_accessible_label'),
    'element_selected_accessible_label' => __('templates.element_selected_accessible_label'),
    'element_position_status' => __('templates.element_position_status'),
    'element_selected_status' => __('templates.element_selected_status'),
    'element_added_status' => __('templates.element_added_status'),
    'element_deleted_status' => __('templates.element_deleted_status'),
    'element_not_movable_status' => __('templates.element_not_movable_status'),
    'delete_unused_field_confirm' => __('templates.delete_unused_field_confirm'),
    'layer_keyboard_hint' => __('templates.layer_keyboard_hint'),
    'layer_moved_status' => __('templates.layer_moved_status'),
    'empty_fields' => __('templates.empty_fields'),
    'no_selection' => __('templates.no_selection'),
    'no_field_selection' => __('templates.no_field_selection'),
    'no_animation_selection' => __('templates.no_animation_selection'),
    'background_cannot_animate' => __('templates.background_cannot_animate'),
    'element_has_no_field' => __('templates.element_has_no_field'),
    'element_cannot_use_fields' => __('templates.element_cannot_use_fields'),
    'create_and_bind_field' => __('templates.create_and_bind_field'),
    'delete_element' => __('templates.delete_element'),
    'shortcut_add_text' => __('templates.shortcut_add_text'),
    'shortcut_add_media' => __('templates.shortcut_add_media'),
    'shortcut_add_qr' => __('templates.shortcut_add_qr'),
    'shortcut_add_shape' => __('templates.shortcut_add_shape'),
    'shortcut_add_datetime' => __('templates.shortcut_add_datetime'),
    'shortcut_add_countdown' => __('templates.shortcut_add_countdown'),
    'shape_dropdown_label' => __('templates.shape_dropdown_label'),
    'shape_box' => __('templates.shape_box'),
    'shape_square' => __('templates.shape_square'),
    'shape_circle' => __('templates.shape_circle'),
    'shape_triangle' => __('templates.shape_triangle'),
    'shape_diamond' => __('templates.shape_diamond'),
    'shape_star' => __('templates.shape_star'),
    'shape_hexagon' => __('templates.shape_hexagon'),
    'shape_pentagon' => __('templates.shape_pentagon'),
    'shape_arrow' => __('templates.shape_arrow'),
    'shadow_direction_top' => __('templates.shadow_direction_top'),
    'shadow_direction_top_right' => __('templates.shadow_direction_top_right'),
    'shadow_direction_right' => __('templates.shadow_direction_right'),
    'shadow_direction_bottom_right' => __('templates.shadow_direction_bottom_right'),
    'shadow_direction_bottom' => __('templates.shadow_direction_bottom'),
    'shadow_direction_bottom_left' => __('templates.shadow_direction_bottom_left'),
    'shadow_direction_left' => __('templates.shadow_direction_left'),
    'shadow_direction_top_left' => __('templates.shadow_direction_top_left'),
    'add_field_tooltip' => __('templates.add_field_tooltip'),
    'remove_field_tooltip' => __('templates.remove_field_tooltip'),
    'select_layer_tooltip' => __('templates.select_layer_tooltip'),
    'layer_group_background' => __('templates.layer_group_background'),
    'layer_group_content' => __('templates.layer_group_content'),
    'layer_group_overlay' => __('templates.layer_group_overlay'),
    'layer_empty_group' => __('templates.layer_empty_group'),
    'layer_locked' => __('templates.layer_locked'),
    'layer_drag_tooltip' => __('templates.layer_drag_tooltip'),
    'layer_position_hint' => __('templates.layer_position_hint'),
    'import_invalid' => __('templates.import_invalid'),
    'unsaved_changes_confirm' => __('templates.unsaved_changes_confirm'),
    'open_color_picker' => __('templates.open_color_picker'),
    'color_opacity' => __('templates.color_opacity'),
    'animation_entrance' => __('templates.animation_entrance'),
    'animation_continuous' => __('templates.animation_continuous'),
    'animation_delay' => __('templates.animation_delay'),
    'animation_duration' => __('templates.animation_duration'),
    'animation_easing' => __('templates.animation_easing'),
    'animation_direction' => __('templates.animation_direction'),
    'animation_fade_in' => __('templates.animation_fade_in'),
    'animation_fade_up' => __('templates.animation_fade_up'),
    'animation_fade_down' => __('templates.animation_fade_down'),
    'animation_slide_left' => __('templates.animation_slide_left'),
    'animation_slide_right' => __('templates.animation_slide_right'),
    'animation_slide_up' => __('templates.animation_slide_up'),
    'animation_slide_down' => __('templates.animation_slide_down'),
    'animation_zoom_in' => __('templates.animation_zoom_in'),
    'animation_pop_in' => __('templates.animation_pop_in'),
    'animation_blur_in' => __('templates.animation_blur_in'),
    'animation_pulse' => __('templates.animation_pulse'),
    'animation_float' => __('templates.animation_float'),
    'animation_slow_zoom' => __('templates.animation_slow_zoom'),
    'animation_wiggle' => __('templates.animation_wiggle'),
    'animation_glow' => __('templates.animation_glow'),
    'animation_rotate_slow' => __('templates.animation_rotate_slow'),
    'animation_ease' => __('templates.animation_ease'),
    'animation_ease_out' => __('templates.animation_ease_out'),
    'animation_ease_in_out' => __('templates.animation_ease_in_out'),
    'animation_linear' => __('templates.animation_linear'),
    'animation_direction_normal' => __('templates.animation_direction_normal'),
    'animation_direction_alternate' => __('templates.animation_direction_alternate'),
    'animation_direction_reverse' => __('templates.animation_direction_reverse'),
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
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<?php if ($publicFonts): ?>
    <style data-template-editor-fonts>
        <?php foreach ($publicFonts as $fontFamily => $font): ?>
            @font-face {
                font-family: '<?= e($fontFamily) ?>';
                font-display: swap;
                src: <?= $font['src'] ?>;
            }
        <?php endforeach; ?>
    </style>
<?php endif; ?>
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
            <label class="template-editor__snap-toggle">
                <input type="checkbox" data-snap-to-grid checked>
                <span><?= e(__('templates.snap_to_grid')) ?></span>
            </label>
            <div class="template-editor__tools">
                <button type="button" class="template-tool-button template-tool-button--text" data-add-element="text" aria-label="<?= e(__('templates.add_element_accessible_label', ['type' => __('templates.element_text')])) ?>" title="<?= e(__('templates.add_element_shortcut_tooltip', ['type' => __('templates.element_text'), 'shortcut' => __('templates.shortcut_add_text')])) ?>">
                    <span class="template-tool-button__icon" aria-hidden="true"><img src="<?= e(url('/assets/icons/admin/template-tool-text.png')) ?>" alt=""></span>
                    <span class="template-tool-button__label"><?= e(__('templates.element_text')) ?></span>
                </button>
                <button type="button" class="template-tool-button template-tool-button--media" data-add-element="media" aria-label="<?= e(__('templates.add_element_accessible_label', ['type' => __('templates.element_media')])) ?>" title="<?= e(__('templates.add_element_shortcut_tooltip', ['type' => __('templates.element_media'), 'shortcut' => __('templates.shortcut_add_media')])) ?>">
                    <span class="template-tool-button__icon" aria-hidden="true"><img src="<?= e(url('/assets/icons/admin/template-tool-media.png')) ?>" alt=""></span>
                    <span class="template-tool-button__label"><?= e(__('templates.element_media')) ?></span>
                </button>
                <button type="button" class="template-tool-button template-tool-button--qr" data-add-element="qr" aria-label="<?= e(__('templates.add_element_accessible_label', ['type' => __('templates.element_qr')])) ?>" title="<?= e(__('templates.add_element_shortcut_tooltip', ['type' => __('templates.element_qr'), 'shortcut' => __('templates.shortcut_add_qr')])) ?>">
                    <span class="template-tool-button__icon" aria-hidden="true"><img src="<?= e(url('/assets/icons/admin/template-tool-qr.png')) ?>" alt=""></span>
                    <span class="template-tool-button__label"><?= e(__('templates.element_qr')) ?></span>
                </button>
                <button type="button" class="template-tool-button template-tool-button--datetime" data-add-element="datetime" aria-label="<?= e(__('templates.add_element_accessible_label', ['type' => __('templates.element_datetime')])) ?>" title="<?= e(__('templates.add_element_shortcut_tooltip', ['type' => __('templates.element_datetime'), 'shortcut' => __('templates.shortcut_add_datetime')])) ?>">
                    <span class="template-tool-button__icon" aria-hidden="true"><?= admin_icon('schedules') ?></span>
                    <span class="template-tool-button__label"><?= e(__('templates.element_datetime')) ?></span>
                </button>
                <button type="button" class="template-tool-button template-tool-button--countdown" data-add-element="countdown" aria-label="<?= e(__('templates.add_element_accessible_label', ['type' => __('templates.element_countdown')])) ?>" title="<?= e(__('templates.add_element_shortcut_tooltip', ['type' => __('templates.element_countdown'), 'shortcut' => __('templates.shortcut_add_countdown')])) ?>">
                    <span class="template-tool-button__icon" aria-hidden="true"><?= admin_icon('reload') ?></span>
                    <span class="template-tool-button__label"><?= e(__('templates.element_countdown')) ?></span>
                </button>
                <div class="template-tool-split template-tool-split--shape" data-shape-dropdown>
                    <button type="button" class="template-tool-button template-tool-button--shape" data-add-element="shape" data-shape-type="box" aria-label="<?= e(__('templates.add_element_accessible_label', ['type' => __('templates.element_shape')])) ?>" title="<?= e(__('templates.add_element_shortcut_tooltip', ['type' => __('templates.element_shape'), 'shortcut' => __('templates.shortcut_add_shape')])) ?>">
                        <span class="template-tool-button__icon" aria-hidden="true"><img src="<?= e(url('/assets/icons/admin/template-tool-shape.png')) ?>" alt=""></span>
                        <span class="template-tool-button__label"><?= e(__('templates.element_shape')) ?></span>
                    </button>
                    <button type="button" class="template-tool-dropdown-toggle" data-shape-dropdown-toggle aria-haspopup="true" aria-expanded="false" aria-label="<?= e(__('templates.shape_dropdown_label')) ?>" title="<?= e(__('templates.shape_dropdown_label')) ?>"><span aria-hidden="true"></span></button>
                    <div class="template-tool-menu" data-shape-dropdown-menu role="menu" hidden>
                        <?php foreach ($shapeToolOptions as $shapeOption): ?>
                            <button type="button" class="template-tool-menu__item" data-add-shape="<?= e($shapeOption['key']) ?>" role="menuitem">
                                <span class="template-tool-menu__shape" aria-hidden="true"><svg viewBox="0 0 100 100" preserveAspectRatio="none" focusable="false"><?= $shapeOption['svg'] ?></svg></span>
                                <span><?= e($shapeOption['label']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="template-editor__layout">
            <div class="template-editor__canvas-shell">
                <div class="template-editor__canvas-tabs" role="tablist" aria-label="<?= e(__('common.orientation')) ?>">
                    <button type="button" class="is-active" data-orientation-tab="landscape" role="tab" aria-selected="true"><?= e(__('orientations.landscape')) ?></button>
                    <button type="button" data-orientation-tab="portrait" role="tab" aria-selected="false" tabindex="-1"><?= e(__('orientations.vertical')) ?></button>
                </div>
                <div class="template-editor__canvas-frame">
                    <p id="template-editor-canvas-instructions" class="sr-only"><?= e(__('templates.canvas_instructions')) ?></p>
                    <div id="template-editor-canvas-status" class="sr-only" aria-live="polite" aria-atomic="true" data-editor-status></div>
                    <div class="template-editor__canvas" data-editor-canvas role="group" aria-label="<?= e(__('templates.canvas_label')) ?>" aria-describedby="template-editor-canvas-instructions"></div>
                </div>
            </div>
            <aside class="template-editor__inspector" data-editor-inspector>
                <div class="template-editor__inspector-tabbar">
                    <button type="button" class="template-editor__tab-scroll" data-tab-scroll="left" aria-label="<?= e(__('common.previous')) ?>">&#8249;</button>
                    <div class="template-editor__inspector-tabs" role="tablist" aria-label="<?= e(__('templates.properties')) ?>" data-inspector-tabs-scroll>
                        <button type="button" class="is-active" data-inspector-tab="element" title="<?= e(__('templates.inspector_element')) ?>" aria-label="<?= e(__('templates.inspector_element')) ?>"><?= admin_icon('settings') ?><span><?= e(__('templates.inspector_element')) ?></span></button>
                        <button type="button" data-inspector-tab="fields" title="<?= e(__('templates.inspector_fields')) ?>" aria-label="<?= e(__('templates.inspector_fields')) ?>"><?= admin_icon('add') ?><span><?= e(__('templates.inspector_fields')) ?></span></button>
                        <button type="button" data-inspector-tab="layers" title="<?= e(__('templates.inspector_layers')) ?>" aria-label="<?= e(__('templates.inspector_layers')) ?>"><?= admin_icon('move') ?><span><?= e(__('templates.inspector_layers')) ?></span></button>
                        <button type="button" data-inspector-tab="animations" title="<?= e(__('templates.inspector_animations')) ?>" aria-label="<?= e(__('templates.inspector_animations')) ?>"><?= admin_icon('reload') ?><span><?= e(__('templates.inspector_animations')) ?></span></button>
                    </div>
                    <button type="button" class="template-editor__tab-scroll" data-tab-scroll="right" aria-label="<?= e(__('common.next')) ?>">&#8250;</button>
                </div>
                <div class="template-editor__inspector-body">
                    <section class="template-editor__inspector-panel" data-inspector-panel="element"></section>
                    <section class="template-editor__inspector-panel" data-inspector-panel="fields" hidden></section>
                    <section class="template-editor__inspector-panel" data-inspector-panel="layers" hidden></section>
                    <section class="template-editor__inspector-panel" data-inspector-panel="animations" hidden></section>
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
        <button type="submit" name="save_action" value="save" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
        <button type="submit" name="save_action" value="save_and_close" class="button button--normal"><?= admin_icon('save') ?><span><?= e(__('templates.save_and_close')) ?></span></button>
        <a class="button button--normal" href="<?= e(url('/admin/slide-templates')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
    </div>
</form>
<script src="<?= e(asset_url('/assets/js/hugin-qr.js')) ?>"></script>
<script>
(() => {
    const mediaAssets = <?= $mediaJson ?: '[]' ?>;
    const fontOptions = <?= $fontOptionsJson ?: '[]' ?>;
    const mediaById = new Map(mediaAssets.map(asset => [Number(asset.id), asset]));
    const previewQrUrl = 'https://hugin.local/template-preview';
    const layerGroups = [
        { key: 'background', base: 0, locked: true },
        { key: 'content', base: 100, locked: false },
        { key: 'overlay', base: 700, locked: false },
    ];
    const entranceAnimations = ['none', 'fade-in', 'fade-up', 'fade-down', 'slide-left', 'slide-right', 'slide-up', 'slide-down', 'zoom-in', 'pop-in', 'blur-in'];
    const continuousAnimations = ['none', 'pulse', 'float', 'slow-zoom', 'wiggle', 'glow', 'rotate-slow'];
    const animationEasings = ['ease', 'ease-out', 'ease-in-out', 'linear'];
    const animationDirections = ['normal', 'alternate', 'reverse'];
    const shapeTypes = ['box', 'square', 'circle', 'triangle', 'diamond', 'star', 'hexagon', 'pentagon', 'arrow'];
    const dateTimeModes = ['clock', 'date'];
    const timeFormats = ['24h', 'ampm'];
    const dropShadowDirections = ['top', 'top-right', 'right', 'bottom-right', 'bottom', 'bottom-left', 'left', 'top-left'];
    const addElementShortcuts = { Digit1: 'text', Digit2: 'media', Digit3: 'qr', Digit4: 'shape', Digit5: 'datetime', Digit6: 'countdown' };
    const snapColumns = 24;
    const snapGuidePixelThreshold = 9;
    const snapGuidePrecision = 0.0001;
    const defaultAnimation = () => ({ entrance: 'none', continuous: 'none', entranceDelayMs: 0, entranceDurationMs: 600, continuousDurationMs: 3000, easing: 'ease-out', direction: 'normal' });
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
    let draggedLayerElementId = '';
    let pendingFocusElementId = '';
    let isDeletingElement = false;

    const editor = document.querySelector('[data-template-editor]');
    const form = document.querySelector('[data-template-editor-form]');
    const canvas = editor.querySelector('[data-editor-canvas]');
    const inspectorTabs = editor.querySelectorAll('[data-inspector-tab]');
    const inspectorTabsScroll = editor.querySelector('[data-inspector-tabs-scroll]');
    const inspectorTabScrollButtons = editor.querySelectorAll('[data-tab-scroll]');
    const inspectorPanels = editor.querySelectorAll('[data-inspector-panel]');
    const elementPanel = editor.querySelector('[data-inspector-panel="element"]');
    const fieldsPanel = editor.querySelector('[data-inspector-panel="fields"]');
    const layersPanel = editor.querySelector('[data-inspector-panel="layers"]');
    const animationsPanel = editor.querySelector('[data-inspector-panel="animations"]');
    const hiddenLandscape = form.querySelector('[data-template-spec="landscape"]');
    const hiddenPortrait = form.querySelector('[data-template-spec="portrait"]');
    const debugLandscape = form.querySelector('[data-json-debug="landscape"]');
    const debugPortrait = form.querySelector('[data-json-debug="portrait"]');
    const snapToGridToggle = editor.querySelector('[data-snap-to-grid]');
    const editorStatus = editor.querySelector('[data-editor-status]');
    const shapeDropdown = editor.querySelector('[data-shape-dropdown]');
    const shapeDropdownToggle = editor.querySelector('[data-shape-dropdown-toggle]');
    const shapeDropdownMenu = editor.querySelector('[data-shape-dropdown-menu]');

    function uid(prefix) { return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`; }
    function spec() { if (!specs[orientation]) specs[orientation] = defaults[orientation](); return specs[orientation]; }
    function clamp(value, min, max) { return Math.max(min, Math.min(max, Number(String(value).replace(',', '.')) || 0)); }
    function rounded(value, min = 0, max = 1) { return Number(clamp(value, min, max).toFixed(4)); }
    function coordinateDisplay(value, min = 0, max = 1) { return rounded(value, min, max).toFixed(4); }
    function pct(value) { return `${clamp(value, 0, 1) * 100}%`; }
    function snapEnabled() { return snapToGridToggle?.checked !== false; }
    function snapSteps() {
        const current = spec();
        const ratio = Math.max(0.1, Number(current.canvas?.width || 1) / Math.max(1, Number(current.canvas?.height || 1)));
        return { x: 1 / snapColumns, y: (1 / snapColumns) * ratio };
    }
    function visualSquareHeightForWidth(width) {
        const current = spec();
        const ratio = Math.max(0.1, Number(current.canvas?.width || 1) / Math.max(1, Number(current.canvas?.height || 1)));
        return rounded(width * ratio, 0.02, 1);
    }
    function snapGridValue(value, step, min = 0, max = 1) {
        if (!snapEnabled()) return rounded(value, min, max);
        if (!Number.isFinite(step) || step <= 0) return rounded(value, min, max);
        return rounded(Math.round(clamp(value, min, max) / step) * step, min, max);
    }
    function axisStart(element, axis) { return Number(element?.[axis] || 0); }
    function axisSize(element, axis) { return Number(element?.[axis === 'x' ? 'w' : 'h'] || 0); }
    function axisPixelLength(axis) {
        const box = canvas.getBoundingClientRect();
        const rendered = axis === 'x' ? box.width : box.height;
        if (rendered > 0) return rendered;
        const current = spec();
        return Number(current.canvas?.[axis === 'x' ? 'width' : 'height'] || 1000);
    }
    function snapGuideThreshold(axis) {
        return Math.min(0.04, Math.max(0.0025, snapGuidePixelThreshold / axisPixelLength(axis)));
    }
    function snapSourcePriority(source) {
        if (source === 'canvas') return 0;
        if (source === 'element') return 1;
        return 2;
    }
    function addSnapGuide(guides, value, source) {
        const numeric = Number(value);
        if (!Number.isFinite(numeric)) return;
        const guide = rounded(numeric);
        if (guide < 0 || guide > 1) return;
        guides.push({ value: guide, source });
    }
    function uniqueSnapGuides(guides) {
        const byValue = new Map();
        guides.forEach(guide => {
            const key = guide.value.toFixed(4);
            const existing = byValue.get(key);
            if (!existing || snapSourcePriority(guide.source) < snapSourcePriority(existing.source)) {
                byValue.set(key, guide);
            }
        });
        return Array.from(byValue.values());
    }
    function snapAlignmentGuides(axis, element) {
        const guides = [];
        [0, 0.5, 1].forEach(value => addSnapGuide(guides, value, 'canvas'));
        spec().elements.forEach(item => {
            if (!item || item.id === element.id || isBackground(item)) return;
            const start = axisStart(item, axis);
            const size = axisSize(item, axis);
            addSnapGuide(guides, start, 'element');
            addSnapGuide(guides, start + (size / 2), 'element');
            addSnapGuide(guides, start + size, 'element');
        });
        return uniqueSnapGuides(guides);
    }
    function addSnapCandidate(candidates, candidate, proposed, min, max, threshold, source) {
        if (candidate < min - snapGuidePrecision || candidate > max + snapGuidePrecision) return;
        const value = rounded(candidate, min, max);
        const distance = Math.abs(value - proposed);
        if (threshold !== null && distance > threshold) return;
        candidates.push({ value, distance, priority: snapSourcePriority(source) });
    }
    function bestSnapCandidate(candidates) {
        if (candidates.length === 0) return null;
        candidates.sort((a, b) => (a.distance - b.distance) || (a.priority - b.priority));
        return candidates[0].value;
    }
    function snapAxisPosition(element, axis, value) {
        const size = axisSize(element, axis);
        const min = 0;
        const max = Math.max(0, 1 - size);
        const proposed = clamp(value, min, max);
        if (!snapEnabled()) return rounded(proposed, min, max);

        const threshold = snapGuideThreshold(axis);
        const guideCandidates = [];
        snapAlignmentGuides(axis, element).forEach(guide => {
            addSnapCandidate(guideCandidates, guide.value, proposed, min, max, threshold, guide.source);
            addSnapCandidate(guideCandidates, guide.value - (size / 2), proposed, min, max, threshold, guide.source);
            addSnapCandidate(guideCandidates, guide.value - size, proposed, min, max, threshold, guide.source);
        });
        const guideMatch = bestSnapCandidate(guideCandidates);
        if (guideMatch !== null) return guideMatch;

        return snapGridValue(proposed, snapSteps()[axis], min, max);
    }
    function snapAxisSize(element, axis, value) {
        const origin = axisStart(element, axis);
        const max = Math.max(0, 1 - origin);
        const min = Math.min(0.02, max);
        const proposed = clamp(value, min, max);
        if (!snapEnabled()) return rounded(proposed, min, max);

        const threshold = snapGuideThreshold(axis);
        const guideCandidates = [];
        snapAlignmentGuides(axis, element).forEach(guide => {
            addSnapCandidate(guideCandidates, guide.value - origin, proposed, min, max, threshold, guide.source);
        });
        const guideMatch = bestSnapCandidate(guideCandidates);
        if (guideMatch !== null) return guideMatch;

        return snapGridValue(proposed, snapSteps()[axis], min, max);
    }
    function snapElementPosition(element, x, y) {
        return {
            x: snapAxisPosition(element, 'x', x),
            y: snapAxisPosition(element, 'y', y),
        };
    }
    function snapElementSize(element, w, h) {
        return {
            w: snapAxisSize(element, 'x', w),
            h: snapAxisSize(element, 'y', h),
        };
    }
    function setElementCoordinate(element, key, value) {
        const parsed = Number(String(value).replace(',', '.')) || 0;
        if (!snapEnabled()) {
            element[key] = ['x', 'y'].includes(key) ? rounded(parsed, 0, key === 'x' ? 1 - element.w : 1 - element.h) : rounded(parsed, 0.02, key === 'w' ? 1 - element.x : 1 - element.y);
            return;
        }
        if (key === 'x' || key === 'y') {
            const next = snapElementPosition(element, key === 'x' ? parsed : element.x, key === 'y' ? parsed : element.y);
            element.x = next.x;
            element.y = next.y;
            return;
        }
        const next = snapElementSize(element, key === 'w' ? parsed : element.w, key === 'h' ? parsed : element.h);
        element.w = next.w;
        element.h = next.h;
    }
    function templateText(key, replacements = {}) {
        let text = i18n[key] || '';
        Object.entries(replacements).forEach(([name, value]) => { text = text.split(`:${name}`).join(String(value)); });
        return text;
    }
    function percentLabel(value) { return `${Math.round(clamp(value, 0, 1) * 1000) / 10}%`; }
    function elementPositionLabel(element) { return `x ${percentLabel(element.x)}, y ${percentLabel(element.y)}, ${i18n.w} ${percentLabel(element.w)}, ${i18n.h} ${percentLabel(element.h)}`; }
    function announce(message) { if (editorStatus) editorStatus.textContent = message; }
    function setShapeDropdownOpen(open) {
        if (!shapeDropdown || !shapeDropdownToggle || !shapeDropdownMenu) return;
        shapeDropdown.classList.toggle('is-open', open);
        shapeDropdownToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        shapeDropdownMenu.hidden = !open;
    }
    function isTextEntryTarget(target) {
        if (!(target instanceof Element)) return false;
        return Boolean(target.closest('input, textarea, select, [contenteditable="true"]'));
    }
    function selectedElement() { return spec().elements.find(element => element.id === selectedId) || null; }
    function escapeHtml(value) { const div = document.createElement('div'); div.textContent = String(value ?? ''); return div.innerHTML; }
    function attr(value) { return escapeHtml(value).replace(/"/g, '&quot;'); }
    function isBackground(element) { return element?.type === 'background'; }
    function allSpecs() { return [specs.landscape, specs.portrait].filter(item => item && Array.isArray(item.fields) && Array.isArray(item.elements)); }
    function allTemplateFields() {
        const fields = new Map();
        [spec()].concat(allSpecs().filter(item => item !== spec())).forEach(currentSpec => {
            currentSpec.fields.forEach(field => {
                if (field?.key && !fields.has(field.key)) fields.set(field.key, field);
            });
        });
        return Array.from(fields.values());
    }
    function findTemplateField(key) { return allTemplateFields().find(field => field.key === key) || null; }
    function fieldOptions(selected) { return [`<option value="">${escapeHtml(i18n.none)}</option>`].concat(allTemplateFields().map(field => `<option value="${attr(field.key)}" ${field.key === selected ? 'selected' : ''}>${escapeHtml(field.label || field.key)}</option>`)).join(''); }
    function mediaOptions() { return [`<option value="0">${escapeHtml(i18n.none)}</option>`].concat(mediaAssets.map(asset => `<option value="${asset.id}">${escapeHtml(asset.name)} (${escapeHtml(asset.kind)})</option>`)).join(''); }
    function textFontElement(element) { return ['text', 'datetime', 'countdown'].includes(element?.type || ''); }
    function normalizeFontFamily(value) {
        const raw = String(value || '').trim();
        return fontOptions.some(option => option.value === raw) ? raw : '';
    }
    function fontCssForToken(value) {
        const normalized = normalizeFontFamily(value);
        if (normalized === '') return '';
        return fontOptions.find(option => option.value === normalized)?.css || '';
    }
    function fontFamilyOptions(selected) {
        const current = normalizeFontFamily(selected);
        return fontOptions.map(option => `<option value="${attr(option.value)}" ${option.value === current ? 'selected' : ''}>${escapeHtml(option.label || option.value || i18n.none)}</option>`).join('');
    }
    function applyTextPreviewStyle(node, element) {
        if (element.style?.fontSize) node.style.fontSize = `clamp(0.7rem, ${Number(element.style.fontSize)}cqw, 4rem)`;
        if (element.style?.fontWeight) node.style.fontWeight = element.style.fontWeight;
        if (element.style?.align) node.style.textAlign = element.style.align;
        const fontFamily = fontCssForToken(element.style?.fontFamily || '');
        if (fontFamily) node.style.fontFamily = fontFamily;
    }
    function cssColor(value) { return String(value || '').trim() || 'transparent'; }
    function mediaAsset(id) { return mediaById.get(Number(id || 0)) || null; }
    function normalizeShapeType(shape) {
        const value = String(shape || '').trim().toLowerCase();
        return shapeTypes.includes(value) ? value : 'box';
    }
    function shapeLabel(shape) { return i18n[`shape_${normalizeShapeType(shape)}`] || normalizeShapeType(shape); }
    function shapeTypeOptions(selected) {
        const current = normalizeShapeType(selected);
        return shapeTypes.map(shape => `<option value="${attr(shape)}" ${shape === current ? 'selected' : ''}>${escapeHtml(shapeLabel(shape))}</option>`).join('');
    }
    function roundedShapeElement(element) {
        return element?.type === 'shape' && ['box', 'square'].includes(normalizeShapeType(element.style?.shape || 'box'));
    }
    function normalizeDateTimeMode(mode) {
        const value = String(mode || '').trim().toLowerCase();
        return dateTimeModes.includes(value) ? value : 'clock';
    }
    function normalizeTimeFormat(format) {
        const value = String(format || '').trim().toLowerCase();
        return timeFormats.includes(value) ? value : '24h';
    }
    function dateTimeModeLabel(mode) {
        return i18n[`datetime_mode_${normalizeDateTimeMode(mode)}`] || normalizeDateTimeMode(mode);
    }
    function dateTimeModeOptions(selected) {
        const current = normalizeDateTimeMode(selected);
        return dateTimeModes.map(mode => `<option value="${attr(mode)}" ${mode === current ? 'selected' : ''}>${escapeHtml(dateTimeModeLabel(mode))}</option>`).join('');
    }
    function timeFormatOptions(selected) {
        const current = normalizeTimeFormat(selected);
        return timeFormats.map(format => `<option value="${attr(format)}" ${format === current ? 'selected' : ''}>${escapeHtml(i18n[`time_format_${format.replace(/-/g, '_')}`] || format)}</option>`).join('');
    }
    function pad2(value) {
        return String(Math.max(0, Number(value) || 0)).padStart(2, '0');
    }
    function dateTimeLocalInputValue(date = new Date()) {
        const local = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
        return local.toISOString().slice(0, 16);
    }
    function dateTimePreviewValue(element) {
        const now = new Date();
        if (normalizeDateTimeMode(element?.style?.dateTimeMode || 'clock') === 'date') {
            return `${pad2(now.getDate())}.${pad2(now.getMonth() + 1)}.${now.getFullYear()}`;
        }

        const minutes = pad2(now.getMinutes());
        if (normalizeTimeFormat(element?.style?.timeFormat || '24h') === 'ampm') {
            const hours24 = now.getHours();
            const hours12 = hours24 % 12 || 12;
            return `${pad2(hours12)}:${minutes} ${hours24 >= 12 ? 'PM' : 'AM'}`;
        }

        return `${pad2(now.getHours())}:${minutes}`;
    }
    function normalizeCountdownTarget(value) {
        const raw = String(value || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/.test(raw)) return '';
        const parsed = new Date(raw);
        return Number.isFinite(parsed.getTime()) ? raw.slice(0, 16) : '';
    }
    function defaultCountdownTarget() {
        return dateTimeLocalInputValue(new Date(Date.now() + 86400000));
    }
    function countdownTargetMs(value) {
        const target = normalizeCountdownTarget(value);
        if (!target) return NaN;
        const parsed = new Date(target).getTime();
        return Number.isFinite(parsed) ? parsed : NaN;
    }
    function formatCountdownSeconds(totalSeconds) {
        let remaining = Math.max(0, Math.floor(Number(totalSeconds) || 0));
        const days = Math.floor(remaining / 86400);
        remaining %= 86400;
        const hours = Math.floor(remaining / 3600);
        remaining %= 3600;
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        return `${pad2(days)}d ${pad2(hours)}h ${pad2(minutes)}m ${pad2(seconds)}s`;
    }
    function countdownPreviewValue(element) {
        const targetMs = countdownTargetMs(element?.style?.countdownTarget || '');
        if (!Number.isFinite(targetMs)) return formatCountdownSeconds(0);
        return formatCountdownSeconds((targetMs - Date.now()) / 1000);
    }
    function truthy(value) {
        if (typeof value === 'boolean') return value;
        if (typeof value === 'number') return value !== 0;
        if (typeof value === 'string') return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase());
        return false;
    }
    function normalizeDropShadowDirection(direction) {
        const value = String(direction || '').trim().toLowerCase();
        return dropShadowDirections.includes(value) ? value : 'bottom-right';
    }
    function dropShadowDirectionOptions(selected) {
        const current = normalizeDropShadowDirection(selected);
        return dropShadowDirections.map(direction => `<option value="${attr(direction)}" ${direction === current ? 'selected' : ''}>${escapeHtml(i18n[`shadow_direction_${direction.replace(/-/g, '_')}`] || direction)}</option>`).join('');
    }
    function dropShadowSettings(element) {
        const style = element?.style || {};
        return {
            enabled: !isBackground(element) && truthy(style.dropShadow),
            offset: clamp(style.dropShadowOffset ?? 2, 0, 40),
            blur: clamp(style.dropShadowBlur ?? 4, 0, 60),
            color: cssColor(style.dropShadowColor || 'rgba(0, 0, 0, 0.35)'),
            direction: normalizeDropShadowDirection(style.dropShadowDirection || 'bottom-right'),
        };
    }
    function dropShadowVector(direction, offset) {
        const diagonal = Number((offset * 0.7071).toFixed(4));
        switch (normalizeDropShadowDirection(direction)) {
            case 'top': return [0, -offset];
            case 'top-right': return [diagonal, -diagonal];
            case 'right': return [offset, 0];
            case 'bottom': return [0, offset];
            case 'bottom-left': return [-diagonal, diagonal];
            case 'left': return [-offset, 0];
            case 'top-left': return [-diagonal, -diagonal];
            default: return [diagonal, diagonal];
        }
    }
    function cqwLength(value) {
        return `${Number(Number(value).toFixed(4))}cqw`;
    }
    function dropShadowCss(element, filter = false) {
        const shadow = dropShadowSettings(element);
        if (!shadow.enabled) return '';
        const [x, y] = dropShadowVector(shadow.direction, shadow.offset);
        const value = `${cqwLength(x)} ${cqwLength(y)} ${cqwLength(shadow.blur)} ${shadow.color}`;
        return filter ? `drop-shadow(${value})` : value;
    }
    function editorBoxShadow(element) {
        return dropShadowCss(element, false) || '0 0 0 rgba(0, 0, 0, 0)';
    }
    function svgNumber(value) { return String(Number(Number(value).toFixed(4))); }
    function svgPoints(points) { return points.map(point => `${svgNumber(point[0])},${svgNumber(point[1])}`).join(' '); }
    function regularPolygonPoints(sides, radius) {
        const start = -Math.PI / 2;
        const points = [];
        for (let index = 0; index < sides; index += 1) {
            const angle = start + ((2 * Math.PI * index) / sides);
            points.push([50 + (radius * Math.cos(angle)), 50 + (radius * Math.sin(angle))]);
        }
        return svgPoints(points);
    }
    function regularStarPoints(outerRadius, innerRadius) {
        const start = -Math.PI / 2;
        const points = [];
        for (let index = 0; index < 10; index += 1) {
            const radius = index % 2 === 0 ? outerRadius : innerRadius;
            const angle = start + ((Math.PI * index) / 5);
            points.push([50 + (radius * Math.cos(angle)), 50 + (radius * Math.sin(angle))]);
        }
        return svgPoints(points);
    }
    function shapeMarkup(shape, inset, radius) {
        const min = inset;
        const max = 100 - inset;
        const size = Math.max(0, max - min);
        const normalized = normalizeShapeType(shape);

        if (normalized === 'circle') {
            const r = Math.max(0, 50 - inset);
            return `<ellipse cx="50" cy="50" rx="${svgNumber(r)}" ry="${svgNumber(r)}"></ellipse>`;
        }
        if (normalized === 'box' || normalized === 'square') {
            const cornerRadius = Math.max(0, Math.min(50, Number(radius) || 0));
            return `<rect x="${svgNumber(min)}" y="${svgNumber(min)}" width="${svgNumber(size)}" height="${svgNumber(size)}" rx="${svgNumber(cornerRadius)}" ry="${svgNumber(cornerRadius)}"></rect>`;
        }
        if (normalized === 'arrow') {
            return `<polygon points="${svgPoints([[min, 28], [56, 28], [56, min], [max, 50], [56, max], [56, 72], [min, 72]])}"></polygon>`;
        }
        if (normalized === 'triangle') return `<polygon points="${svgPoints([[50, min], [max, max], [min, max]])}"></polygon>`;
        if (normalized === 'diamond') return `<polygon points="${svgPoints([[50, min], [max, 50], [50, max], [min, 50]])}"></polygon>`;
        if (normalized === 'star') return `<polygon points="${regularStarPoints(Math.max(0, 50 - inset), Math.max(0, 22 - (inset / 2)))}"></polygon>`;
        return `<polygon points="${regularPolygonPoints(normalized === 'pentagon' ? 5 : 6, Math.max(0, 50 - inset))}"></polygon>`;
    }
    function shapeSvgMarkup(element, className = 'template-editor__shape-svg') {
        const style = element?.style || {};
        const shape = normalizeShapeType(style.shape || 'box');
        const strokeWidth = clamp(style.borderWidth || 0, 0, 40);
        const stroke = strokeWidth > 0 ? cssColor(style.borderColor || 'rgba(0, 0, 0, 0)') : 'none';
        const fill = cssColor(style.backgroundColor || 'rgba(0, 0, 0, 0)');
        const radius = roundedShapeElement(element) ? Number(style.radius || 0) : 0;
        const shadow = dropShadowCss(element, true);
        const styleAttr = shadow ? ` style="filter: ${attr(shadow)}"` : '';

        return `<svg class="${attr(className)} ${attr(`${className}--${shape}`)}" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false"${styleAttr} fill="${attr(fill)}" stroke="${attr(stroke)}" stroke-width="${attr(strokeWidth)}" stroke-linejoin="round" stroke-linecap="round">${shapeMarkup(shape, strokeWidth / 2, radius)}</svg>`;
    }

    function previewFieldValue(element) {
        const field = fieldForElement(element);
        if (!field) return '';
        const value = String(field.default || '').trim();
        return value || field.label || field.key || '';
    }

    function textPreviewValue(element) {
        return previewFieldValue(element) || elementLabel(element);
    }

    function previewPlaceholder(label, modifier = '') {
        const node = document.createElement('span');
        node.className = `template-editor__element-placeholder ${modifier}`.trim();
        node.textContent = label;
        return node;
    }

    function renderMediaPreview(assetId, fit, emptyLabel) {
        const asset = mediaAsset(assetId);
        if (!asset) {
            if (!emptyLabel) return document.createElement('span');
            return previewPlaceholder(emptyLabel);
        }
        if (asset.kind === 'image' && asset.url) {
            if (fit === 'contain-blur') {
                const wrapper = document.createElement('span');
                wrapper.className = 'template-editor__media-preview-stack';
                const blurred = document.createElement('img');
                blurred.className = 'template-editor__media-preview template-editor__media-preview--blurred';
                blurred.src = asset.url;
                blurred.alt = '';
                blurred.setAttribute('aria-hidden', 'true');
                blurred.loading = 'lazy';
                blurred.decoding = 'async';
                blurred.style.objectFit = 'cover';
                const image = document.createElement('img');
                image.className = 'template-editor__media-preview template-editor__media-preview--contained';
                image.src = asset.url;
                image.alt = asset.name || asset.original_name || '';
                image.loading = 'lazy';
                image.decoding = 'async';
                image.style.objectFit = 'contain';
                wrapper.append(blurred, image);
                return wrapper;
            }
            const image = document.createElement('img');
            image.className = 'template-editor__media-preview';
            image.src = asset.url;
            image.alt = asset.name || asset.original_name || '';
            image.loading = 'lazy';
            image.decoding = 'async';
            image.style.objectFit = ['cover', 'contain'].includes(fit) ? fit : 'cover';
            return image;
        }

        if (asset.kind === 'video' && asset.preview_url) {
            const image = document.createElement('img');
            image.className = 'template-editor__media-preview';
            image.src = asset.preview_url;
            image.alt = asset.name || asset.original_name || '';
            image.loading = 'lazy';
            image.decoding = 'async';
            image.style.objectFit = ['cover', 'contain'].includes(fit) ? fit : 'cover';
            return image;
        }

        const placeholder = document.createElement('span');
        placeholder.className = 'template-editor__video-placeholder';
        placeholder.innerHTML = `<span aria-hidden="true"></span><strong>${escapeHtml(i18n.media_video)}</strong><small>${escapeHtml(asset.name || asset.original_name || '')}</small>`;
        return placeholder;
    }

    function renderElementPreview(element) {
        const preview = document.createElement('span');
        preview.className = 'template-editor__element-preview';
        const fit = element.style?.fit || 'cover';

        if (element.type === 'background') {
            preview.appendChild(renderMediaPreview(element.style?.backgroundMediaAssetId || 0, fit, ''));
            return preview;
        }
        if (element.type === 'media') {
            const mediaAssetId = element.style?.mediaAssetId || (element.field ? previewFieldValue(element) : 0);
            preview.appendChild(renderMediaPreview(mediaAssetId, fit, previewFieldValue(element) || elementLabel(element)));
            return preview;
        }
        if (element.type === 'qr') {
            const qr = document.createElement('canvas');
            qr.className = 'template-editor__qr-preview';
            qr.width = 1;
            qr.height = 1;
            qr.dataset.editorQr = '1';
            qr.dataset.qrUrl = previewFieldValue(element) || previewQrUrl;
            qr.dataset.qrForeground = element.style?.color || 'rgba(15, 23, 42, 1)';
            qr.dataset.qrBackground = element.style?.backgroundColor || 'rgba(255, 255, 255, 1)';
            preview.appendChild(qr);
            return preview;
        }
        if (element.type === 'shape') {
            const shape = document.createElement('span');
            shape.className = 'template-editor__shape-preview';
            shape.innerHTML = shapeSvgMarkup(element);
            preview.appendChild(shape);
            return preview;
        }
        if (element.type === 'datetime') {
            const dateTime = document.createElement('span');
            dateTime.className = 'template-editor__datetime-preview';
            dateTime.textContent = dateTimePreviewValue(element);
            applyTextPreviewStyle(dateTime, element);
            preview.appendChild(dateTime);
            return preview;
        }
        if (element.type === 'countdown') {
            const countdown = document.createElement('span');
            countdown.className = 'template-editor__countdown-preview';
            countdown.textContent = countdownPreviewValue(element);
            applyTextPreviewStyle(countdown, element);
            preview.appendChild(countdown);
            return preview;
        }
        if (element.type === 'text') {
            const text = document.createElement('span');
            text.className = 'template-editor__text-preview';
            text.textContent = textPreviewValue(element);
            applyTextPreviewStyle(text, element);
            preview.appendChild(text);
            return preview;
        }

        return preview;
    }

    function renderEditorQrCodes() {
        canvas.querySelectorAll('[data-editor-qr]').forEach(qr => {
            try {
                if (!window.HuginQr?.drawCanvas) throw new Error('QR renderer is unavailable.');
                window.HuginQr.drawCanvas(qr, qr.dataset.qrUrl || previewQrUrl, qr.dataset.qrForeground, qr.dataset.qrBackground);
            } catch (error) {
                qr.replaceWith(previewPlaceholder(previewQrUrl, 'template-editor__element-placeholder--qr'));
            }
        });
    }

    function syncHidden() {
        hiddenLandscape.value = JSON.stringify(normalizedSpecForSave(specs.landscape || defaults.landscape()));
        hiddenPortrait.value = specs.portrait ? JSON.stringify(normalizedSpecForSave(specs.portrait)) : '';
        debugLandscape.value = JSON.stringify(JSON.parse(hiddenLandscape.value || '{}'), null, 2);
        debugPortrait.value = hiddenPortrait.value ? JSON.stringify(JSON.parse(hiddenPortrait.value), null, 2) : '';
    }

    let cleanSnapshot = '';
    let isSubmitting = false;
    let dirtyTrackingReady = false;
    function formSnapshot() {
        syncHidden();
        return JSON.stringify({
            name: form.querySelector('[name="name"]')?.value || '',
            description: form.querySelector('[name="description"]')?.value || '',
            isActive: form.querySelector('[name="is_active"]')?.checked ? 1 : 0,
            landscape: hiddenLandscape.value,
            portrait: hiddenPortrait.value,
        });
    }
    function hasUnsavedChanges() { return dirtyTrackingReady && formSnapshot() !== cleanSnapshot; }
    function markDirty() {
        if (dirtyTrackingReady) editor.dataset.dirty = hasUnsavedChanges() ? 'true' : 'false';
    }
    function resetDirtyState() {
        cleanSnapshot = formSnapshot();
        dirtyTrackingReady = true;
        editor.dataset.dirty = 'false';
    }

    function normalizedSpecForSave(inputSpec) {
        const copy = JSON.parse(JSON.stringify(inputSpec));
        copy.elements = (copy.elements || []).map(element => Object.assign({}, element, {
            x: rounded(element.x),
            y: rounded(element.y),
            w: rounded(element.w, 0.02, 1),
            h: rounded(element.h, 0.02, 1),
        })).map(element => {
            normalizeDateTimeForSave(element);
            normalizeCountdownForSave(element);
            normalizeFontForSave(element);
            if (isBackground(element)) {
                delete element.animation;
                normalizeDropShadowForSave(element);
                return element;
            }
            normalizeDropShadowForSave(element);
            element.animation = normalizedAnimation(element.animation);
            return element;
        });
        return copy;
    }

    function normalizeDateTimeForSave(element) {
        if (element?.type !== 'datetime') return;
        element.field = '';
        element.style = element.style || {};
        element.style.dateTimeMode = normalizeDateTimeMode(element.style.dateTimeMode || 'clock');
        element.style.timeFormat = normalizeTimeFormat(element.style.timeFormat || '24h');
    }

    function normalizeCountdownForSave(element) {
        if (element?.type !== 'countdown') return;
        element.field = '';
        element.style = element.style || {};
        const target = normalizeCountdownTarget(element.style.countdownTarget || '');
        if (target) {
            element.style.countdownTarget = target;
        } else {
            delete element.style.countdownTarget;
        }
    }

    function normalizeFontForSave(element) {
        element.style = element.style || {};
        if (!textFontElement(element)) {
            delete element.style.fontFamily;
            return;
        }

        const fontFamily = normalizeFontFamily(element.style.fontFamily || '');
        if (fontFamily) {
            element.style.fontFamily = fontFamily;
        } else {
            delete element.style.fontFamily;
        }
    }

    function normalizeDropShadowForSave(element) {
        element.style = element.style || {};
        if (isBackground(element) || !truthy(element.style.dropShadow)) {
            delete element.style.dropShadow;
            delete element.style.dropShadowOffset;
            delete element.style.dropShadowBlur;
            delete element.style.dropShadowColor;
            delete element.style.dropShadowDirection;
            return;
        }

        const shadow = dropShadowSettings(element);
        element.style.dropShadow = true;
        element.style.dropShadowOffset = shadow.offset;
        element.style.dropShadowBlur = shadow.blur;
        element.style.dropShadowColor = shadow.color;
        element.style.dropShadowDirection = shadow.direction;
    }

    function normalizedAnimation(animation) {
        const input = animation && typeof animation === 'object' ? animation : {};
        const fallback = defaultAnimation();
        return {
            entrance: entranceAnimations.includes(input.entrance) ? input.entrance : fallback.entrance,
            continuous: continuousAnimations.includes(input.continuous) ? input.continuous : fallback.continuous,
            entranceDelayMs: Math.round(clamp(input.entranceDelayMs ?? fallback.entranceDelayMs, 0, 30000) / 100) * 100,
            entranceDurationMs: Math.round(clamp(input.entranceDurationMs ?? fallback.entranceDurationMs, 100, 10000) / 100) * 100,
            continuousDurationMs: Math.round(clamp(input.continuousDurationMs ?? fallback.continuousDurationMs, 500, 30000) / 250) * 250,
            easing: animationEasings.includes(input.easing) ? input.easing : fallback.easing,
            direction: animationDirections.includes(input.direction) ? input.direction : fallback.direction,
        };
    }

    let pendingCanvasFrameElementId = '';
    let pendingCanvasFrameHandle = 0;
    let suppressCanvasClickElementId = '';

    function canvasElementNode(elementOrId) {
        const id = typeof elementOrId === 'string' ? elementOrId : String(elementOrId?.id || '');
        if (id === '') return null;
        return Array.from(canvas.querySelectorAll('[data-element-id]')).find(node => node.dataset.elementId === id) || null;
    }

    function focusWithoutScroll(node) {
        if (!node || typeof node.focus !== 'function') return;
        try {
            node.focus({ preventScroll: true });
        } catch (error) {
            node.focus();
        }
    }

    function canvasElementAriaLabel(element) {
        return templateText(element.id === selectedId ? 'element_selected_accessible_label' : 'element_accessible_label', {
            label: elementLabel(element),
            type: i18n[`element_${element.type}`] || element.type,
            position: elementPositionLabel(element),
        });
    }

    function applyCanvasElementSelection(node, element) {
        const selected = element.id === selectedId;
        node.classList.toggle('is-selected', selected);
        node.setAttribute('aria-pressed', selected ? 'true' : 'false');
        node.setAttribute('aria-label', canvasElementAriaLabel(element));
    }

    function applyCanvasSelectionState() {
        canvas.querySelectorAll('[data-element-id]').forEach(node => {
            const element = spec().elements.find(item => item.id === node.dataset.elementId);
            if (element) applyCanvasElementSelection(node, element);
        });
    }

    function applyCanvasElementFrame(element) {
        const node = canvasElementNode(element);
        if (!node) return false;
        node.style.left = pct(element.x);
        node.style.top = pct(element.y);
        node.style.width = pct(element.w);
        node.style.height = pct(element.h);
        node.style.zIndex = String(element.z || 0);
        node.style.borderRadius = element.type === 'shape' && !roundedShapeElement(element) ? '0' : `${Number(element.style?.radius || 0)}cqw`;
        node.style.setProperty('--template-editor-element-shadow', element.type === 'shape' ? '0 0 0 rgba(0, 0, 0, 0)' : editorBoxShadow(element));
        applyCanvasElementSelection(node, element);
        return true;
    }

    function scheduleCanvasElementFrame(element) {
        pendingCanvasFrameElementId = String(element?.id || '');
        if (pendingCanvasFrameElementId === '' || pendingCanvasFrameHandle) return;
        pendingCanvasFrameHandle = window.requestAnimationFrame(() => {
            const id = pendingCanvasFrameElementId;
            pendingCanvasFrameElementId = '';
            pendingCanvasFrameHandle = 0;
            const currentElement = spec().elements.find(item => item.id === id);
            if (currentElement) applyCanvasElementFrame(currentElement);
        });
    }

    function flushCanvasElementFrame() {
        if (pendingCanvasFrameHandle) {
            window.cancelAnimationFrame(pendingCanvasFrameHandle);
            pendingCanvasFrameHandle = 0;
        }
        const id = pendingCanvasFrameElementId;
        pendingCanvasFrameElementId = '';
        if (id === '') return;
        const element = spec().elements.find(item => item.id === id);
        if (element) applyCanvasElementFrame(element);
    }

    function finalizeCanvasGeometryChange(element) {
        flushCanvasElementFrame();
        applyCanvasElementFrame(element);
        announce(templateText('element_position_status', { label: elementLabel(element), position: elementPositionLabel(element) }));
        renderInspector();
        syncHidden();
        markDirty();
    }

    function renderCanvas() {
        if (pendingCanvasFrameHandle) {
            window.cancelAnimationFrame(pendingCanvasFrameHandle);
            pendingCanvasFrameHandle = 0;
            pendingCanvasFrameElementId = '';
        }
        const current = spec();
        const ratio = `${current.canvas.width} / ${current.canvas.height}`;
        const ratioValue = current.canvas.width / current.canvas.height;
        const steps = snapSteps();
        canvas.style.aspectRatio = ratio;
        canvas.style.width = ratioValue >= 1 ? '100%' : `min(100%, ${Number((ratioValue * 76).toFixed(3))}vh)`;
        canvas.style.setProperty('--template-grid-x', `${steps.x * 100}%`);
        canvas.style.setProperty('--template-grid-y', `${steps.y * 100}%`);
        canvas.innerHTML = '';
        current.elements.slice().sort((a, b) => (a.z || 0) - (b.z || 0)).forEach(element => {
            const node = document.createElement('button');
            node.type = 'button';
            node.className = `template-editor__element template-editor__element--${element.type}`;
            node.setAttribute('aria-describedby', 'template-editor-canvas-instructions');
            node.style.left = pct(element.x);
            node.style.top = pct(element.y);
            node.style.width = pct(element.w);
            node.style.height = pct(element.h);
            node.style.zIndex = String(element.z || 0);
            node.style.color = element.style?.color || '';
            node.style.background = ['qr', 'shape'].includes(element.type) ? 'transparent' : (element.style?.backgroundColor || (element.type === 'background' ? '#0f172a' : 'rgba(255,255,255,0.18)'));
            node.style.borderRadius = element.type === 'shape' && !roundedShapeElement(element) ? '0' : `${Number(element.style?.radius || 0)}cqw`;
            node.style.setProperty('--template-editor-element-shadow', element.type === 'shape' ? '0 0 0 rgba(0, 0, 0, 0)' : editorBoxShadow(element));
            node.dataset.elementId = element.id;
            applyCanvasElementSelection(node, element);
            node.appendChild(renderElementPreview(element));
            node.addEventListener('pointerdown', startDrag);
            node.addEventListener('click', () => {
                if (suppressCanvasClickElementId === element.id) {
                    suppressCanvasClickElementId = '';
                    return;
                }
                selectElement(element.id, false, true);
            });
            node.addEventListener('keydown', handleCanvasElementKeydown);
            if (!isBackground(element)) {
                const handle = document.createElement('span');
                handle.className = 'template-editor__resize';
                handle.addEventListener('pointerdown', startResize);
                node.appendChild(handle);
            }
            canvas.appendChild(node);
        });
        renderEditorQrCodes();
        focusPendingCanvasElement();
    }

    function renderLiveCanvas() {
        renderCanvas();
        renderLayersPanel();
        syncHidden();
        markDirty();
    }

    function render() {
        renderCanvas();
        renderInspector();
        syncHidden();
        markDirty();
    }

    function elementLabel(element) {
        if (element.type === 'background') return i18n.background;
        if (element.type === 'shape') return shapeLabel(element.style?.shape || 'box');
        if (element.type === 'datetime') return dateTimeModeLabel(element.style?.dateTimeMode || 'clock');
        if (element.type === 'countdown') return i18n.element_countdown || 'Countdown';
        if (element.field) return element.field;
        return i18n[`element_${element.type}`] || element.type;
    }

    function setInspectorTab(tab) {
        activeInspectorTab = tab;
        renderInspector();
    }

    function focusPendingCanvasElement() {
        if (!pendingFocusElementId) return;
        const id = pendingFocusElementId;
        pendingFocusElementId = '';
        window.requestAnimationFrame(() => {
            focusWithoutScroll(Array.from(canvas.querySelectorAll('[data-element-id]')).find(node => node.dataset.elementId === id));
        });
    }

    function selectElement(id, focusElementTab = false, focusCanvasElement = false) {
        const wasSelected = selectedId === id;
        if (wasSelected && !focusElementTab) {
            if (focusCanvasElement) focusWithoutScroll(canvasElementNode(id));
            return;
        }
        selectedId = id;
        if (focusElementTab) activeInspectorTab = 'element';
        const element = spec().elements.find(item => item.id === id);
        if (element) announce(templateText('element_selected_status', { label: elementLabel(element), position: elementPositionLabel(element) }));
        if (focusCanvasElement) {
            if (wasSelected) {
                focusWithoutScroll(canvasElementNode(id));
            } else {
                pendingFocusElementId = id;
            }
        }
        applyCanvasSelectionState();
        renderInspector();
        syncHidden();
        markDirty();
        focusPendingCanvasElement();
    }

    function renderInspector() {
        renderInspectorTabs();
        renderElementInspector(selectedElement());
        renderFieldsPanel();
        renderLayersPanel();
        renderAnimationsPanel();
    }

    function renderInspectorTabs() {
        inspectorTabs.forEach(tab => {
            const active = tab.dataset.inspectorTab === activeInspectorTab;
            const tabName = tab.dataset.inspectorTab;
            const panel = editor.querySelector(`[data-inspector-panel="${tabName}"]`);
            const tabId = `template-editor-tab-${tabName}`;
            const panelId = `template-editor-panel-${tabName}`;
            tab.id = tabId;
            tab.setAttribute('role', 'tab');
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.setAttribute('aria-controls', panelId);
            tab.tabIndex = active ? 0 : -1;
            if (panel) {
                panel.id = panelId;
                panel.setAttribute('role', 'tabpanel');
                panel.setAttribute('aria-labelledby', tabId);
                panel.tabIndex = 0;
            }
            if (active) {
                ensureInspectorTabVisible(tab);
            }
        });
        inspectorPanels.forEach(panel => { panel.hidden = panel.dataset.inspectorPanel !== activeInspectorTab; });
        updateInspectorTabScroll();
    }

    function ensureInspectorTabVisible(tab) {
        if (!inspectorTabsScroll || !tab || tab.parentElement !== inspectorTabsScroll) return;
        const left = tab.offsetLeft;
        const right = left + tab.offsetWidth;
        const viewLeft = inspectorTabsScroll.scrollLeft;
        const viewRight = viewLeft + inspectorTabsScroll.clientWidth;

        if (left < viewLeft) {
            inspectorTabsScroll.scrollLeft = left;
        } else if (right > viewRight) {
            inspectorTabsScroll.scrollLeft = right - inspectorTabsScroll.clientWidth;
        }
    }

    function updateInspectorTabScroll() {
        if (!inspectorTabsScroll) return;
        const overflow = inspectorTabsScroll.scrollWidth > inspectorTabsScroll.clientWidth + 1;
        const maxScroll = Math.max(0, inspectorTabsScroll.scrollWidth - inspectorTabsScroll.clientWidth);
        inspectorTabScrollButtons.forEach(button => {
            button.hidden = !overflow;
            if (button.dataset.tabScroll === 'left') {
                button.disabled = !overflow || inspectorTabsScroll.scrollLeft <= 1;
            } else {
                button.disabled = !overflow || inspectorTabsScroll.scrollLeft >= maxScroll - 1;
            }
        });
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

    function propertyRow(label, control, modifier = '', tooltip = '') {
        const tooltipAttr = tooltip ? ` title="${attr(tooltip)}"` : '';
        return `<label class="template-editor__property-row ${modifier}"><span${tooltipAttr}>${escapeHtml(label)}</span>${control}</label>`;
    }

    function numberInput(dataKind, key, value, attrs = '') {
        return `<input type="number" ${attrs} data-${dataKind}="${attr(key)}" value="${attr(value)}">`;
    }

    function dateTimeLocalInput(dataKind, key, value) {
        return `<input type="datetime-local" data-${dataKind}="${attr(key)}" value="${attr(value)}">`;
    }

    function percentageInputValue(value, min = 0, max = 1) {
        const percent = rounded(value, min, max) * 100;
        return String(Number(percent.toFixed(2)));
    }

    function percentInput(dataKind, key, value, label, min = 0, max = 1) {
        const minValue = percentageInputValue(min);
        const maxValue = percentageInputValue(max);
        return numberInput(dataKind, key, percentageInputValue(value, min, max), `step="0.01" min="${attr(minValue)}" max="${attr(maxValue)}" aria-label="${attr(label)}" data-percent-value`);
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

    function optionLabel(prefix, value) {
        if (value === 'none') return i18n.none;
        return i18n[`${prefix}_${value.replace(/-/g, '_')}`] || value;
    }

    function optionsFor(values, selected, prefix) {
        return values.map(value => `<option value="${attr(value)}" ${value === selected ? 'selected' : ''}>${escapeHtml(optionLabel(prefix, value))}</option>`).join('');
    }

    function layerGroupForElement(element) {
        if (isBackground(element)) return 'background';
        return Number(element?.z || 0) >= 700 ? 'overlay' : 'content';
    }

    function layerGroupLabel(groupKey) {
        return i18n[`layer_group_${groupKey}`] || groupKey;
    }

    function elementsInLayerGroup(groupKey) {
        return spec().elements
            .filter(element => layerGroupForElement(element) === groupKey)
            .sort((a, b) => (Number(b.z || 0) - Number(a.z || 0)) || elementLabel(a).localeCompare(elementLabel(b)));
    }

    function layerPositionLabel(element) {
        const groupKey = layerGroupForElement(element);
        const groupElements = elementsInLayerGroup(groupKey);
        const index = groupElements.findIndex(item => item.id === element.id);
        const position = index >= 0 ? index + 1 : 1;
        return `${layerGroupLabel(groupKey)} · ${i18n.layer_position_hint.replace(':position', String(position)).replace(':z', String(Number(element.z || 0)))}`;
    }

    function renderElementInspector(element) {
        if (!element) {
            elementPanel.innerHTML = `${panelTitle(i18n.inspector_element)}<div class="template-editor__empty-state">${escapeHtml(i18n.no_selection)}</div>`;
            return;
        }

        const mediaOptionHtml = mediaOptions();
        const fitOptions = `<option value="cover">${escapeHtml(i18n.cover)}</option><option value="contain">${escapeHtml(i18n.contain)}</option><option value="contain-blur">${escapeHtml(i18n.contain_blur)}</option>`;
        const mediaFitOptions = `<option value="cover">${escapeHtml(i18n.cover)}</option><option value="contain">${escapeHtml(i18n.contain)}</option>`;
        const position = isBackground(element) ? '' : propertySection(i18n.section_position, `
            <div class="template-editor__quad">
                ${propertyRow(i18n.x, numberInput('prop', 'x', coordinateDisplay(element.x), 'step="0.0001" min="0" max="1"'))}
                ${propertyRow(i18n.y, numberInput('prop', 'y', coordinateDisplay(element.y), 'step="0.0001" min="0" max="1"'))}
                ${propertyRow(i18n.w_short || i18n.w, percentInput('prop', 'w', element.w, i18n.w, 0.02, 1 - element.x), '', i18n.w)}
                ${propertyRow(i18n.h_short || i18n.h, percentInput('prop', 'h', element.h, i18n.h, 0.02, 1 - element.y), '', i18n.h)}
            </div>
            ${propertyRow(i18n.z, `<span class="template-editor__property-value">${escapeHtml(layerPositionLabel(element))}</span>`)}
        `);

        let content = '';
        if (element.type === 'media') {
            content = propertySection(i18n.section_content, propertyRow(i18n.media, selectInput('style', 'mediaAssetId', element.style?.mediaAssetId || 0, mediaOptionHtml)) + propertyRow(i18n.fit, selectInput('style', 'fit', element.style?.fit || 'cover', mediaFitOptions)));
        } else if (element.type === 'background') {
            content = propertySection(i18n.section_content, propertyRow(i18n.background_media, selectInput('style', 'backgroundMediaAssetId', element.style?.backgroundMediaAssetId || 0, mediaOptionHtml)) + propertyRow(i18n.fit, selectInput('style', 'fit', element.style?.fit || 'cover', fitOptions)));
        } else if (element.type === 'datetime') {
            const mode = normalizeDateTimeMode(element.style?.dateTimeMode || 'clock');
            content = propertySection(i18n.section_content,
                propertyRow(i18n.datetime_mode, selectInput('style', 'dateTimeMode', mode, dateTimeModeOptions(mode))) +
                (mode === 'clock' ? propertyRow(i18n.time_format, selectInput('style', 'timeFormat', normalizeTimeFormat(element.style?.timeFormat || '24h'), timeFormatOptions(element.style?.timeFormat || '24h'))) : '')
            );
        } else if (element.type === 'countdown') {
            content = propertySection(i18n.section_content, propertyRow(i18n.countdown_target, dateTimeLocalInput('style', 'countdownTarget', normalizeCountdownTarget(element.style?.countdownTarget || ''))));
        }

        const appearanceRows = [];
        if (element.type === 'shape') appearanceRows.push(propertyRow(i18n.shape_type, selectInput('style', 'shape', normalizeShapeType(element.style?.shape || 'box'), shapeTypeOptions(element.style?.shape || 'box'))));
        if (textFontElement(element)) appearanceRows.push(propertyRow(i18n.font, selectInput('style', 'fontFamily', normalizeFontFamily(element.style?.fontFamily || ''), fontFamilyOptions(element.style?.fontFamily || ''))));
        if (['text', 'qr', 'datetime', 'countdown'].includes(element.type)) appearanceRows.push(propertyRow(i18n.text_color, colorInput('color', element.style?.color || '')));
        if (['text', 'media', 'qr', 'shape', 'background', 'datetime', 'countdown'].includes(element.type)) appearanceRows.push(propertyRow(i18n.background_color, colorInput('backgroundColor', element.style?.backgroundColor || '', true)));
        if (['text', 'datetime', 'countdown'].includes(element.type)) appearanceRows.push(propertyRow(i18n.size, numberInput('style', 'fontSize', element.style?.fontSize || 4, 'step="0.1" min="0.5"')));
        if (element.type === 'shape') appearanceRows.push(propertyRow(i18n.outline_color, colorInput('borderColor', element.style?.borderColor || '', true)));
        if (element.type === 'shape') appearanceRows.push(propertyRow(i18n.outline_width, numberInput('style', 'borderWidth', element.style?.borderWidth || 0, 'step="0.1" min="0" max="40"')));
        if (!isBackground(element) && (element.type !== 'shape' || roundedShapeElement(element))) appearanceRows.push(propertyRow(i18n.radius, numberInput('style', 'radius', element.style?.radius || 0, 'step="0.1" min="0"')));
        if (!isBackground(element)) {
            const shadow = dropShadowSettings(element);
            appearanceRows.push(propertyRow(i18n.drop_shadow, `<span class="checkbox-row"><input type="checkbox" data-style="dropShadow" ${shadow.enabled ? 'checked' : ''}> ${escapeHtml(i18n.drop_shadow_enabled)}</span>`));
            if (shadow.enabled) {
                appearanceRows.push(propertyRow(i18n.drop_shadow_direction, selectInput('style', 'dropShadowDirection', shadow.direction, dropShadowDirectionOptions(shadow.direction))));
                appearanceRows.push(propertyRow(i18n.drop_shadow_offset, numberInput('style', 'dropShadowOffset', shadow.offset, 'step="0.1" min="0" max="40"')));
                appearanceRows.push(propertyRow(i18n.drop_shadow_blur, numberInput('style', 'dropShadowBlur', shadow.blur, 'step="0.1" min="0" max="60"')));
                appearanceRows.push(propertyRow(i18n.drop_shadow_color, colorInput('dropShadowColor', shadow.color, true)));
            }
        }
        const appearance = propertySection(i18n.section_appearance, appearanceRows.join(''));
        const actions = isBackground(element) ? '' : `<div class="template-editor__property-actions">${iconButton('delete-element', i18n.delete_element, 'delete', 'template-editor__icon-button--danger')}</div>`;

        elementPanel.innerHTML = `${panelTitle(elementLabel(element))}${position}${content}${appearance}${actions}`;
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
                if (['x', 'y', 'w', 'h'].includes(input.dataset.prop)) {
                    const value = input.hasAttribute('data-percent-value')
                        ? (Number(String(input.value).replace(',', '.')) || 0) / 100
                        : input.value;
                    setElementCoordinate(element, input.dataset.prop, value);
                    applyCanvasElementFrame(element);
                    syncHidden();
                    markDirty();
                    return;
                } else {
                    element[input.dataset.prop] = Number(input.value);
                }
                renderLiveCanvas();
            };
            input.addEventListener('input', update);
            input.addEventListener('change', update);
        });
        elementPanel.querySelectorAll('[data-style]').forEach(input => {
            const update = () => {
                element.style = element.style || {};
                if (input.dataset.style === 'shape') {
                    element.style.shape = normalizeShapeType(input.value);
                    render();
                    return;
                }
                if (input.dataset.style === 'dateTimeMode') {
                    element.style.dateTimeMode = normalizeDateTimeMode(input.value);
                    element.style.timeFormat = normalizeTimeFormat(element.style.timeFormat || '24h');
                    element.field = '';
                    render();
                    return;
                }
                if (input.dataset.style === 'timeFormat') {
                    element.style.timeFormat = normalizeTimeFormat(input.value);
                    renderLiveCanvas();
                    return;
                }
                if (input.dataset.style === 'countdownTarget') {
                    const target = normalizeCountdownTarget(input.value);
                    if (target) {
                        element.style.countdownTarget = target;
                    } else {
                        delete element.style.countdownTarget;
                    }
                    element.field = '';
                    renderLiveCanvas();
                    return;
                }
                if (input.dataset.style === 'fontFamily') {
                    const fontFamily = normalizeFontFamily(input.value);
                    if (fontFamily) {
                        element.style.fontFamily = fontFamily;
                    } else {
                        delete element.style.fontFamily;
                    }
                    renderLiveCanvas();
                    return;
                }
                if (input.dataset.style === 'dropShadow') {
                    if (input.checked) {
                        const shadow = dropShadowSettings(element);
                        element.style.dropShadow = true;
                        element.style.dropShadowOffset = shadow.offset;
                        element.style.dropShadowBlur = shadow.blur;
                        element.style.dropShadowColor = shadow.color;
                        element.style.dropShadowDirection = shadow.direction;
                    } else {
                        delete element.style.dropShadow;
                        delete element.style.dropShadowOffset;
                        delete element.style.dropShadowBlur;
                        delete element.style.dropShadowColor;
                        delete element.style.dropShadowDirection;
                    }
                    renderLiveCanvas();
                    renderElementInspector(element);
                    return;
                }
                if (input.dataset.style === 'dropShadowDirection') {
                    element.style.dropShadowDirection = normalizeDropShadowDirection(input.value);
                    renderLiveCanvas();
                    return;
                }
                element.style[input.dataset.style] = input.type === 'number' || (input.tagName === 'SELECT' && /AssetId$/.test(input.dataset.style)) ? Number(input.value) : input.value;
                renderLiveCanvas();
            };
            input.addEventListener('input', update);
            input.addEventListener('change', update);
        });
        elementPanel.querySelectorAll('[data-delete-element]').forEach(button => button.addEventListener('click', () => {
            deleteSelectedElement();
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
        if (allTemplateFields().length > 0) {
            fieldsPanel.innerHTML += propertySection(i18n.section_binding, propertyRow(i18n.field, `<select data-bind-existing-field>${fieldOptions(element.field || '')}</select>`));
            fieldsPanel.querySelector('[data-bind-existing-field]').addEventListener('change', event => {
                element.field = event.currentTarget.value;
                render();
            });
        }
        const field = fieldForElement(element);
        if (!field) {
            fieldsPanel.innerHTML += `<div class="template-editor__empty-state">${escapeHtml(i18n.element_has_no_field)}</div><button type="button" class="button button--normal button--small" data-create-bind-field>${icons.add || ''}<span>${escapeHtml(i18n.create_and_bind_field)}</span></button>`;
            fieldsPanel.querySelector('[data-create-bind-field]').addEventListener('click', () => { createAndBindField(element); });
            return;
        }
        const index = Math.max(0, allTemplateFields().indexOf(field));
        const row = document.createElement('div');
        row.className = 'template-editor__field-row';
        row.innerHTML = `
            <div class="template-editor__field-row-head">
                <strong>${escapeHtml(field.label || field.key || i18n.field_1.replace('1', String(index + 1)))}</strong>
                ${iconButton('remove-bound-field', i18n.remove_field_tooltip, 'delete', 'template-editor__icon-button--danger')}
            </div>
            <div class="template-editor__field-grid">
                <label>${escapeHtml(i18n.field_key)}<input type="text" value="${attr(field.key)}" autocomplete="off" autocapitalize="none" spellcheck="false" data-field-key></label>
                <label>${escapeHtml(i18n.field_label)}<input type="text" value="${attr(field.label)}" data-field-label></label>
                <label>${escapeHtml(i18n.field)}<select data-field-type><option value="text">${escapeHtml(i18n.text)}</option><option value="multiline">${escapeHtml(i18n.multiline)}</option><option value="url">${escapeHtml(i18n.url)}</option><option value="media_image">${escapeHtml(i18n.media_image)}</option><option value="media_video">${escapeHtml(i18n.media_video)}</option><option value="qr_url">${escapeHtml(i18n.qr_url)}</option><option value="color">${escapeHtml(i18n.color)}</option></select></label>
                <label class="checkbox-row"><input type="checkbox" data-field-required ${field.required ? 'checked' : ''}> ${escapeHtml(i18n.field_required)}</label>
                ${fieldDefaultControl(field)}
            </div>`;
        row.querySelector('[data-field-type]').value = field.type || defaultFieldTypeForElement(element);
        const updateFieldSummary = () => {
            const summary = row.querySelector('[data-field-summary]');
            if (summary) summary.textContent = field.label || field.key || i18n.field_1.replace('1', String(index + 1));
        };
        const syncFieldMeta = () => {
            const oldKey = field.key;
            const newKey = normalizeKey(row.querySelector('[data-field-key]').value || `field_${index + 1}`);
            field.key = newKey;
            field.label = row.querySelector('[data-field-label]').value || field.key;
            field.type = row.querySelector('[data-field-type]').value;
            field.required = row.querySelector('[data-field-required]').checked;
            field.default = row.querySelector('[data-field-default]')?.value ?? '';
            if (oldKey !== newKey) {
                allSpecs().forEach(currentSpec => {
                    currentSpec.elements.forEach(item => { if (item.field === oldKey) item.field = newKey; });
                    currentSpec.fields.forEach(item => { if (item !== field && item.key === oldKey) item.key = newKey; });
                });
            }
            allSpecs().forEach(currentSpec => {
                currentSpec.fields.forEach(item => {
                    if (item !== field && item.key === newKey) {
                        item.label = field.label;
                        item.type = field.type;
                        item.required = field.required;
                        item.default = field.default;
                    }
                });
            });
            element.field = newKey;
        };
        row.querySelector('.template-editor__field-row-head strong')?.setAttribute('data-field-summary', '');
        row.querySelectorAll('[data-field-key], [data-field-label]').forEach(input => {
            const update = () => {
                syncFieldMeta();
                updateFieldSummary();
                renderLiveCanvas();
            };
            input.addEventListener('input', update);
            input.addEventListener('change', () => {
                syncFieldMeta();
                row.querySelector('[data-field-key]').value = field.key;
                updateFieldSummary();
                renderLiveCanvas();
            });
        });
        row.querySelector('[data-field-required]').addEventListener('change', () => {
            syncFieldMeta();
            renderLiveCanvas();
        });
        row.querySelector('[data-field-type]').addEventListener('change', () => {
            syncFieldMeta();
            renderFieldsPanel();
            renderLiveCanvas();
        });
        row.querySelectorAll('[data-field-default]').forEach(input => {
            const update = () => {
                field.default = input.value;
                allSpecs().forEach(currentSpec => {
                    currentSpec.fields.forEach(item => {
                        if (item !== field && item.key === field.key) item.default = field.default;
                    });
                });
                renderLiveCanvas();
            };
            input.addEventListener('input', update);
            input.addEventListener('change', update);
        });
        row.querySelector('[data-remove-bound-field]').addEventListener('click', () => {
            const key = field.key;
            allSpecs().forEach(currentSpec => {
                currentSpec.fields = currentSpec.fields.filter(item => item !== field && item.key !== key);
            });
            clearFieldReferences(key);
            render();
        });
        const list = document.createElement('div');
        list.className = 'template-editor__field-list';
        list.appendChild(row);
        fieldsPanel.appendChild(list);
    }

    function renderAnimationsPanel() {
        const element = selectedElement();
        animationsPanel.innerHTML = panelTitle(i18n.inspector_animations);
        if (!element) {
            animationsPanel.innerHTML += `<div class="template-editor__empty-state">${escapeHtml(i18n.no_animation_selection)}</div>`;
            return;
        }
        if (isBackground(element)) {
            animationsPanel.innerHTML += `<div class="template-editor__empty-state">${escapeHtml(i18n.background_cannot_animate)}</div>`;
            delete element.animation;
            syncHidden();
            return;
        }

        element.animation = normalizedAnimation(element.animation);
        const animation = element.animation;
        const entrance = propertySection(i18n.section_entrance_animation, `
            ${propertyRow(i18n.animation_entrance, selectInput('animation', 'entrance', animation.entrance, optionsFor(entranceAnimations, animation.entrance, 'animation')))}
            ${propertyRow(i18n.animation_delay, numberInput('animation', 'entranceDelayMs', animation.entranceDelayMs, 'step="100" min="0" max="30000"'))}
            ${propertyRow(i18n.animation_duration, numberInput('animation', 'entranceDurationMs', animation.entranceDurationMs, 'step="100" min="100" max="10000"'))}
            ${propertyRow(i18n.animation_easing, selectInput('animation', 'easing', animation.easing, optionsFor(animationEasings, animation.easing, 'animation')))}
        `);
        const continuous = propertySection(i18n.section_continuous_animation, `
            ${propertyRow(i18n.animation_continuous, selectInput('animation', 'continuous', animation.continuous, optionsFor(continuousAnimations, animation.continuous, 'animation')))}
            ${propertyRow(i18n.animation_duration, numberInput('animation', 'continuousDurationMs', animation.continuousDurationMs, 'step="250" min="500" max="30000"'))}
            ${propertyRow(i18n.animation_direction, selectInput('animation', 'direction', animation.direction, optionsFor(animationDirections, animation.direction, 'animation_direction')))}
        `);

        animationsPanel.innerHTML += entrance + continuous;
        bindAnimationsPanel(element);
    }

    function bindAnimationsPanel(element) {
        animationsPanel.querySelectorAll('[data-animation]').forEach(input => {
            const update = () => {
                if (isBackground(element)) return;
                element.animation = normalizedAnimation(Object.assign({}, element.animation || {}, {
                    [input.dataset.animation]: input.type === 'number' ? Number(input.value) : input.value,
                }));
                renderLiveCanvas();
                renderAnimationsPanel();
            };
            input.addEventListener('change', update);
        });
    }

    function fieldCapableElement(element) {
        return ['text', 'media', 'qr'].includes(element?.type || '');
    }

    function fieldForElement(element) {
        if (!fieldCapableElement(element) || !element.field) return null;
        return findTemplateField(element.field);
    }

    function defaultFieldTypeForElement(element) {
        if (element?.type === 'media') return 'media_image';
        if (element?.type === 'qr') return 'qr_url';
        return 'text';
    }

    function createAndBindField(element) {
        if (!fieldCapableElement(element)) return;
        activeInspectorTab = 'fields';
        const existingKeys = new Set(allTemplateFields().map(field => field.key));
        let fieldNumber = allTemplateFields().length + 1;
        let key = normalizeKey(`field_${fieldNumber}`);
        while (existingKeys.has(key)) {
            fieldNumber += 1;
            key = normalizeKey(`field_${fieldNumber}`);
        }
        const field = { key, label: `${i18n.field_1.replace('1', String(fieldNumber))}`, type: defaultFieldTypeForElement(element), required: false, default: '' };
        spec().fields.push(field);
        element.field = key;
        render();
    }

    function clearFieldReferences(fieldKey) {
        allSpecs().forEach(currentSpec => {
            currentSpec.elements.forEach(element => { if (element.field === fieldKey) element.field = ''; });
        });
    }

    function fieldReferenceCount(fieldKey) {
        if (!fieldKey) return 0;
        return allSpecs().reduce((count, currentSpec) => count + currentSpec.elements.filter(element => element.field === fieldKey).length, 0);
    }

    function deleteFieldDefinition(fieldKey) {
        allSpecs().forEach(currentSpec => {
            currentSpec.fields = currentSpec.fields.filter(field => field.key !== fieldKey);
        });
    }

    function confirmAction(config) {
        if (typeof window.HuginConfirm?.confirm === 'function') {
            return Promise.resolve(window.HuginConfirm.confirm(config));
        }
        return Promise.resolve(false);
    }

    function confirmUnusedFieldDeletion(field, fieldKey) {
        return confirmAction({
            title: i18n.delete || '',
            message: templateText('delete_unused_field_confirm', { field: field.label || field.key || fieldKey }),
            accept: i18n.delete || '',
        });
    }

    function fieldDefaultControl(field) {
        const type = field.type || 'text';
        const value = String(field.default ?? '');
        if (type === 'multiline') {
            return `<label class="template-editor__field-default">${escapeHtml(i18n.field_default)}<textarea rows="3" data-field-default>${escapeHtml(value)}</textarea></label>`;
        }
        if (type === 'media_image' || type === 'media_video') {
            const expected = type === 'media_image' ? 'image' : 'video';
            const options = [`<option value="">${escapeHtml(i18n.none)}</option>`].concat(mediaAssets
                .filter(asset => asset.kind === expected)
                .map(asset => `<option value="${attr(asset.id)}" ${String(asset.id) === value ? 'selected' : ''}>${escapeHtml(asset.name)} (${escapeHtml(asset.kind)})</option>`));
            return `<label class="template-editor__field-default">${escapeHtml(i18n.field_default)}<select data-field-default>${options.join('')}</select></label>`;
        }
        const inputType = type === 'url' || type === 'qr_url' ? 'url' : 'text';
        return `<label class="template-editor__field-default">${escapeHtml(i18n.field_default)}<input type="${inputType}" value="${attr(value)}" data-field-default></label>`;
    }

    function layerElementsByGroup() {
        return Object.fromEntries(layerGroups.map(group => [group.key, elementsInLayerGroup(group.key)]));
    }

    function recalculatedLayerZ(orderByGroup) {
        layerGroups.forEach(group => {
            const orderedIds = orderByGroup[group.key] || [];
            const step = 10;
            orderedIds.forEach((id, index) => {
                const element = spec().elements.find(item => item.id === id);
                if (!element) return;
                const topOffset = (orderedIds.length - index - 1) * step;
                element.z = Math.min(group.base + 99, group.base + topOffset);
            });
        });
    }

    function orderByRenderedLayers() {
        const orderByGroup = {};
        layerGroups.forEach(group => {
            const list = layersPanel.querySelector(`[data-layer-group-list="${group.key}"]`);
            orderByGroup[group.key] = list ? Array.from(list.querySelectorAll('[data-layer-element-id]')).map(row => row.dataset.layerElementId) : [];
        });
        return orderByGroup;
    }

    function moveElementToLayerGroup(elementId, groupKey, beforeId = '') {
        const element = spec().elements.find(item => item.id === elementId);
        const group = layerGroups.find(item => item.key === groupKey);
        if (!element || !group || group.locked || isBackground(element)) return;

        const orderByGroup = layerElementsByGroup();
        Object.keys(orderByGroup).forEach(key => {
            orderByGroup[key] = orderByGroup[key].map(item => item.id).filter(id => id !== elementId);
        });
        const target = orderByGroup[groupKey] || [];
        const beforeIndex = beforeId ? target.indexOf(beforeId) : -1;
        if (beforeIndex >= 0) {
            target.splice(beforeIndex, 0, elementId);
        } else {
            target.push(elementId);
        }
        orderByGroup[groupKey] = target;
        recalculatedLayerZ(orderByGroup);
        selectedId = elementId;
        announce(templateText('layer_moved_status', { label: elementLabel(element), group: layerGroupLabel(groupKey) }));
        render();
    }

    function moveLayerByKeyboard(element, direction) {
        if (!element || isBackground(element)) return false;
        const groupKey = layerGroupForElement(element);
        const currentGroupIndex = layerGroups.findIndex(group => group.key === groupKey);
        const current = elementsInLayerGroup(groupKey);
        const currentIndex = current.findIndex(item => item.id === element.id);
        if (direction === 'up' || direction === 'down') {
            const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;
            if (targetIndex < 0 || targetIndex >= current.length) return false;
            const orderedIds = current.map(item => item.id);
            orderedIds.splice(currentIndex, 1);
            orderedIds.splice(targetIndex, 0, element.id);
            const orderByGroup = layerElementsByGroup();
            orderByGroup[groupKey] = orderedIds;
            recalculatedLayerZ(Object.fromEntries(Object.entries(orderByGroup).map(([key, value]) => [key, value.map(item => typeof item === 'string' ? item : item.id)])));
            selectedId = element.id;
            announce(templateText('layer_moved_status', { label: elementLabel(element), group: layerGroupLabel(groupKey) }));
            pendingFocusElementId = '';
            render();
            return true;
        }
        const targetGroup = layerGroups[currentGroupIndex + (direction === 'right' ? 1 : -1)];
        if (!targetGroup || targetGroup.locked) return false;
        moveElementToLayerGroup(element.id, targetGroup.key);
        return true;
    }

    function nextContentLayerZ() {
        const content = elementsInLayerGroup('content');
        const maxZ = content.reduce((max, element) => Math.max(max, Number(element.z || 0)), 90);
        return Math.min(690, maxZ + 10);
    }

    function elementOverlapRatio(a, b) {
        const left = Math.max(a.x, b.x);
        const top = Math.max(a.y, b.y);
        const right = Math.min(a.x + a.w, b.x + b.w);
        const bottom = Math.min(a.y + a.h, b.y + b.h);
        if (right <= left || bottom <= top) return 0;
        return ((right - left) * (bottom - top)) / Math.max(0.0001, a.w * a.h);
    }

    function placementCandidate(element, x, y) {
        const candidate = Object.assign({}, element, {
            x: rounded(x, 0, 1 - element.w),
            y: rounded(y, 0, 1 - element.h),
        });
        const placed = snapEnabled() ? snapElementPosition(element, candidate.x, candidate.y) : candidate;
        return Object.assign(candidate, placed);
    }

    function placeNewElement(element) {
        const existing = spec().elements.filter(item => !isBackground(item));
        const baseX = 0.2;
        const baseY = 0.2;
        const offset = 0.055;
        const candidates = [];

        for (let index = 0; index < 10; index += 1) {
            candidates.push(placementCandidate(element, baseX + (index * offset), baseY + (index * offset)));
        }
        for (let y = 0.12; y <= 0.72; y += 0.12) {
            for (let x = 0.12; x <= 0.72; x += 0.12) {
                candidates.push(placementCandidate(element, x, y));
            }
        }

        const unique = [];
        const seen = new Set();
        candidates.forEach(candidate => {
            const key = `${candidate.x}:${candidate.y}`;
            if (seen.has(key)) return;
            seen.add(key);
            unique.push(candidate);
        });

        const best = unique
            .map(candidate => ({
                candidate,
                overlap: existing.reduce((max, item) => Math.max(max, elementOverlapRatio(candidate, item)), 0),
            }))
            .sort((a, b) => a.overlap - b.overlap)[0]?.candidate || placementCandidate(element, baseX, baseY);

        element.x = best.x;
        element.y = best.y;
    }

    function applyRenderedLayerOrder() {
        recalculatedLayerZ(orderByRenderedLayers());
        render();
    }

    function layerRow(element, group) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'template-editor__layer';
        button.dataset.layerElementId = element.id;
        if (!group.locked) {
            button.draggable = true;
            button.title = i18n.layer_drag_tooltip;
        } else {
            button.title = i18n.layer_locked;
            button.classList.add('is-locked');
        }
        if (element.id === selectedId) button.classList.add('is-selected');
        button.setAttribute('aria-label', `${i18n.select_layer_tooltip}: ${elementLabel(element)}. ${group.locked ? i18n.layer_locked : i18n.layer_keyboard_hint}`);
        button.innerHTML = `
            <span class="template-editor__layer-icon" aria-hidden="true">${icons.move || ''}</span>
            <span>${escapeHtml(elementLabel(element))}</span>
            <small>${escapeHtml(element.type)} · ${escapeHtml(layerGroupLabel(group.key))} · z ${escapeHtml(element.z || 0)}</small>`;
        button.addEventListener('click', () => { selectElement(element.id); });
        if (!group.locked) {
            button.addEventListener('keydown', event => {
                if (!event.altKey || !['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                event.preventDefault();
                const directions = { ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right' };
                if (moveLayerByKeyboard(element, directions[event.key])) {
                    window.requestAnimationFrame(() => focusWithoutScroll(Array.from(layersPanel.querySelectorAll('[data-layer-element-id]')).find(node => node.dataset.layerElementId === element.id)));
                }
            });
            button.addEventListener('dragstart', event => {
                draggedLayerElementId = element.id;
                selectedId = element.id;
                button.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', element.id);
            });
            button.addEventListener('dragend', () => {
                draggedLayerElementId = '';
                button.classList.remove('is-dragging');
                layersPanel.querySelectorAll('.is-drag-over').forEach(item => item.classList.remove('is-drag-over'));
            });
        }
        return button;
    }

    function bindLayerGroupDrop(list, group) {
        if (group.locked) return;
        list.addEventListener('dragover', event => {
            const draggedId = draggedLayerElementId || event.dataTransfer.getData('text/plain');
            const dragged = spec().elements.find(element => element.id === draggedId);
            if (!dragged || isBackground(dragged)) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            const target = event.target instanceof Element ? event.target.closest('[data-layer-element-id]') : null;
            list.querySelectorAll('.is-drag-over').forEach(item => item.classList.remove('is-drag-over'));
            if (target && target.parentElement === list && target.dataset.layerElementId !== draggedId) {
                target.classList.add('is-drag-over');
            }
        });
        list.addEventListener('dragleave', event => {
            if (!list.contains(event.relatedTarget)) {
                list.querySelectorAll('.is-drag-over').forEach(item => item.classList.remove('is-drag-over'));
            }
        });
        list.addEventListener('drop', event => {
            const draggedId = draggedLayerElementId || event.dataTransfer.getData('text/plain');
            const dragged = spec().elements.find(element => element.id === draggedId);
            if (!dragged || isBackground(dragged)) return;
            event.preventDefault();
            const target = event.target instanceof Element ? event.target.closest('[data-layer-element-id]') : null;
            const draggedRow = Array.from(list.querySelectorAll('[data-layer-element-id]')).find(row => row.dataset.layerElementId === draggedId) || null;
            const beforeId = target && target.parentElement === list && target.dataset.layerElementId !== draggedId ? target.dataset.layerElementId : '';
            if (draggedRow && target && target.parentElement === list && target.dataset.layerElementId !== draggedId) {
                list.insertBefore(draggedRow, target);
                applyRenderedLayerOrder();
                return;
            }
            moveElementToLayerGroup(draggedId, group.key, beforeId);
        });
    }

    function renderLayersPanel() {
        layersPanel.innerHTML = panelTitle(i18n.inspector_layers);
        const groups = layerElementsByGroup();
        layerGroups.slice().reverse().forEach(group => {
            const section = document.createElement('section');
            section.className = 'template-editor__layer-group';
            section.dataset.layerGroup = group.key;
            section.innerHTML = `<h3>${escapeHtml(layerGroupLabel(group.key))}</h3>`;
            const list = document.createElement('div');
            list.className = 'template-editor__layer-list';
            list.dataset.layerGroupList = group.key;
            bindLayerGroupDrop(list, group);
            (groups[group.key] || []).forEach(element => {
                list.appendChild(layerRow(element, group));
            });
            if ((groups[group.key] || []).length === 0) {
                list.classList.add('is-empty');
                const empty = document.createElement('p');
                empty.className = 'template-editor__layer-empty';
                empty.textContent = i18n.layer_empty_group;
                list.appendChild(empty);
            }
            section.appendChild(list);
            layersPanel.appendChild(section);
        });
    }

    function normalizeKey(value) { return String(value || '').toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '') || 'field'; }

    function addElement(type, shapeType = '') {
        const z = type === 'background' ? 0 : nextContentLayerZ();
        const element = { id: uid(type), type, field: '', x: 0.2, y: 0.2, w: 0.35, h: 0.22, z, style: { backgroundColor: type === 'shape' ? 'rgba(255,255,255,0.35)' : 'rgba(15,23,42,0.55)', color: '#ffffff', fontSize: 4, radius: 0, fit: 'cover' } };
        if (type !== 'background') element.animation = defaultAnimation();
        if (type === 'shape') {
            element.w = rounded(4 / snapColumns, 0.02, 1);
            element.h = visualSquareHeightForWidth(element.w);
            element.style.shape = normalizeShapeType(shapeType || 'box');
            element.style.borderColor = 'rgba(15, 23, 42, 0.65)';
            element.style.borderWidth = 2;
        }
        if (type === 'qr') { element.w = 0.18; element.h = 0.32; element.style.backgroundColor = 'rgba(255,255,255,1)'; element.style.color = 'rgba(15,23,42,1)'; }
        if (type === 'media') { element.w = 0.42; element.h = 0.32; }
        if (type === 'datetime') {
            element.w = 0.24;
            element.h = 0.12;
            element.style.dateTimeMode = 'clock';
            element.style.timeFormat = '24h';
            element.style.fontSize = 5;
            element.style.fontWeight = '700';
            element.style.align = 'center';
        }
        if (type === 'countdown') {
            element.w = 0.34;
            element.h = 0.12;
            element.style.countdownTarget = defaultCountdownTarget();
            element.style.fontSize = 4.4;
            element.style.fontWeight = '700';
            element.style.align = 'center';
        }
        placeNewElement(element);
        if (!isBackground(element) && snapEnabled()) {
            const size = snapElementSize(element, element.w, element.h);
            element.w = size.w;
            element.h = size.h;
            placeNewElement(element);
        }
        spec().elements.push(element);
        selectedId = element.id;
        activeInspectorTab = 'element';
        pendingFocusElementId = element.id;
        announce(templateText('element_added_status', { label: elementLabel(element), position: elementPositionLabel(element) }));
        render();
    }

    async function deleteSelectedElement() {
        if (isDeletingElement) return false;
        const element = selectedElement();
        if (!element || isBackground(element)) return false;
        const currentSpec = spec();
        const label = elementLabel(element);
        const fieldKey = fieldCapableElement(element) ? String(element.field || '') : '';
        const field = fieldKey ? findTemplateField(fieldKey) : null;
        let shouldDeleteField = false;
        if (field && fieldReferenceCount(fieldKey) <= 1) {
            isDeletingElement = true;
            try {
                shouldDeleteField = await confirmUnusedFieldDeletion(field, fieldKey);
            } finally {
                isDeletingElement = false;
            }
        }
        if (!currentSpec.elements.some(item => item.id === element.id)) return false;
        currentSpec.elements = currentSpec.elements.filter(item => item.id !== element.id);
        if (shouldDeleteField) {
            deleteFieldDefinition(fieldKey);
        }
        selectedId = null;
        announce(templateText('element_deleted_status', { label }));
        render();
        return true;
    }

    function keyboardMoveStep(axis, large = false) {
        const steps = snapSteps();
        const base = snapEnabled() ? steps[axis] : 0.005;
        return base * (large ? 5 : 1);
    }

    function moveElementWithKeyboard(element, key, largeStep) {
        if (!element || isBackground(element)) {
            if (element) announce(templateText('element_not_movable_status', { label: elementLabel(element) }));
            return;
        }
        const dx = key === 'ArrowLeft' ? -keyboardMoveStep('x', largeStep) : (key === 'ArrowRight' ? keyboardMoveStep('x', largeStep) : 0);
        const dy = key === 'ArrowUp' ? -keyboardMoveStep('y', largeStep) : (key === 'ArrowDown' ? keyboardMoveStep('y', largeStep) : 0);
        const next = snapEnabled()
            ? snapElementPosition(element, element.x + dx, element.y + dy)
            : { x: rounded(element.x + dx, 0, 1 - element.w), y: rounded(element.y + dy, 0, 1 - element.h) };
        element.x = next.x;
        element.y = next.y;
        selectedId = element.id;
        finalizeCanvasGeometryChange(element);
        focusWithoutScroll(canvasElementNode(element));
    }

    function handleCanvasElementKeydown(event) {
        const element = spec().elements.find(item => item.id === event.currentTarget.dataset.elementId);
        if (!element) return;
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            selectElement(element.id, false, true);
            return;
        }
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
            event.preventDefault();
            moveElementWithKeyboard(element, event.key, event.shiftKey);
        }
    }

    function handleEditorShortcuts(event) {
        if (!editor.contains(event.target) || isTextEntryTarget(event.target)) return;
        if (event.key === 'Delete') {
            const focusedElement = event.target instanceof Element ? event.target.closest('[data-element-id], [data-layer-element-id]') : null;
            const focusedId = focusedElement?.dataset.elementId || focusedElement?.dataset.layerElementId || '';
            if (focusedId) selectedId = focusedId;
            const element = selectedElement();
            if (!element || isBackground(element)) return;
            event.preventDefault();
            deleteSelectedElement();
            return;
        }
        if (!event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) return;
        const type = addElementShortcuts[event.code];
        if (!type) return;
        event.preventDefault();
        addElement(type);
    }

    function startDrag(event) {
        if (event.target.classList.contains('template-editor__resize')) return;
        const element = spec().elements.find(item => item.id === event.currentTarget.dataset.elementId);
        if (!element) return;
        suppressCanvasClickElementId = '';
        const wasSelected = selectedId === element.id;
        selectedId = element.id;
        applyCanvasSelectionState();
        if (!wasSelected) {
            renderInspector();
            syncHidden();
            markDirty();
        }
        if (isBackground(element)) return;
        const box = canvas.getBoundingClientRect();
        const start = { x: event.clientX, y: event.clientY, left: element.x, top: element.y };
        let didMove = false;
        event.currentTarget.setPointerCapture(event.pointerId);
        const move = (moveEvent) => {
            const next = snapElementPosition(element, start.left + ((moveEvent.clientX - start.x) / box.width), start.top + ((moveEvent.clientY - start.y) / box.height));
            if (next.x === element.x && next.y === element.y) return;
            element.x = next.x;
            element.y = next.y;
            didMove = true;
            scheduleCanvasElementFrame(element);
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            if (!didMove) return;
            suppressCanvasClickElementId = element.id;
            window.setTimeout(() => { if (suppressCanvasClickElementId === element.id) suppressCanvasClickElementId = ''; }, 120);
            finalizeCanvasGeometryChange(element);
            focusWithoutScroll(canvasElementNode(element));
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    function startResize(event) {
        event.stopPropagation();
        event.preventDefault();
        const element = spec().elements.find(item => item.id === event.currentTarget.parentElement.dataset.elementId);
        if (!element || isBackground(element)) return;
        suppressCanvasClickElementId = '';
        const wasSelected = selectedId === element.id;
        selectedId = element.id;
        applyCanvasSelectionState();
        if (!wasSelected) {
            renderInspector();
            syncHidden();
            markDirty();
        }
        const box = canvas.getBoundingClientRect();
        const start = { x: event.clientX, y: event.clientY, w: element.w, h: element.h };
        let didResize = false;
        const move = (moveEvent) => {
            const next = snapElementSize(element, start.w + ((moveEvent.clientX - start.x) / box.width), start.h + ((moveEvent.clientY - start.y) / box.height));
            if (next.w === element.w && next.h === element.h) return;
            element.w = next.w;
            element.h = next.h;
            didResize = true;
            scheduleCanvasElementFrame(element);
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            if (!didResize) return;
            suppressCanvasClickElementId = element.id;
            window.setTimeout(() => { if (suppressCanvasClickElementId === element.id) suppressCanvasClickElementId = ''; }, 120);
            finalizeCanvasGeometryChange(element);
            focusWithoutScroll(canvasElementNode(element));
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    }

    editor.querySelectorAll('[data-orientation-tab]').forEach(button => button.addEventListener('click', () => {
        editor.querySelectorAll('[data-orientation-tab]').forEach(tab => {
            const active = tab === button;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });
        orientation = button.dataset.orientationTab;
        selectedId = null;
        spec();
        render();
    }));
    editor.querySelectorAll('[data-orientation-tab]').forEach(button => {
        button.addEventListener('keydown', event => {
            const tabs = Array.from(editor.querySelectorAll('[data-orientation-tab]'));
            const currentIndex = tabs.indexOf(button);
            const nextIndex = event.key === 'ArrowRight' ? currentIndex + 1 : (event.key === 'ArrowLeft' ? currentIndex - 1 : -1);
            if (nextIndex < 0) return;
            event.preventDefault();
            const next = tabs[(nextIndex + tabs.length) % tabs.length];
            next.click();
            focusWithoutScroll(next);
        });
    });
    inspectorTabs.forEach(button => {
        button.addEventListener('click', () => { setInspectorTab(button.dataset.inspectorTab); });
        button.addEventListener('keydown', event => {
            const tabs = Array.from(inspectorTabs);
            const currentIndex = tabs.indexOf(button);
            const nextIndex = event.key === 'ArrowRight' ? currentIndex + 1 : (event.key === 'ArrowLeft' ? currentIndex - 1 : -1);
            if (nextIndex < 0) return;
            event.preventDefault();
            const next = tabs[(nextIndex + tabs.length) % tabs.length];
            setInspectorTab(next.dataset.inspectorTab);
            focusWithoutScroll(next);
        });
    });
    inspectorTabScrollButtons.forEach(button => button.addEventListener('click', () => {
        const direction = button.dataset.tabScroll === 'left' ? -1 : 1;
        inspectorTabsScroll?.scrollBy({ left: direction * Math.max(120, inspectorTabsScroll.clientWidth * 0.72), behavior: 'smooth' });
    }));
    inspectorTabsScroll?.addEventListener('scroll', updateInspectorTabScroll, { passive: true });
    window.addEventListener('resize', updateInspectorTabScroll);
    window.addEventListener('beforeunload', event => {
        if (isSubmitting || !hasUnsavedChanges()) return;
        event.preventDefault();
        event.returnValue = '';
    });
    document.addEventListener('click', event => {
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || isSubmitting || !hasUnsavedChanges()) return;
        const href = link.getAttribute('href') || '';
        if (href === '' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:') || link.target || link.hasAttribute('download')) return;
        if (!window.confirm(i18n.unsaved_changes_confirm || 'You have unsaved changes. Leave this page?')) {
            event.preventDefault();
        }
    });
    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);
    document.addEventListener('keydown', handleEditorShortcuts);
    snapToGridToggle?.addEventListener('change', () => { editor.classList.toggle('is-snap-enabled', snapEnabled()); });
    editor.querySelectorAll('[data-add-element]').forEach(button => button.addEventListener('click', () => addElement(button.dataset.addElement, button.dataset.shapeType || '')));
    shapeDropdownToggle?.addEventListener('click', event => {
        event.stopPropagation();
        setShapeDropdownOpen(shapeDropdownMenu?.hidden !== false);
    });
    editor.querySelectorAll('[data-add-shape]').forEach(button => button.addEventListener('click', () => {
        addElement('shape', button.dataset.addShape || 'box');
        setShapeDropdownOpen(false);
    }));
    document.addEventListener('click', event => {
        if (!shapeDropdown || !(event.target instanceof Element) || shapeDropdown.contains(event.target)) return;
        setShapeDropdownOpen(false);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') setShapeDropdownOpen(false);
    });
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
    form.addEventListener('submit', () => {
        isSubmitting = true;
        syncHidden();
    });
    editor.classList.toggle('is-snap-enabled', snapEnabled());
    render();
    resetDirtyState();
})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
