<?php
$formId = 'slide';
$selectedSlideType = (string)old('slide_type', $slide['slide_type'] ?? 'image', $formId);
$returnToPath = (string)old('return_to', $returnTo ?? '/admin/slides', $formId);
$title = $slide ? __('slide.edit_title') : __('slide.create_title');
$textSlideLayouts = text_slide_layout_options();
$textSlideAnimations = text_slide_animation_options();
$textSlideQrPositions = text_slide_qr_position_options();
$textBoxWidthPercentValue = (string)old('text_box_width_percent', $slide['text_box_width_percent'] ?? '76', $formId);
$textBoxRadiusSlideValues = [
    'top_left' => $slide['text_box_radius_top_left_rem'] ?? null,
    'top_right' => $slide['text_box_radius_top_right_rem'] ?? null,
    'bottom_right' => $slide['text_box_radius_bottom_right_rem'] ?? null,
    'bottom_left' => $slide['text_box_radius_bottom_left_rem'] ?? null,
];
$textBoxRadiusMode = (string)old('text_box_radius_mode', text_slide_radius_mode_from_values($textBoxRadiusSlideValues), $formId);
$textBoxRadiusAllValue = (string)old('text_box_radius_all_rem', text_slide_radius_all_value($textBoxRadiusSlideValues), $formId);
$textBoxRadiusValues = [
    'top_left' => (string)old('text_box_radius_top_left_rem', format_text_slide_radius_rem($slide['text_box_radius_top_left_rem'] ?? ''), $formId),
    'top_right' => (string)old('text_box_radius_top_right_rem', format_text_slide_radius_rem($slide['text_box_radius_top_right_rem'] ?? ''), $formId),
    'bottom_right' => (string)old('text_box_radius_bottom_right_rem', format_text_slide_radius_rem($slide['text_box_radius_bottom_right_rem'] ?? ''), $formId),
    'bottom_left' => (string)old('text_box_radius_bottom_left_rem', format_text_slide_radius_rem($slide['text_box_radius_bottom_left_rem'] ?? ''), $formId),
];
$qrSizePercentValue = (string)old('qr_size_percent', $slide['qr_size_percent'] ?? '15', $formId);
$qrRadiusSlideValues = [
    'top_left' => $slide['qr_radius_top_left_rem'] ?? null,
    'top_right' => $slide['qr_radius_top_right_rem'] ?? null,
    'bottom_right' => $slide['qr_radius_bottom_right_rem'] ?? null,
    'bottom_left' => $slide['qr_radius_bottom_left_rem'] ?? null,
];
$qrRadiusMode = (string)old('qr_radius_mode', text_slide_radius_mode_from_values($qrRadiusSlideValues), $formId);
$qrRadiusAllValue = (string)old('qr_radius_all_rem', text_slide_radius_all_value($qrRadiusSlideValues), $formId);
$qrRadiusValues = [
    'top_left' => (string)old('qr_radius_top_left_rem', format_text_slide_radius_rem($slide['qr_radius_top_left_rem'] ?? ''), $formId),
    'top_right' => (string)old('qr_radius_top_right_rem', format_text_slide_radius_rem($slide['qr_radius_top_right_rem'] ?? ''), $formId),
    'bottom_right' => (string)old('qr_radius_bottom_right_rem', format_text_slide_radius_rem($slide['qr_radius_bottom_right_rem'] ?? ''), $formId),
    'bottom_left' => (string)old('qr_radius_bottom_left_rem', format_text_slide_radius_rem($slide['qr_radius_bottom_left_rem'] ?? ''), $formId),
];
$pluginCss = [];
foreach ($pluginDefinitions as $p) {
    $pluginName = (string)($p['name'] ?? '');
    if ($pluginName === '') {
        continue;
    }
    $pluginCss[] = plugin_asset_url($pluginName, 'assets/' . $pluginName . '.css');
}
$pluginCss = array_values(array_unique($pluginCss));
$slideTypeDefinitions = array_values($slideTypeDefinitions ?? []);
$slideTemplates = array_values($slideTemplates ?? []);
$templateFieldDefinitions = $templateFieldDefinitions ?? [];
$templateValues = is_array($templateValues ?? null) ? $templateValues : [];
$selectedTemplateId = (int)old('template_id', $selectedTemplateId ?? 0, $formId);
$slideTypeIconFallbackUrl = url('/assets/img/slides/slide_generic.png');
$slideTypeIconMap = [];
foreach ($slideTypeDefinitions as $definition) {
    $slideType = (string)($definition['slide_type'] ?? '');
    if ($slideType === '') {
        continue;
    }
    $fallbackIconUrl = (string)(($definition['icon_fallback_url'] ?? '') ?: $slideTypeIconFallbackUrl);
    $slideTypeIconMap[$slideType] = [
        'icon_url' => (string)(($definition['icon_url'] ?? '') ?: $fallbackIconUrl),
        'icon_fallback_url' => $fallbackIconUrl,
    ];
}
$selectedSlideTypeIcon = $slideTypeIconMap[$selectedSlideType] ?? [
    'icon_url' => $slideTypeIconFallbackUrl,
    'icon_fallback_url' => $slideTypeIconFallbackUrl,
];
require __DIR__ . '/../layouts/admin_header.php';
?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <form method="post" enctype="multipart/form-data" action="<?= e(($slide && isset($slide['id'])) ? url('/admin/slides/' . $slide['id'] . '/edit') : url('/admin/slides/create')) ?>" class="form-grid" id="slide-form">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= e($returnToPath) ?>">
        <div class="slide-common-fields full-width">
            <label class="slide-common-field"><?= e(__('slide.title', [], __('common.name'))) ?>
                <input type="text" name="name" value="<?= e((string)old('name', $slide['name'] ?? '', $formId)) ?>" placeholder="<?= e(__('slide.name_placeholder')) ?>" required<?= field_attrs('name', $formId) ?>>
                <?= field_error_html('name', $formId) ?>
            </label>
            <label class="slide-common-field"><?= e(__('slide.slide_type')) ?>
                <select name="slide_type" id="slide_type" required<?= field_attrs('slide_type', $formId) ?>>
                    <option value="image" <?= selected($selectedSlideType, 'image') ?>><?= e(enum_label('slide_types', 'image')) ?></option>
                    <option value="video" <?= selected($selectedSlideType, 'video') ?>><?= e(enum_label('slide_types', 'video')) ?></option>
                    <option value="website" <?= selected($selectedSlideType, 'website') ?>><?= e(enum_label('slide_types', 'website')) ?></option>
                    <option value="text" <?= selected($selectedSlideType, 'text') ?>><?= e(enum_label('slide_types', 'text')) ?></option>
                    <option value="template" <?= selected($selectedSlideType, 'template') ?>><?= e(enum_label('slide_types', 'template')) ?></option>
                    <?php foreach ($pluginDefinitions as $plugin): ?>
                        <option value="<?= e($plugin['slide_type']) ?>" <?= selected($selectedSlideType, $plugin['slide_type']) ?>><?= e($plugin['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error_html('slide_type', $formId) ?>
            </label>
            <div class="slide-common-type-icon" aria-hidden="true">
                <img src="<?= e((string)$selectedSlideTypeIcon['icon_url']) ?>" data-slide-type-icon data-fallback-icon="<?= e((string)$selectedSlideTypeIcon['icon_fallback_url']) ?>" alt="">
            </div>
            <label class="slide-common-field"><?= e(__('slide.title_position')) ?>
                <select name="title_position"<?= field_attrs('title_position', $formId) ?>>
                    <?php foreach (['hide','top-left','top-right','bottom-left','bottom-right','center'] as $position): ?>
                        <option value="<?= e($position) ?>" <?= old_selected('title_position', $position, $slide['title_position'] ?? 'hide', $formId) ?>><?= e(enum_label('title_positions', $position, $position)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error_html('title_position', $formId) ?>
            </label>
            <label class="slide-common-field"><?= e(__('slide.duration_optional')) ?>
                <input type="number" min="1" name="duration_seconds" value="<?= e((string)old('duration_seconds', $slide['duration_seconds'] ?? '', $formId)) ?>" placeholder="<?= e(__('slide.duration_placeholder')) ?>"<?= field_attrs('duration_seconds', $formId) ?>>
                <?= field_error_html('duration_seconds', $formId) ?>
            </label>
        </div>
        <fieldset class="channel-assignment-group full-width">
            <legend><?= e(__('slide.assigned_channels')) ?></legend>
            <div class="channel-assignment-grid">
                <?php foreach ($channels as $channel): ?>
                    <?php
                    $channelLabel = (string)$channel['label'];
                    if (isset($channel['is_active']) && (int)$channel['is_active'] !== 1) {
                        $channelLabel .= ' (' . __('common.inactive') . ')';
                    }
                    ?>
                    <label class="checkbox-row channel-assignment-option">
                        <input type="checkbox" name="channel_ids[]" value="<?= e((string)$channel['id']) ?>" <?= in_array((int)$channel['id'], $assignedChannelIds ?? [], true) ? 'checked' : '' ?>>
                        <?= e($channelLabel) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= field_error_html('channel_ids', $formId) ?>
        </fieldset>

        <div id="core-source-fields" class="full-width plugin-settings-card">
            <div class="grid-2 compact-grid">
                <label id="source_mode_wrap"><?= e(__('slide.source_mode')) ?>
                    <select name="source_mode" id="source_mode"<?= field_attrs('source_mode', $formId) ?>>
                        <option value="external" <?= old_selected('source_mode', 'external', $slide['source_mode'] ?? 'external', $formId) ?>><?= e(enum_label('source_modes', 'external')) ?></option>
                        <option value="media" <?= old_selected('source_mode', 'media', $slide['source_mode'] ?? '', $formId) ?>><?= e(enum_label('source_modes', 'media')) ?></option>
                    </select>
                    <?= field_error_html('source_mode', $formId) ?>
                </label>
            </div>
            <label id="source_url_wrap" class="full-width"><?= e(__('slide.source_url')) ?>
                <input type="text" name="source_url" value="<?= e((string)old('source_url', $slide['source_url'] ?? '', $formId)) ?>" placeholder="<?= e(__('slide.source_url_placeholder')) ?>"<?= field_attrs('source_url', $formId) ?>>
                <?= field_error_html('source_url', $formId) ?>
            </label>
            <label id="media_asset_wrap" class="full-width"><?= e(__('slide.uploaded_media')) ?>
                <select name="media_asset_id"<?= field_attrs('media_asset_id', $formId) ?>>
                    <option value=""><?= e(__('slide.choose_uploaded_file')) ?></option>
                    <?php foreach ($mediaAssets as $asset): ?>
                        <option value="<?= e((string)$asset['id']) ?>" data-media-kind="<?= e($asset['media_kind'] ?? '') ?>" <?= old_selected('media_asset_id', $asset['id'], $slide['media_asset_id'] ?? '', $formId) ?>><?= e($asset['name']) ?> · <?= e($asset['original_name']) ?> (<?= e(enum_label('slide_types', $asset['media_kind'], $asset['media_kind'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?= field_error_html('media_asset_id', $formId) ?>
            </label>
            <label id="upload_wrap" class="full-width"><?= e(__('slide.upload_new_media')) ?>
                <input type="file" name="uploaded_file" id="uploaded_file" accept="image/*,video/*"<?= field_attrs('uploaded_file', $formId) ?>>
                <?= field_error_html('uploaded_file', $formId) ?>
                <small class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
            </label>
        </div>

        <div id="text-slide-fields" class="form-grid full-width text-slide-settings" style="display:none;">
            <fieldset class="text-slide-group full-width">
                <legend><?= e(__('slide.text_slide_content')) ?></legend>
                <div class="text-slide-group__grid">
                    <label class="full-width"><?= e(__('slide.text_markup')) ?>
                        <textarea name="text_markup" rows="12" placeholder="<?= e(__('slide.text_markup_placeholder')) ?>"<?= field_attrs('text_markup', $formId) ?>><?= e((string)old('text_markup', $slide['text_markup'] ?? '', $formId)) ?></textarea>
                        <?= field_error_html('text_markup', $formId) ?>
                        <small class="field-note"><?= e(__('slide.text_markup_help')) ?></small>
                    </label>
                </div>
            </fieldset>

            <fieldset class="text-slide-group full-width">
                <legend><?= e(__('slide.text_slide_background')) ?></legend>
                <div class="text-slide-group__grid">
                    <label><?= e(__('slide.background_color')) ?>
                        <span class="admin-color-picker admin-color-picker--compact" data-admin-color-picker data-color-format="hex" data-color-alpha="false" data-default-color="#0f172a" data-default-alpha="1">
                            <input type="color" value="#0f172a" data-color-picker-swatch>
                            <input type="hidden" name="background_color" value="<?= e((string)old('background_color', $slide['background_color'] ?? '#0f172a', $formId)) ?>" data-color-value<?= field_attrs('background_color', $formId) ?>>
                        </span>
                        <?= field_error_html('background_color', $formId) ?>
                    </label>
                    <label><?= e(__('slide.background_media')) ?>
                        <select name="background_media_asset_id" data-background-media-select<?= field_attrs('background_media_asset_id', $formId) ?>>
                            <option value=""><?= e(__('common.none')) ?></option>
                            <?php foreach (($backgroundMediaAssets ?? $imageMediaAssets ?? []) as $asset): ?>
                                <option value="<?= e((string)$asset['id']) ?>" data-media-kind="<?= e((string)($asset['media_kind'] ?? '')) ?>" data-media-url="<?= e(($asset['file_path'] ?? '') !== '' ? url((string)$asset['file_path']) : '') ?>" data-media-name="<?= e($asset['name']) ?>" <?= old_selected('background_media_asset_id', $asset['id'], $slide['background_media_asset_id'] ?? '', $formId) ?>><?= e($asset['name']) ?> · <?= e($asset['original_name']) ?> (<?= e(enum_label('slide_types', (string)$asset['media_kind'], (string)$asset['media_kind'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error_html('background_media_asset_id', $formId) ?>
                    </label>
                    <div class="text-background-preview full-width" data-background-media-preview hidden>
                        <div class="text-background-preview__thumb" data-background-media-preview-thumb>
                            <img src="" alt="" data-background-media-preview-image hidden>
                            <video src="" muted playsinline preload="metadata" data-background-media-preview-video hidden></video>
                            <span data-background-media-preview-name></span>
                        </div>
                    </div>
                    <label class="full-width"><?= e(__('slide.background_upload')) ?>
                        <input type="file" name="background_uploaded_file" accept="image/*,video/*"<?= field_attrs('background_uploaded_file', $formId) ?>>
                        <?= field_error_html('background_uploaded_file', $formId) ?>
                        <small class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
                    </label>
                </div>
            </fieldset>

            <fieldset class="text-slide-group full-width">
                <legend><?= e(__('slide.text_slide_text_box')) ?></legend>
                <div class="text-slide-group__grid text-slide-group__grid--compact">
                    <label><?= e(__('slide.text_color')) ?>
                        <span class="admin-color-picker" data-admin-color-picker data-color-format="rgba" data-color-alpha="true" data-default-color="#f8fafc" data-default-alpha="1">
                            <input type="color" value="#f8fafc" data-color-picker-swatch>
                            <input type="hidden" name="text_color" value="<?= e((string)old('text_color', $slide['text_color'] ?? '', $formId)) ?>" data-color-value<?= field_attrs('text_color', $formId) ?>>
                        </span>
                        <?= field_error_html('text_color', $formId) ?>
                    </label>
                    <label><?= e(__('slide.text_box_background_color')) ?>
                        <span class="admin-color-picker" data-admin-color-picker data-color-format="rgba" data-color-alpha="true" data-default-color="#0f172a" data-default-alpha="0.68">
                            <input type="color" value="#0f172a" data-color-picker-swatch>
                            <input type="hidden" name="text_box_background_color" value="<?= e((string)old('text_box_background_color', $slide['text_box_background_color'] ?? '', $formId)) ?>" data-color-value<?= field_attrs('text_box_background_color', $formId) ?>>
                        </span>
                        <?= field_error_html('text_box_background_color', $formId) ?>
                    </label>
                    <label><?= e(__('slide.text_box_layout')) ?>
                        <select name="text_box_layout"<?= field_attrs('text_box_layout', $formId) ?>>
                            <?php foreach ($textSlideLayouts as $layout): ?>
                                <option value="<?= e($layout) ?>" <?= old_selected('text_box_layout', $layout, $slide['text_box_layout'] ?? 'center', $formId) ?>><?= e(enum_label('text_slide_layouts', $layout, $layout)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error_html('text_box_layout', $formId) ?>
                    </label>
                    <label class="range-field">
                        <span class="range-field__head">
                            <span><?= e(__('slide.text_box_width_percent')) ?></span>
                            <output data-range-output><?= e($textBoxWidthPercentValue) ?>%</output>
                        </span>
                        <input type="range" name="text_box_width_percent" min="25" max="95" step="1" value="<?= e($textBoxWidthPercentValue) ?>" data-range-input data-range-unit="%"<?= field_attrs('text_box_width_percent', $formId) ?>>
                        <?= field_error_html('text_box_width_percent', $formId) ?>
                    </label>
                    <label class="checkbox-row text-slide-checkbox">
                        <input type="checkbox" name="text_box_blur_enabled" value="1" <?= old_checked('text_box_blur_enabled', $slide['text_box_blur_enabled'] ?? 1, $formId) ?>>
                        <span><?= e(__('slide.text_box_blur_enabled')) ?></span>
                    </label>
                    <div class="radius-control full-width" data-radius-control>
                        <label><?= e(__('slide.text_box_radius')) ?>
                            <select name="text_box_radius_mode" data-radius-mode<?= field_attrs('text_box_radius_mode', $formId) ?>>
                                <option value="default" <?= selected($textBoxRadiusMode, 'default') ?>><?= e(enum_label('radius_modes', 'default')) ?></option>
                                <option value="all" <?= selected($textBoxRadiusMode, 'all') ?>><?= e(enum_label('radius_modes', 'all')) ?></option>
                                <option value="custom" <?= selected($textBoxRadiusMode, 'custom') ?>><?= e(enum_label('radius_modes', 'custom')) ?></option>
                            </select>
                        </label>
                        <label data-radius-panel="all"><?= e(__('slide.radius_all_rem')) ?>
                            <input type="number" name="text_box_radius_all_rem" min="0" max="10" step="0.1" value="<?= e($textBoxRadiusAllValue) ?>"<?= field_attrs('text_box_radius_all_rem', $formId) ?>>
                        </label>
                        <div class="radius-control__corners" data-radius-panel="custom">
                            <label><?= e(__('slide.radius_top_left_rem')) ?>
                                <input type="number" name="text_box_radius_top_left_rem" min="0" max="10" step="0.1" value="<?= e($textBoxRadiusValues['top_left']) ?>"<?= field_attrs('text_box_radius_top_left_rem', $formId) ?>>
                            </label>
                            <label><?= e(__('slide.radius_top_right_rem')) ?>
                                <input type="number" name="text_box_radius_top_right_rem" min="0" max="10" step="0.1" value="<?= e($textBoxRadiusValues['top_right']) ?>"<?= field_attrs('text_box_radius_top_right_rem', $formId) ?>>
                            </label>
                            <label><?= e(__('slide.radius_bottom_right_rem')) ?>
                                <input type="number" name="text_box_radius_bottom_right_rem" min="0" max="10" step="0.1" value="<?= e($textBoxRadiusValues['bottom_right']) ?>"<?= field_attrs('text_box_radius_bottom_right_rem', $formId) ?>>
                            </label>
                            <label><?= e(__('slide.radius_bottom_left_rem')) ?>
                                <input type="number" name="text_box_radius_bottom_left_rem" min="0" max="10" step="0.1" value="<?= e($textBoxRadiusValues['bottom_left']) ?>"<?= field_attrs('text_box_radius_bottom_left_rem', $formId) ?>>
                            </label>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="text-slide-group full-width">
                <legend><?= e(__('slide.text_slide_animation')) ?></legend>
                <div class="text-slide-group__grid text-slide-group__grid--compact">
                    <label><?= e(__('slide.text_box_animation')) ?>
                        <select name="text_box_animation"<?= field_attrs('text_box_animation', $formId) ?>>
                            <?php foreach ($textSlideAnimations as $animation): ?>
                                <option value="<?= e($animation) ?>" <?= old_selected('text_box_animation', $animation, $slide['text_box_animation'] ?? 'none', $formId) ?>><?= e(enum_label('text_slide_animations', $animation, $animation)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error_html('text_box_animation', $formId) ?>
                    </label>
                    <label><?= e(__('slide.text_box_animation_duration_ms')) ?>
                        <input type="number" name="text_box_animation_duration_ms" min="300" max="1500" step="50" value="<?= e((string)old('text_box_animation_duration_ms', $slide['text_box_animation_duration_ms'] ?? '600', $formId)) ?>"<?= field_attrs('text_box_animation_duration_ms', $formId) ?>>
                        <?= field_error_html('text_box_animation_duration_ms', $formId) ?>
                    </label>
                    <label><?= e(__('slide.text_box_animation_delay_ms')) ?>
                        <input type="number" name="text_box_animation_delay_ms" min="0" max="5000" step="50" value="<?= e((string)old('text_box_animation_delay_ms', $slide['text_box_animation_delay_ms'] ?? '0', $formId)) ?>"<?= field_attrs('text_box_animation_delay_ms', $formId) ?>>
                        <?= field_error_html('text_box_animation_delay_ms', $formId) ?>
                    </label>
                    <label class="checkbox-row text-slide-checkbox">
                        <input type="checkbox" name="qr_animation_enabled" value="1" <?= old_checked('qr_animation_enabled', $slide['qr_animation_enabled'] ?? 0, $formId) ?>>
                        <span><?= e(__('slide.qr_animation_enabled')) ?></span>
                    </label>
                </div>
            </fieldset>

            <fieldset class="text-slide-group full-width">
                <legend><?= e(__('slide.text_slide_qr')) ?></legend>
                <div class="text-slide-group__grid text-slide-group__grid--compact">
                    <label class="full-width"><?= e(__('slide.qr_url')) ?>
                        <input type="url" name="qr_url" maxlength="270" value="<?= e((string)old('qr_url', $slide['source_url'] ?? '', $formId)) ?>" placeholder="<?= e(__('slide.qr_url_placeholder')) ?>"<?= field_attrs('qr_url', $formId) ?>>
                        <?= field_error_html('qr_url', $formId) ?>
                        <small class="field-note"><?= e(__('slide.qr_url_help')) ?></small>
                    </label>
                    <label class="range-field">
                        <span class="range-field__head">
                            <span><?= e(__('slide.qr_size_percent')) ?></span>
                            <output data-range-output><?= e($qrSizePercentValue) ?>%</output>
                        </span>
                        <input type="range" min="8" max="40" step="1" name="qr_size_percent" value="<?= e($qrSizePercentValue) ?>" data-range-input data-range-unit="%"<?= field_attrs('qr_size_percent', $formId) ?>>
                        <?= field_error_html('qr_size_percent', $formId) ?>
                        <small class="field-note"><?= e(__('slide.qr_size_percent_help')) ?></small>
                    </label>
                    <label><?= e(__('slide.qr_position')) ?>
                        <select name="qr_position"<?= field_attrs('qr_position', $formId) ?>>
                            <?php foreach ($textSlideQrPositions as $position): ?>
                                <option value="<?= e($position) ?>" <?= old_selected('qr_position', $position, $slide['qr_position'] ?? 'bottom-right', $formId) ?>><?= e(enum_label('text_slide_layouts', $position, $position)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error_html('qr_position', $formId) ?>
                    </label>
                    <div class="radius-control full-width" data-radius-control>
                        <label><?= e(__('slide.qr_radius')) ?>
                            <select name="qr_radius_mode" data-radius-mode<?= field_attrs('qr_radius_mode', $formId) ?>>
                                <option value="default" <?= selected($qrRadiusMode, 'default') ?>><?= e(enum_label('radius_modes', 'default')) ?></option>
                                <option value="all" <?= selected($qrRadiusMode, 'all') ?>><?= e(enum_label('radius_modes', 'all')) ?></option>
                                <option value="custom" <?= selected($qrRadiusMode, 'custom') ?>><?= e(enum_label('radius_modes', 'custom')) ?></option>
                            </select>
                        </label>
                        <label data-radius-panel="all"><?= e(__('slide.radius_all_rem')) ?>
                            <input type="number" name="qr_radius_all_rem" min="0" max="10" step="0.1" value="<?= e($qrRadiusAllValue) ?>"<?= field_attrs('qr_radius_all_rem', $formId) ?>>
                        </label>
                        <div class="radius-control__corners" data-radius-panel="custom">
                            <label><?= e(__('slide.radius_top_left_rem')) ?>
                                <input type="number" name="qr_radius_top_left_rem" min="0" max="10" step="0.1" value="<?= e($qrRadiusValues['top_left']) ?>"<?= field_attrs('qr_radius_top_left_rem', $formId) ?>>
                            </label>
                            <label><?= e(__('slide.radius_top_right_rem')) ?>
                                <input type="number" name="qr_radius_top_right_rem" min="0" max="10" step="0.1" value="<?= e($qrRadiusValues['top_right']) ?>"<?= field_attrs('qr_radius_top_right_rem', $formId) ?>>
                            </label>
                            <label><?= e(__('slide.radius_bottom_right_rem')) ?>
                                <input type="number" name="qr_radius_bottom_right_rem" min="0" max="10" step="0.1" value="<?= e($qrRadiusValues['bottom_right']) ?>"<?= field_attrs('qr_radius_bottom_right_rem', $formId) ?>>
                            </label>
                            <label><?= e(__('slide.radius_bottom_left_rem')) ?>
                                <input type="number" name="qr_radius_bottom_left_rem" min="0" max="10" step="0.1" value="<?= e($qrRadiusValues['bottom_left']) ?>"<?= field_attrs('qr_radius_bottom_left_rem', $formId) ?>>
                            </label>
                        </div>
                    </div>
                    <label><?= e(__('slide.qr_foreground_color')) ?>
                        <span class="admin-color-picker" data-admin-color-picker data-color-format="rgba" data-color-alpha="true" data-default-color="#0f172a" data-default-alpha="1">
                            <input type="color" value="#0f172a" data-color-picker-swatch>
                            <input type="hidden" name="qr_foreground_color" value="<?= e((string)old('qr_foreground_color', $slide['qr_foreground_color'] ?? '', $formId)) ?>" data-color-value<?= field_attrs('qr_foreground_color', $formId) ?>>
                        </span>
                        <?= field_error_html('qr_foreground_color', $formId) ?>
                    </label>
                    <label><?= e(__('slide.qr_background_color')) ?>
                        <span class="admin-color-picker" data-admin-color-picker data-color-format="rgba" data-color-alpha="true" data-default-color="#ffffff" data-default-alpha="1">
                            <input type="color" value="#ffffff" data-color-picker-swatch>
                            <input type="hidden" name="qr_background_color" value="<?= e((string)old('qr_background_color', $slide['qr_background_color'] ?? '', $formId)) ?>" data-color-value<?= field_attrs('qr_background_color', $formId) ?>>
                        </span>
                        <?= field_error_html('qr_background_color', $formId) ?>
                    </label>
                </div>
            </fieldset>
        </div>

        <div id="template-slide-fields" class="form-grid full-width template-slide-settings" style="display:none;">
            <fieldset class="text-slide-group full-width">
                <legend><?= e(__('templates.singular')) ?></legend>
                <label class="full-width"><?= e(__('templates.choose_template')) ?>
                    <select name="template_id" data-template-select<?= field_attrs('template_id', $formId) ?>>
                        <option value=""><?= e(__('templates.choose_template')) ?></option>
                        <?php foreach ($slideTemplates as $template): ?>
                            <option value="<?= e((string)$template['id']) ?>" <?= selected($selectedTemplateId, (string)$template['id']) ?>><?= e((string)$template['name']) ?><?= (int)($template['is_active'] ?? 1) !== 1 ? ' (' . e(__('common.inactive')) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error_html('template_id', $formId) ?>
                </label>
                <div class="template-slide-value-fields full-width" data-template-value-fields>
                    <?= field_error_html('template_values', $formId) ?>
                </div>
            </fieldset>
        </div>

        <div class="full-width plugin-settings-wrapper">
            <?php foreach ($pluginDefinitions as $plugin): ?>
                <section class="plugin-settings-section" data-plugin-slide-type="<?= e($plugin['slide_type']) ?>">
                    <?= field_error_html('plugin_settings.' . $plugin['name'], $formId) ?>
                    <?= $pluginForms[$plugin['name']] ?? '' ?>
                </section>
            <?php endforeach; ?>
        </div>

        <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= old_checked('is_active', $slide['is_active'] ?? 1, $formId) ?>> <?= e(__('common.active')) ?></label>
        <div class="form-actions">
            <button type="submit" name="save_action" value="save_and_close" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('slide.save_and_close')) ?></span></button>
            <button type="submit" name="save_action" value="save" class="button button--normal"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
            <?php if ($slide && isset($slide['id'])): ?>
                <a class="button button--normal" target="_blank" rel="noopener noreferrer" href="<?= e(url('/preview-slide/' . $slide['id'])) ?>"><?= admin_icon('preview') ?><span><?= e(__('common.preview')) ?></span></a>
            <?php endif; ?>
            <a class="button button--normal" href="<?= e(url($returnToPath)) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
        </div>
    </form>
</div>
<script>
const huginPluginSlideTypes = <?= json_encode(array_column($pluginDefinitions, 'slide_type'), JSON_UNESCAPED_SLASHES) ?>;
const huginSlideTypeIcons = <?= json_encode($slideTypeIconMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const huginSlideTypeFallbackIcon = <?= json_encode($slideTypeIconFallbackUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const huginTemplateFields = <?= json_encode($templateFieldDefinitions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const huginTemplateValues = <?= json_encode($templateValues, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

function initRangeInput(input) {
    const output = input.closest('.range-field')?.querySelector('[data-range-output]');
    const unit = input.dataset.rangeUnit || '';
    const sync = () => {
        if (output) {
            output.textContent = `${input.value}${unit}`;
        }
    };

    input.addEventListener('input', sync);
    sync();
}

function initBackgroundMediaPreview(select) {
    const preview = document.querySelector('[data-background-media-preview]');
    if (!preview) return;

    const image = preview.querySelector('[data-background-media-preview-image]');
    const video = preview.querySelector('[data-background-media-preview-video]');
    const name = preview.querySelector('[data-background-media-preview-name]');

    const clearMedia = () => {
        if (image) {
            image.hidden = true;
            image.removeAttribute('src');
            image.alt = '';
        }
        if (video) {
            video.hidden = true;
            video.pause();
            video.removeAttribute('src');
            video.load();
        }
    };

    const sync = () => {
        const option = select.selectedOptions[0];
        const url = option?.dataset.mediaUrl || '';
        const kind = option?.dataset.mediaKind || '';
        const label = option?.dataset.mediaName || '';

        clearMedia();
        preview.hidden = !url;
        if (name) {
            name.textContent = label;
        }
        if (!url) return;

        if (kind === 'video' && video) {
            video.src = url;
            video.hidden = false;
            video.load();
            return;
        }

        if (image) {
            image.src = url;
            image.alt = label;
            image.hidden = false;
        }
    };

    select.addEventListener('change', sync);
    sync();
}

function setRadiusPanelState(panel, enabled) {
    panel.hidden = !enabled;
    panel.setAttribute('aria-hidden', enabled ? 'false' : 'true');
    panel.querySelectorAll('input, select, textarea, button').forEach(input => {
        input.disabled = !enabled;
    });
}

function updateRadiusControl(control) {
    const mode = control.querySelector('[data-radius-mode]');
    if (!mode) return;

    const isSectionHidden = control.closest('#text-slide-fields')?.style.display === 'none';
    const selectedMode = mode.value || 'default';
    mode.disabled = isSectionHidden;

    control.querySelectorAll('[data-radius-panel]').forEach(panel => {
        setRadiusPanelState(panel, !isSectionHidden && panel.dataset.radiusPanel === selectedMode);
    });
}

function initRadiusControl(control) {
    const mode = control.querySelector('[data-radius-mode]');
    if (!mode) return;

    mode.addEventListener('change', () => updateRadiusControl(control));
    mode.addEventListener('input', () => updateRadiusControl(control));
    updateRadiusControl(control);
}

function filterMediaByType(slideType) {
    const mediaSelect = document.querySelector('select[name="media_asset_id"]');
    const uploadInput = document.getElementById('uploaded_file');
    if (!mediaSelect) return;

    let allowedKind = '';
    if (slideType === 'image') {
        allowedKind = 'image';
    } else if (slideType === 'video') {
        allowedKind = 'video';
    }

    // Filter media options
    const options = mediaSelect.querySelectorAll('option');
    options.forEach((option, index) => {
        if (index === 0) return; // Keep the default "choose" option
        const mediaKind = option.dataset.mediaKind || '';
        if (allowedKind && mediaKind !== allowedKind) {
            option.style.display = 'none';
        } else {
            option.style.display = '';
        }
    });

    // Update file input accept attribute
    if (uploadInput) {
        if (allowedKind === 'image') {
            uploadInput.accept = 'image/*';
        } else if (allowedKind === 'video') {
            uploadInput.accept = 'video/*';
        } else {
            uploadInput.accept = 'image/*,video/*';
        }
    }
}

function applySlideTypeFallbackIcon(event) {
    const image = event.currentTarget;
    if (image.dataset.fallbackApplied === '1' || !image.dataset.fallbackIcon) return;
    image.dataset.fallbackApplied = '1';
    image.src = image.dataset.fallbackIcon;
}

function updateSlideTypeIcon(slideType) {
    const image = document.querySelector('[data-slide-type-icon]');
    if (!image) return;

    const icon = huginSlideTypeIcons[slideType] || {};
    const fallbackIcon = icon.icon_fallback_url || huginSlideTypeFallbackIcon;
    image.dataset.fallbackIcon = fallbackIcon;
    delete image.dataset.fallbackApplied;
    image.src = icon.icon_url || fallbackIcon;
}


function escapeTemplateHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

function renderTemplateValueFields() {
    const wrapper = document.querySelector('[data-template-value-fields]');
    const select = document.querySelector('[data-template-select]');
    if (!wrapper || !select) return;
    const fields = huginTemplateFields[String(select.value || '')] || [];
    const existing = new Map(Array.from(wrapper.querySelectorAll('[name^="template_values["]')).map(input => [input.dataset.templateValueKey || '', input.value]));
    wrapper.innerHTML = '';
    if (fields.length === 0) {
        const note = document.createElement('p');
        note.className = 'muted';
        note.textContent = select.value ? <?= json_encode(__('templates.no_fields')) ?> : <?= json_encode(__('templates.choose_template')) ?>;
        wrapper.appendChild(note);
        return;
    }
    fields.forEach(field => {
        const key = String(field.key || '');
        const label = document.createElement('label');
        label.className = 'full-width';
        label.textContent = String(field.label || key) + (field.required ? ' *' : '');
        const value = existing.get(key) ?? huginTemplateValues[key] ?? field.default ?? '';
        let control;
        let controlMount = null;
        if (field.type === 'multiline') {
            control = document.createElement('textarea');
            control.rows = 4;
            control.value = value;
        } else if (field.type === 'media_image' || field.type === 'media_video') {
            control = document.createElement('select');
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = <?= json_encode(__('common.none')) ?>;
            control.appendChild(empty);
            const expected = field.type === 'media_image' ? 'image' : 'video';
            document.querySelectorAll('select[name="media_asset_id"] option[data-media-kind]').forEach(option => {
                if ((option.dataset.mediaKind || '') !== expected) return;
                const item = document.createElement('option');
                item.value = option.value;
                item.textContent = option.textContent;
                control.appendChild(item);
            });
            control.value = String(value || '');
        } else if (field.type === 'color') {
            const picker = document.createElement('span');
            picker.className = 'admin-color-picker';
            picker.dataset.adminColorPicker = '';
            picker.dataset.colorFormat = 'rgba';
            picker.dataset.colorAlpha = 'true';
            picker.dataset.defaultColor = '#ffffff';
            picker.dataset.defaultAlpha = '1';

            const swatch = document.createElement('input');
            swatch.type = 'color';
            swatch.value = '#ffffff';
            swatch.dataset.colorPickerSwatch = '';
            picker.appendChild(swatch);

            control = document.createElement('input');
            control.type = 'hidden';
            control.value = value;
            control.dataset.colorValue = '';
            picker.appendChild(control);
            controlMount = picker;
        } else {
            control = document.createElement('input');
            control.type = field.type === 'url' || field.type === 'qr_url' ? 'url' : 'text';
            control.value = value;
            if (field.type === 'url' || field.type === 'qr_url') control.maxLength = 1024;
        }
        control.name = `template_values[${key}]`;
        control.dataset.templateValueKey = key;
        if (field.required) control.required = true;
        label.appendChild(controlMount || control);
        wrapper.appendChild(label);
    });
    window.HuginColorPicker?.init(wrapper);
}

function updateSlideTypeUi() {
    const slideType = document.getElementById('slide_type').value;
    updateSlideTypeIcon(slideType);
    const isPlugin = huginPluginSlideTypes.includes(slideType);
    const coreFields = document.getElementById('core-source-fields');
    const sourceMode = document.getElementById('source_mode');
    const sourceUrlWrap = document.getElementById('source_url_wrap');
    const mediaWrap = document.getElementById('media_asset_wrap');
    const uploadWrap = document.getElementById('upload_wrap');
    const textFields = document.getElementById('text-slide-fields');
    const templateFields = document.getElementById('template-slide-fields');

    coreFields.style.display = isPlugin ? 'none' : 'block';

    const isText = !isPlugin && slideType === 'text';
    const isTemplate = !isPlugin && slideType === 'template';
    const showWebsiteOnly = !isPlugin && slideType === 'website';
    const useMedia = !isPlugin && !showWebsiteOnly && !isText && !isTemplate && sourceMode.value === 'media';

    sourceMode.parentElement.style.display = showWebsiteOnly || isText || isTemplate ? 'none' : 'grid';
    sourceUrlWrap.style.display = showWebsiteOnly ? 'grid' : (!isPlugin && !useMedia && !isText && !isTemplate ? 'grid' : 'none');
    mediaWrap.style.display = !isPlugin && !showWebsiteOnly && !isText && !isTemplate && useMedia ? 'grid' : 'none';
    uploadWrap.style.display = !isPlugin && !showWebsiteOnly && !isText && !isTemplate ? 'grid' : 'none';
    textFields.style.display = isText ? 'grid' : 'none';
    templateFields.style.display = isTemplate ? 'grid' : 'none';
    renderTemplateValueFields();

    filterMediaByType(slideType);

    document.querySelectorAll('.plugin-settings-section').forEach(section => {
        section.style.display = section.dataset.pluginSlideType === slideType ? 'block' : 'none';
    });

    // Disable/enable form controls based on visibility
    const coreInputs = coreFields.querySelectorAll('input, select, textarea');
    coreInputs.forEach(el => el.disabled = coreFields.style.display === 'none');

    sourceMode.disabled = sourceMode.parentElement.style.display === 'none';

    const sourceUrlInput = sourceUrlWrap.querySelector('input');
    if (sourceUrlInput) sourceUrlInput.disabled = sourceUrlWrap.style.display === 'none';

    const mediaSelect = mediaWrap.querySelector('select');
    if (mediaSelect) mediaSelect.disabled = mediaWrap.style.display === 'none';

    const uploadInput = uploadWrap.querySelector('input');
    if (uploadInput) uploadInput.disabled = uploadWrap.style.display === 'none';

    const textInputs = textFields.querySelectorAll('input, select, textarea');
    textInputs.forEach(el => el.disabled = textFields.style.display === 'none');
    const templateInputs = templateFields.querySelectorAll('input, select, textarea');
    templateInputs.forEach(el => el.disabled = templateFields.style.display === 'none');
    document.querySelectorAll('[data-radius-control]').forEach(updateRadiusControl);

    document.querySelectorAll('.plugin-settings-section').forEach(section => {
        const inputs = section.querySelectorAll('input, select, textarea');
        inputs.forEach(el => el.disabled = section.style.display === 'none');
    });
}

document.querySelector('[data-slide-type-icon]')?.addEventListener('error', applySlideTypeFallbackIcon);
document.getElementById('slide_type').addEventListener('change', updateSlideTypeUi);
document.getElementById('source_mode').addEventListener('change', updateSlideTypeUi);
window.HuginColorPicker?.init(document);
document.querySelectorAll('[data-range-input]').forEach(initRangeInput);
document.querySelectorAll('[data-radius-control]').forEach(initRadiusControl);
const backgroundMediaSelect = document.querySelector('[data-background-media-select]');
if (backgroundMediaSelect) initBackgroundMediaPreview(backgroundMediaSelect);
document.querySelector('[data-template-select]')?.addEventListener('change', renderTemplateValueFields);
updateSlideTypeUi();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
