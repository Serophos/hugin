<?php
$formId = 'slide';
$selectedSlideType = (string)old('slide_type', $slide['slide_type'] ?? 'image', $formId);
$returnToPath = (string)old('return_to', $returnTo ?? '/admin/slides', $formId);
$title = $slide ? __('slide.edit_title') : __('slide.create_title');
$textSlideLayouts = text_slide_layout_options();
$textSlideAnimations = text_slide_animation_options();
$textSlideQrPositions = text_slide_qr_position_options();
$textBoxWidthPercentValue = (string)old('text_box_width_percent', $slide['text_box_width_percent'] ?? '76', $formId);
$qrSizePercentValue = (string)old('qr_size_percent', $slide['qr_size_percent'] ?? '15', $formId);
$pluginCss = null;
if (!in_array($selectedSlideType, ['image', 'video', 'website', 'text'])) {
    foreach ($pluginDefinitions as $p) {
        if ($p['slide_type'] === $selectedSlideType) {
            $pluginCss = url('/plugin-assets/' . rawurlencode((string)$p['name']) . '/assets/' . rawurlencode((string)$p['name']) . '.css');
            break;
        }
    }
}
require __DIR__ . '/../layouts/admin_header.php';
?>
<h1><?= e($title) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <form method="post" enctype="multipart/form-data" action="<?= e(($slide && isset($slide['id'])) ? url('/admin/slides/' . $slide['id'] . '/edit') : url('/admin/slides/create')) ?>" class="form-grid" id="slide-form">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= e($returnToPath) ?>">
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
        <label><?= e(__('common.name')) ?>
            <input type="text" name="name" value="<?= e((string)old('name', $slide['name'] ?? '', $formId)) ?>" placeholder="<?= e(__('slide.name_placeholder')) ?>" required<?= field_attrs('name', $formId) ?>>
            <?= field_error_html('name', $formId) ?>
        </label>
        <label><?= e(__('slide.slide_type')) ?>
            <select name="slide_type" id="slide_type" required<?= field_attrs('slide_type', $formId) ?>>
                <option value="image" <?= selected($selectedSlideType, 'image') ?>><?= e(enum_label('slide_types', 'image')) ?></option>
                <option value="video" <?= selected($selectedSlideType, 'video') ?>><?= e(enum_label('slide_types', 'video')) ?></option>
                <option value="website" <?= selected($selectedSlideType, 'website') ?>><?= e(enum_label('slide_types', 'website')) ?></option>
                <option value="text" <?= selected($selectedSlideType, 'text') ?>><?= e(enum_label('slide_types', 'text')) ?></option>
                <?php foreach ($pluginDefinitions as $plugin): ?>
                    <option value="<?= e($plugin['slide_type']) ?>" <?= selected($selectedSlideType, $plugin['slide_type']) ?>><?= e($plugin['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error_html('slide_type', $formId) ?>
        </label>
        <label><?= e(__('slide.title_position')) ?>
            <select name="title_position"<?= field_attrs('title_position', $formId) ?>>
                <?php foreach (['hide','top-left','top-right','bottom-left','bottom-right','center'] as $position): ?>
                    <option value="<?= e($position) ?>" <?= old_selected('title_position', $position, $slide['title_position'] ?? 'hide', $formId) ?>><?= e(enum_label('title_positions', $position, $position)) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error_html('title_position', $formId) ?>
        </label>
        <label><?= e(__('slide.duration_optional')) ?>
            <input type="number" min="1" name="duration_seconds" value="<?= e((string)old('duration_seconds', $slide['duration_seconds'] ?? '', $formId)) ?>" placeholder="<?= e(__('slide.duration_placeholder')) ?>"<?= field_attrs('duration_seconds', $formId) ?>>
            <?= field_error_html('duration_seconds', $formId) ?>
        </label>

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
                        <input type="color" name="background_color" value="<?= e((string)old('background_color', $slide['background_color'] ?? '#0f172a', $formId)) ?>"<?= field_attrs('background_color', $formId) ?>>
                        <?= field_error_html('background_color', $formId) ?>
                    </label>
                    <label><?= e(__('slide.background_media')) ?>
                        <select name="background_media_asset_id"<?= field_attrs('background_media_asset_id', $formId) ?>>
                            <option value=""><?= e(__('common.none')) ?></option>
                            <?php foreach (($backgroundMediaAssets ?? $imageMediaAssets ?? []) as $asset): ?>
                                <option value="<?= e((string)$asset['id']) ?>" <?= old_selected('background_media_asset_id', $asset['id'], $slide['background_media_asset_id'] ?? '', $formId) ?>><?= e($asset['name']) ?> · <?= e($asset['original_name']) ?> (<?= e(enum_label('slide_types', (string)$asset['media_kind'], (string)$asset['media_kind'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error_html('background_media_asset_id', $formId) ?>
                    </label>
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
                        <span class="rgba-control" data-rgba-control data-default-color="#f8fafc" data-default-alpha="1">
                            <input type="color" value="#f8fafc" data-rgba-color>
                            <span class="rgba-control__alpha">
                                <span class="rgba-control__alpha-head"><span><?= e(__('slide.color_opacity')) ?></span><output data-rgba-output>100%</output></span>
                                <input type="range" min="0" max="1" step="0.05" value="1" data-rgba-alpha>
                            </span>
                            <input type="hidden" name="text_color" value="<?= e((string)old('text_color', $slide['text_color'] ?? '', $formId)) ?>" data-rgba-value<?= field_attrs('text_color', $formId) ?>>
                        </span>
                        <?= field_error_html('text_color', $formId) ?>
                    </label>
                    <label><?= e(__('slide.text_box_background_color')) ?>
                        <span class="rgba-control" data-rgba-control data-default-color="#0f172a" data-default-alpha="0.68">
                            <input type="color" value="#0f172a" data-rgba-color>
                            <span class="rgba-control__alpha">
                                <span class="rgba-control__alpha-head"><span><?= e(__('slide.color_opacity')) ?></span><output data-rgba-output>68%</output></span>
                                <input type="range" min="0" max="1" step="0.05" value="0.68" data-rgba-alpha>
                            </span>
                            <input type="hidden" name="text_box_background_color" value="<?= e((string)old('text_box_background_color', $slide['text_box_background_color'] ?? '', $formId)) ?>" data-rgba-value<?= field_attrs('text_box_background_color', $formId) ?>>
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
                        <input type="number" name="text_box_animation_duration_ms" min="300" max="800" step="50" value="<?= e((string)old('text_box_animation_duration_ms', $slide['text_box_animation_duration_ms'] ?? '600', $formId)) ?>"<?= field_attrs('text_box_animation_duration_ms', $formId) ?>>
                        <?= field_error_html('text_box_animation_duration_ms', $formId) ?>
                    </label>
                    <label><?= e(__('slide.text_box_animation_delay_ms')) ?>
                        <input type="number" name="text_box_animation_delay_ms" min="0" max="5000" step="50" value="<?= e((string)old('text_box_animation_delay_ms', $slide['text_box_animation_delay_ms'] ?? '0', $formId)) ?>"<?= field_attrs('text_box_animation_delay_ms', $formId) ?>>
                        <?= field_error_html('text_box_animation_delay_ms', $formId) ?>
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
                    <label><?= e(__('slide.qr_foreground_color')) ?>
                        <span class="rgba-control" data-rgba-control data-default-color="#0f172a" data-default-alpha="1">
                            <input type="color" value="#0f172a" data-rgba-color>
                            <span class="rgba-control__alpha">
                                <span class="rgba-control__alpha-head"><span><?= e(__('slide.color_opacity')) ?></span><output data-rgba-output>100%</output></span>
                                <input type="range" min="0" max="1" step="0.05" value="1" data-rgba-alpha>
                            </span>
                            <input type="hidden" name="qr_foreground_color" value="<?= e((string)old('qr_foreground_color', $slide['qr_foreground_color'] ?? '', $formId)) ?>" data-rgba-value<?= field_attrs('qr_foreground_color', $formId) ?>>
                        </span>
                        <?= field_error_html('qr_foreground_color', $formId) ?>
                    </label>
                    <label><?= e(__('slide.qr_background_color')) ?>
                        <span class="rgba-control" data-rgba-control data-default-color="#ffffff" data-default-alpha="1">
                            <input type="color" value="#ffffff" data-rgba-color>
                            <span class="rgba-control__alpha">
                                <span class="rgba-control__alpha-head"><span><?= e(__('slide.color_opacity')) ?></span><output data-rgba-output>100%</output></span>
                                <input type="range" min="0" max="1" step="0.05" value="1" data-rgba-alpha>
                            </span>
                            <input type="hidden" name="qr_background_color" value="<?= e((string)old('qr_background_color', $slide['qr_background_color'] ?? '', $formId)) ?>" data-rgba-value<?= field_attrs('qr_background_color', $formId) ?>>
                        </span>
                        <?= field_error_html('qr_background_color', $formId) ?>
                    </label>
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

function clampNumber(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function hexToRgb(value) {
    const hex = String(value || '').replace('#', '').trim();
    if (!/^[0-9a-f]{6}$/i.test(hex)) {
        return { red: 0, green: 0, blue: 0 };
    }

    return {
        red: parseInt(hex.slice(0, 2), 16),
        green: parseInt(hex.slice(2, 4), 16),
        blue: parseInt(hex.slice(4, 6), 16),
    };
}

function rgbToHex(red, green, blue) {
    return '#' + [red, green, blue].map(value => clampNumber(value, 0, 255).toString(16).padStart(2, '0')).join('');
}

function parseRgbaColor(value, defaultColor, defaultAlpha) {
    const fallback = {
        color: /^#[0-9a-f]{6}$/i.test(defaultColor) ? defaultColor : '#000000',
        alpha: clampNumber(parseFloat(defaultAlpha || '1'), 0, 1),
    };
    const input = String(value || '').trim();
    if (input === '') return fallback;

    const hexMatch = input.match(/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i);
    if (hexMatch) {
        let hex = hexMatch[1].toLowerCase();
        if (hex.length === 3 || hex.length === 4) {
            hex = hex.split('').map(char => char + char).join('');
        }
        const alpha = hex.length === 8 ? parseInt(hex.slice(6, 8), 16) / 255 : fallback.alpha;
        return { color: '#' + hex.slice(0, 6), alpha: clampNumber(alpha, 0, 1) };
    }

    const rgbaMatch = input.match(/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*([0-9]*\.?[0-9]+)\s*)?\)$/i);
    if (rgbaMatch) {
        const red = parseInt(rgbaMatch[1], 10);
        const green = parseInt(rgbaMatch[2], 10);
        const blue = parseInt(rgbaMatch[3], 10);
        const alpha = rgbaMatch[4] === undefined ? fallback.alpha : parseFloat(rgbaMatch[4]);
        if (red <= 255 && green <= 255 && blue <= 255 && alpha >= 0 && alpha <= 1) {
            return { color: rgbToHex(red, green, blue), alpha };
        }
    }

    return fallback;
}

function formatRgbaAlpha(value) {
    const alpha = clampNumber(parseFloat(value || '1'), 0, 1);
    return alpha.toFixed(2).replace(/0+$/, '').replace(/\.$/, '') || '0';
}

function initRgbaControl(control) {
    const colorInput = control.querySelector('[data-rgba-color]');
    const alphaInput = control.querySelector('[data-rgba-alpha]');
    const hiddenInput = control.querySelector('[data-rgba-value]');
    const output = control.querySelector('[data-rgba-output]');
    if (!colorInput || !alphaInput || !hiddenInput) return;

    const parsed = parseRgbaColor(hiddenInput.value, control.dataset.defaultColor, control.dataset.defaultAlpha);
    colorInput.value = parsed.color;
    alphaInput.value = String(parsed.alpha);

    const sync = () => {
        const rgb = hexToRgb(colorInput.value);
        const alpha = formatRgbaAlpha(alphaInput.value);
        hiddenInput.value = `rgba(${rgb.red}, ${rgb.green}, ${rgb.blue}, ${alpha})`;
        if (output) {
            output.textContent = `${Math.round(parseFloat(alpha) * 100)}%`;
        }
    };

    colorInput.addEventListener('input', sync);
    alphaInput.addEventListener('input', sync);
    sync();
}

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

function updateSlideTypeUi() {
    const slideType = document.getElementById('slide_type').value;
    const isPlugin = huginPluginSlideTypes.includes(slideType);
    const coreFields = document.getElementById('core-source-fields');
    const sourceMode = document.getElementById('source_mode');
    const sourceUrlWrap = document.getElementById('source_url_wrap');
    const mediaWrap = document.getElementById('media_asset_wrap');
    const uploadWrap = document.getElementById('upload_wrap');
    const textFields = document.getElementById('text-slide-fields');

    coreFields.style.display = isPlugin ? 'none' : 'block';

    const isText = !isPlugin && slideType === 'text';
    const showWebsiteOnly = !isPlugin && slideType === 'website';
    const useMedia = !isPlugin && !showWebsiteOnly && !isText && sourceMode.value === 'media';

    sourceMode.parentElement.style.display = showWebsiteOnly || isText ? 'none' : 'grid';
    sourceUrlWrap.style.display = showWebsiteOnly ? 'grid' : (!isPlugin && !useMedia && !isText ? 'grid' : 'none');
    mediaWrap.style.display = !isPlugin && !showWebsiteOnly && !isText && useMedia ? 'grid' : 'none';
    uploadWrap.style.display = !isPlugin && !showWebsiteOnly && !isText ? 'grid' : 'none';
    textFields.style.display = isText ? 'grid' : 'none';

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

    document.querySelectorAll('.plugin-settings-section').forEach(section => {
        const inputs = section.querySelectorAll('input, select, textarea');
        inputs.forEach(el => el.disabled = section.style.display === 'none');
    });
}

document.getElementById('slide_type').addEventListener('change', updateSlideTypeUi);
document.getElementById('source_mode').addEventListener('change', updateSlideTypeUi);
document.querySelectorAll('[data-rgba-control]').forEach(initRgbaControl);
document.querySelectorAll('[data-range-input]').forEach(initRangeInput);
updateSlideTypeUi();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
