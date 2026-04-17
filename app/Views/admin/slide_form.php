<?php $title = $slide ? __('slide.edit_title') : __('slide.create_title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<h1><?= e($title) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <form method="post" enctype="multipart/form-data" action="<?= e($slide ? url('/admin/slides/' . $slide['id'] . '/edit') : url('/admin/slides/create')) ?>" class="form-grid" id="slide-form">
        <?= csrf_field() ?>
        <label class="full-width"><?= e(__('slide.assigned_channels')) ?>
            <select name="channel_ids[]" multiple size="8" required>
                <?php foreach ($channels as $channel): ?>
                    <option value="<?= e((string)$channel['id']) ?>" <?= in_array((int)$channel['id'], $assignedChannelIds ?? [], true) ? 'selected' : '' ?>><?= e($channel['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(__('common.name')) ?><input type="text" name="name" value="<?= e($slide['name'] ?? '') ?>" required></label>
        <label><?= e(__('slide.slide_type')) ?>
            <select name="slide_type" id="slide_type" required>
                <option value="image" <?= selected($slide['slide_type'] ?? 'image', 'image') ?>><?= e(enum_label('slide_types', 'image')) ?></option>
                <option value="video" <?= selected($slide['slide_type'] ?? '', 'video') ?>><?= e(enum_label('slide_types', 'video')) ?></option>
                <option value="website" <?= selected($slide['slide_type'] ?? '', 'website') ?>><?= e(enum_label('slide_types', 'website')) ?></option>
                <?php foreach ($pluginDefinitions as $plugin): ?>
                    <option value="<?= e($plugin['slide_type']) ?>" <?= selected($slide['slide_type'] ?? '', $plugin['slide_type']) ?>><?= e($plugin['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(__('slide.title_position')) ?>
            <select name="title_position">
                <?php foreach (['hide','top-left','top-right','bottom-left','bottom-right','center'] as $position): ?>
                    <option value="<?= e($position) ?>" <?= selected($slide['title_position'] ?? 'bottom-left', $position) ?>><?= e(enum_label('title_positions', $position, $position)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(__('slide.duration_optional')) ?>
            <input type="number" min="1" name="duration_seconds" value="<?= e((string)($slide['duration_seconds'] ?? '')) ?>">
        </label>

        <div id="core-source-fields" class="full-width plugin-settings-card">
            <div class="grid-2 compact-grid">
                <label id="source_mode_wrap"><?= e(__('slide.source_mode')) ?>
                    <select name="source_mode" id="source_mode">
                        <option value="external" <?= selected($slide['source_mode'] ?? 'external', 'external') ?>><?= e(enum_label('source_modes', 'external')) ?></option>
                        <option value="media" <?= selected($slide['source_mode'] ?? '', 'media') ?>><?= e(enum_label('source_modes', 'media')) ?></option>
                    </select>
                </label>
            </div>
            <label id="source_url_wrap" class="full-width"><?= e(__('slide.source_url')) ?><input type="url" name="source_url" value="<?= e($slide['source_url'] ?? '') ?>"></label>
            <label id="media_asset_wrap" class="full-width"><?= e(__('slide.uploaded_media')) ?>
                <select name="media_asset_id">
                    <option value=""><?= e(__('slide.choose_uploaded_file')) ?></option>
                    <?php foreach ($mediaAssets as $asset): ?>
                        <option value="<?= e((string)$asset['id']) ?>" <?= selected($slide['media_asset_id'] ?? '', $asset['id']) ?>><?= e($asset['name']) ?> · <?= e($asset['original_name']) ?> (<?= e(enum_label('slide_types', $asset['media_kind'], $asset['media_kind'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label id="upload_wrap" class="full-width"><?= e(__('slide.upload_new_media')) ?>
                <input type="file" name="uploaded_file" accept="image/*,video/*">
            </label>
        </div>

        <div class="full-width plugin-settings-wrapper">
            <?php foreach ($pluginDefinitions as $plugin): ?>
                <section class="plugin-settings-section" data-plugin-slide-type="<?= e($plugin['slide_type']) ?>">
                    <?= $pluginForms[$plugin['name']] ?? '' ?>
                </section>
            <?php endforeach; ?>
        </div>

        <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= checked($slide['is_active'] ?? 1) ?>> <?= e(__('common.active')) ?></label>
        <div class="form-actions"><button type="submit"><?= e(__('common.save')) ?></button><a class="button secondary" href="<?= e(url('/admin/slides')) ?>"><?= e(__('common.cancel')) ?></a></div>
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

    coreFields.style.display = isPlugin ? 'none' : 'block';

    const showWebsiteOnly = !isPlugin && slideType === 'website';
    const useMedia = !isPlugin && !showWebsiteOnly && sourceMode.value === 'media';

    sourceMode.parentElement.style.display = showWebsiteOnly ? 'none' : 'grid';
    sourceUrlWrap.style.display = showWebsiteOnly || (!isPlugin && !useMedia) ? 'grid' : 'none';
    mediaWrap.style.display = !isPlugin && !showWebsiteOnly && useMedia ? 'grid' : 'none';
    uploadWrap.style.display = !isPlugin && !showWebsiteOnly ? 'grid' : 'none';

    document.querySelectorAll('.plugin-settings-section').forEach(section => {
        section.style.display = section.dataset.pluginSlideType === slideType ? 'block' : 'none';
    });
}

document.getElementById('slide_type').addEventListener('change', updateSlideTypeUi);
document.getElementById('source_mode').addEventListener('change', updateSlideTypeUi);
updateSlideTypeUi();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
