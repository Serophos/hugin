<?php
$formId = 'slide';
$selectedSlideType = (string)old('slide_type', $slide['slide_type'] ?? 'image', $formId);
$title = $slide ? __('slide.edit_title') : __('slide.create_title');
require __DIR__ . '/../layouts/admin_header.php';
?>
<h1><?= e($title) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <form method="post" enctype="multipart/form-data" action="<?= e($slide ? url('/admin/slides/' . $slide['id'] . '/edit') : url('/admin/slides/create')) ?>" class="form-grid" id="slide-form">
        <?= csrf_field() ?>
        <label class="full-width"><?= e(__('slide.assigned_channels')) ?>
            <select name="channel_ids[]" multiple size="8" required<?= field_attrs('channel_ids', $formId) ?>>
                <?php foreach ($channels as $channel): ?>
                    <option value="<?= e((string)$channel['id']) ?>" <?= in_array((int)$channel['id'], $assignedChannelIds ?? [], true) ? 'selected' : '' ?>><?= e($channel['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error_html('channel_ids', $formId) ?>
        </label>
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
                    <option value="<?= e($position) ?>" <?= old_selected('title_position', $position, $slide['title_position'] ?? 'bottom-left', $formId) ?>><?= e(enum_label('title_positions', $position, $position)) ?></option>
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
                <input type="url" name="source_url" value="<?= e((string)old('source_url', $slide['source_url'] ?? '', $formId)) ?>" placeholder="<?= e(__('slide.source_url_placeholder')) ?>"<?= field_attrs('source_url', $formId) ?>>
                <?= field_error_html('source_url', $formId) ?>
            </label>
            <label id="media_asset_wrap" class="full-width"><?= e(__('slide.uploaded_media')) ?>
                <select name="media_asset_id"<?= field_attrs('media_asset_id', $formId) ?>>
                    <option value=""><?= e(__('slide.choose_uploaded_file')) ?></option>
                    <?php foreach ($mediaAssets as $asset): ?>
                        <option value="<?= e((string)$asset['id']) ?>" <?= old_selected('media_asset_id', $asset['id'], $slide['media_asset_id'] ?? '', $formId) ?>><?= e($asset['name']) ?> · <?= e($asset['original_name']) ?> (<?= e(enum_label('slide_types', $asset['media_kind'], $asset['media_kind'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?= field_error_html('media_asset_id', $formId) ?>
            </label>
            <label id="upload_wrap" class="full-width"><?= e(__('slide.upload_new_media')) ?>
                <input type="file" name="uploaded_file" accept="image/*,video/*"<?= field_attrs('uploaded_file', $formId) ?>>
                <?= field_error_html('uploaded_file', $formId) ?>
                <small class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
            </label>
        </div>

        <div id="text-slide-fields" class="form-grid full-width" style="display:none;">
            <label class="full-width"><?= e(__('slide.text_markup')) ?>
                <textarea name="text_markup" rows="12" placeholder="<?= e(__('slide.text_markup_placeholder')) ?>"<?= field_attrs('text_markup', $formId) ?>><?= e((string)old('text_markup', $slide['text_markup'] ?? '', $formId)) ?></textarea>
                <?= field_error_html('text_markup', $formId) ?>
            </label>
            <label><?= e(__('slide.background_color')) ?>
                <input type="color" name="background_color" value="<?= e(normalize_hex_color((string)old('background_color', $slide['background_color'] ?? '#0f172a', $formId), '#0f172a')) ?>"<?= field_attrs('background_color', $formId) ?>>
                <?= field_error_html('background_color', $formId) ?>
            </label>
            <label><?= e(__('slide.background_image')) ?>
                <select name="background_media_asset_id"<?= field_attrs('background_media_asset_id', $formId) ?>>
                    <option value=""><?= e(__('common.none')) ?></option>
                    <?php foreach (($imageMediaAssets ?? []) as $asset): ?>
                        <option value="<?= e((string)$asset['id']) ?>" <?= old_selected('background_media_asset_id', $asset['id'], $slide['background_media_asset_id'] ?? '', $formId) ?>><?= e($asset['name']) ?> · <?= e($asset['original_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error_html('background_media_asset_id', $formId) ?>
            </label>
            <label class="full-width"><?= e(__('slide.background_upload')) ?>
                <input type="file" name="background_uploaded_file" accept="image/*"<?= field_attrs('background_uploaded_file', $formId) ?>>
                <?= field_error_html('background_uploaded_file', $formId) ?>
                <small class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
            </label>
            <p class="field-note full-width"><?= e(__('slide.text_markup_help')) ?></p>
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
        <div class="form-actions"><button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button><a class="button button--normal" href="<?= e(url('/admin/slides')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a></div>
    </form>
</div>
<script>
const huginPluginSlideTypes = <?= json_encode(array_column($pluginDefinitions, 'slide_type'), JSON_UNESCAPED_SLASHES) ?>;

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

    document.querySelectorAll('.plugin-settings-section').forEach(section => {
        section.style.display = section.dataset.pluginSlideType === slideType ? 'block' : 'none';
    });
}

document.getElementById('slide_type').addEventListener('change', updateSlideTypeUi);
document.getElementById('source_mode').addEventListener('change', updateSlideTypeUi);
updateSlideTypeUi();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
