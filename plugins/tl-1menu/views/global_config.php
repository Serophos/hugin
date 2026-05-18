<?php
$backgroundColor = normalize_hex_color((string)($settings['background_color'] ?? '#f1f5f9'), '#f1f5f9');
$selectedAssetId = (string)($settings['background_media_asset_id'] ?? '');
$environmentDisplayStyle = (string)($settings['environment_display_style'] ?? 'symbols');
if (!in_array($environmentDisplayStyle, ['symbols', 'values'], true)) {
    $environmentDisplayStyle = 'symbols';
}
$previewLanguageSetting = (string)current_locale();
$previewLanguage = in_array($previewLanguageSetting, ['de', 'en'], true) ? $previewLanguageSetting : 'de';
$environmentPreviewMetrics = array_map(static function (array $metric): array {
    $metric['setting'] = '';
    return $metric;
}, $plugin->getEnvironmentPreviewMetrics($previewLanguage));
$globalEnvironmentDisplayStyle = $environmentDisplayStyle;
$environmentIconAssets = is_array($environmentIconAssets ?? null) ? $environmentIconAssets : [];
$formId = 'plugin_settings';
?>
<div class="plugin-settings-card tl1menu-global-settings" data-tl1menu-settings>
    <h3><?= e(__('plugins.tl-1menu.global_config.title')) ?></h3>
    <p class="muted"><?= e(__('plugins.tl-1menu.global_config.intro')) ?></p>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl-1menu.global_config.background_title')) ?></legend>
        <div class="tl1menu-admin-grid">
            <label class="tl1menu-color-control"><?= e(__('plugins.tl-1menu.global_config.color_picker')) ?>
                <input type="color" name="plugin_global_settings[<?= e($plugin->getName()) ?>][background_color]" value="<?= e($backgroundColor) ?>"<?= field_attrs('background_color', $formId) ?>>
                <?= field_error_html('background_color', $formId) ?>
            </label>

            <label><?= e(__('plugins.tl-1menu.global_config.media_library_image')) ?>
                <select name="plugin_global_settings[<?= e($plugin->getName()) ?>][background_media_asset_id]"<?= field_attrs('background_media_asset_id', $formId) ?>>
                    <option value=""><?= e(__('common.none')) ?></option>
                    <?php foreach (($imageMediaAssets ?? []) as $asset): ?>
                        <option value="<?= e((string)$asset['id']) ?>" <?= selected($selectedAssetId, $asset['id']) ?>>
                            <?= e($asset['name']) ?> · <?= e($asset['original_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error_html('background_media_asset_id', $formId) ?>
            </label>

            <label><?= e(__('plugins.tl-1menu.global_config.upload_background_image')) ?>
                <input type="file" name="plugin_global_settings[<?= e($plugin->getName()) ?>][background_image_file]" accept="image/*"<?= field_attrs('background_image_file', $formId) ?>>
                <?= field_error_html('background_image_file', $formId) ?>
                <small class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
            </label>

            <?php if (!empty($backgroundImageUrl)): ?>
                <div class="tl1menu-admin-preview">
                    <div class="tl1menu-admin-preview__label"><?= e(__('plugins.tl-1menu.global_config.current_background_image')) ?></div>
                    <img src="<?= e($backgroundImageUrl) ?>" alt="" class="tl1menu-admin-preview__image">
                </div>
            <?php endif; ?>

            <p class="muted full-width"><?= e(__('plugins.tl-1menu.global_config.background_image_help')) ?></p>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl-1menu.global_config.environment_display_style')) ?></legend>
        <div class="tl1menu-admin-grid">
            <label><?= e(__('plugins.tl-1menu.global_config.environment_display_style_help')) ?>
                <select name="plugin_global_settings[<?= e($plugin->getName()) ?>][environment_display_style]" data-tl1menu-env-preview-control<?= field_attrs('environment_display_style', $formId) ?>>
                    <option value="symbols" <?= selected($environmentDisplayStyle, 'symbols') ?>><?= e(__('plugins.tl-1menu.config.environment_display_styles.symbols')) ?></option>
                    <option value="values" <?= selected($environmentDisplayStyle, 'values') ?>><?= e(__('plugins.tl-1menu.config.environment_display_styles.values')) ?></option>
                </select>
                <?= field_error_html('environment_display_style', $formId) ?>
            </label>

            <?php
            $environmentPreviewModes = ['card', 'list'];
            $environmentPreviewLayout = 'all';
            $environmentPreviewShowModeLabels = true;
            require __DIR__ . '/partials/environment_preview.php';
            ?>
        </div>
    </fieldset>
</div>
<?php require __DIR__ . '/partials/admin_settings_script.php'; ?>
