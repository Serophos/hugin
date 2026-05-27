<?php
$formId = 'slide';
$fieldPrefix = 'plugin_settings.' . $plugin->getName() . '.';
$backgroundColorMode = (string)($settings['background_color_mode'] ?? 'global');
$backgroundImageMode = (string)($settings['background_image_mode'] ?? 'global');
$slideBackgroundColor = normalize_hex_color((string)($settings['background_color'] ?? ''), normalize_hex_color((string)($globalSettings['background_color'] ?? '#f1f5f9'), '#f1f5f9'));
$selectedAssetId = (string)($settings['background_media_asset_id'] ?? '');
$displayMode = (string)($settings['display_mode'] ?? 'card');
if (!in_array($displayMode, ['card', 'list'], true)) {
    $displayMode = 'card';
}
$displayModePreviewUrls = is_array($displayModePreviews ?? null) ? $displayModePreviews : [];
$environmentIconAssets = is_array($environmentIconAssets ?? null) ? $environmentIconAssets : [];
$globalEnvironmentDisplayStyle = (string)($globalSettings['environment_display_style'] ?? 'symbols');
if (!in_array($globalEnvironmentDisplayStyle, ['symbols', 'values'], true)) {
    $globalEnvironmentDisplayStyle = 'symbols';
}
$previewLanguageSetting = (string)($settings['language'] ?? 'de');
$previewLanguage = in_array($previewLanguageSetting, ['de', 'en'], true) ? $previewLanguageSetting : 'de';
$environmentPreviewMetrics = $plugin->getEnvironmentPreviewMetrics($previewLanguage);
$selectedTypes = array_map('intval', is_array($settings['exclude_types'] ?? null) ? $settings['exclude_types'] : []);
$priceGroups = is_array($priceGroups ?? null) ? $priceGroups : [];
$displayPriceGroups = is_array($settings['display_price_groups'] ?? null) ? $settings['display_price_groups'] : [];
?>
<div class="plugin-settings-card tl1menu-slide-settings" data-tl1menu-settings data-tl1menu-slide-settings>
    <h3><?= e(__('plugins.tl-1menu.config.title')) ?></h3>
    <p class="muted"><?= e(__('plugins.tl-1menu.config.intro')) ?></p>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl-1menu.config.title')) ?></legend>
        <div class="tl1menu-admin-grid">
            <label><?= e(__('plugins.tl-1menu.config.mensa')) ?>
                <select name="plugin_settings[<?= e($plugin->getName()) ?>][mensa]"<?= field_attrs($fieldPrefix . 'mensa', $formId) ?>>
                    <?php foreach ($mensen as $mensaKey): ?>
                        <option value="<?= e($mensaKey) ?>" <?= selected($settings['mensa'], $mensaKey) ?>>
                            <?= e($plugin->getMenuService($globalSettings)->getMensaLabel($mensaKey)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error_html($fieldPrefix . 'mensa', $formId) ?>
            </label>

            <label><?= e(__('plugins.tl-1menu.config.language')) ?>
                <select name="plugin_settings[<?= e($plugin->getName()) ?>][language]"<?= field_attrs($fieldPrefix . 'language', $formId) ?>>
                    <option value="de" <?= selected($settings['language'], 'de') ?>><?= e(__('plugins.tl-1menu.languages.de')) ?></option>
                    <option value="en" <?= selected($settings['language'], 'en') ?>><?= e(__('plugins.tl-1menu.languages.en')) ?></option>
                </select>
                <?= field_error_html($fieldPrefix . 'language', $formId) ?>
            </label>

            <label class="checkbox-row tl1menu-admin-inline-check">
                <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_header]" value="1" <?= !empty($settings['show_header']) ? 'checked' : '' ?>>
                <span><?= e(__('plugins.tl-1menu.config.show_header')) ?></span>
            </label>

            <div class="tl1menu-display-mode-field full-width">
                <div class="tl1menu-field-label"><?= e(__('plugins.tl-1menu.config.display_mode')) ?></div>
                <div class="tl1menu-display-mode-options" role="radiogroup" aria-label="<?= e(__('plugins.tl-1menu.config.display_mode')) ?>"<?= field_error($fieldPrefix . 'display_mode', $formId) ? ' aria-describedby="' . e(field_error_id($fieldPrefix . 'display_mode', $formId)) . '"' : '' ?>>
                    <?php foreach (['card', 'list'] as $mode): ?>
                        <?php $previewUrl = (string)($displayModePreviewUrls[$mode] ?? ''); ?>
                        <label class="tl1menu-display-mode-option">
                            <input class="tl1menu-display-mode-option__input" type="radio" name="plugin_settings[<?= e($plugin->getName()) ?>][display_mode]" data-tl1menu-env-preview-control value="<?= e($mode) ?>" <?= checked($displayMode === $mode) ?><?= field_error($fieldPrefix . 'display_mode', $formId) ? ' aria-invalid="true"' : '' ?>>
                            <span class="tl1menu-display-mode-option__body">
                                <?php if ($previewUrl !== ''): ?>
                                    <img class="tl1menu-display-mode-option__preview" src="<?= e($previewUrl) ?>" alt="">
                                <?php endif; ?>
                                <span class="tl1menu-display-mode-option__title"><?= e(__('plugins.tl-1menu.config.display_modes.' . $mode)) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= field_error_html($fieldPrefix . 'display_mode', $formId) ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl-1menu.config.background_title')) ?></legend>
        <div class="tl1menu-admin-grid">
            <label><?= e(__('plugins.tl-1menu.config.background_color_mode')) ?>
                <select name="plugin_settings[<?= e($plugin->getName()) ?>][background_color_mode]" data-tl1menu-toggle="background-color"<?= field_attrs($fieldPrefix . 'background_color_mode', $formId) ?>>
                    <option value="global" <?= selected($backgroundColorMode, 'global') ?>><?= e(__('plugins.tl-1menu.config.background_color_modes.global')) ?></option>
                    <option value="custom" <?= selected($backgroundColorMode, 'custom') ?>><?= e(__('plugins.tl-1menu.config.background_color_modes.custom')) ?></option>
                </select>
                <?= field_error_html($fieldPrefix . 'background_color_mode', $formId) ?>
            </label>

            <label class="tl1menu-color-control" data-tl1menu-toggle-target="background-color" data-tl1menu-show-when="custom" data-tl1menu-preserve-slot="true"><?= e(__('plugins.tl-1menu.config.background_color')) ?>
                <input type="color" name="plugin_settings[<?= e($plugin->getName()) ?>][background_color]" value="<?= e($slideBackgroundColor) ?>"<?= field_attrs($fieldPrefix . 'background_color', $formId) ?>>
                <?= field_error_html($fieldPrefix . 'background_color', $formId) ?>
            </label>

            <label><?= e(__('plugins.tl-1menu.config.background_image_mode')) ?>
                <select name="plugin_settings[<?= e($plugin->getName()) ?>][background_image_mode]" data-tl1menu-toggle="background-image"<?= field_attrs($fieldPrefix . 'background_image_mode', $formId) ?>>
                    <option value="global" <?= selected($backgroundImageMode, 'global') ?>><?= e(__('plugins.tl-1menu.config.background_image_modes.global')) ?></option>
                    <option value="none" <?= selected($backgroundImageMode, 'none') ?>><?= e(__('plugins.tl-1menu.config.background_image_modes.none')) ?></option>
                    <option value="custom" <?= selected($backgroundImageMode, 'custom') ?>><?= e(__('plugins.tl-1menu.config.background_image_modes.custom')) ?></option>
                </select>
                <?= field_error_html($fieldPrefix . 'background_image_mode', $formId) ?>
            </label>

            <label data-tl1menu-toggle-target="background-image" data-tl1menu-show-when="custom"><?= e(__('plugins.tl-1menu.config.background_media_asset')) ?>
                <select name="plugin_settings[<?= e($plugin->getName()) ?>][background_media_asset_id]"<?= field_attrs($fieldPrefix . 'background_media_asset_id', $formId) ?>>
                    <option value=""><?= e(__('common.none')) ?></option>
                    <?php foreach (($imageMediaAssets ?? []) as $asset): ?>
                        <option value="<?= e((string)$asset['id']) ?>" <?= selected($selectedAssetId, $asset['id']) ?>>
                            <?= e($asset['name']) ?> · <?= e($asset['original_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error_html($fieldPrefix . 'background_media_asset_id', $formId) ?>
            </label>

            <label data-tl1menu-toggle-target="background-image" data-tl1menu-show-when="custom"><?= e(__('plugins.tl-1menu.config.upload_background_image')) ?>
                <input type="file" name="plugin_settings[<?= e($plugin->getName()) ?>][background_image_file]" accept="image/*"<?= field_attrs($fieldPrefix . 'background_image_file', $formId) ?>>
                <?= field_error_html($fieldPrefix . 'background_image_file', $formId) ?>
                <small class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
            </label>

            <?php if (!empty($backgroundImageUrl)): ?>
                <div class="tl1menu-admin-preview" data-tl1menu-toggle-target="background-image" data-tl1menu-show-when="custom">
                    <div class="tl1menu-admin-preview__label"><?= e(__('plugins.tl-1menu.config.current_background_image')) ?></div>
                    <img src="<?= e($backgroundImageUrl) ?>" alt="" class="tl1menu-admin-preview__image">
                </div>
            <?php endif; ?>

            <p class="muted full-width"><?= e(__('plugins.tl-1menu.config.background_help')) ?></p>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl-1menu.config.environment_title')) ?></legend>
        <div class="tl1menu-admin-grid">
            <label class="full-width"><?= e(__('plugins.tl-1menu.config.environment_display_style')) ?>
                <select name="plugin_settings[<?= e($plugin->getName()) ?>][environment_display_style]" data-tl1menu-env-preview-control<?= field_attrs($fieldPrefix . 'environment_display_style', $formId) ?>>
                    <option value="global" <?= selected($settings['environment_display_style'] ?? 'global', 'global') ?>><?= e(__('plugins.tl-1menu.config.environment_display_styles.global')) ?></option>
                    <option value="symbols" <?= selected($settings['environment_display_style'] ?? 'global', 'symbols') ?>><?= e(__('plugins.tl-1menu.config.environment_display_styles.symbols')) ?></option>
                    <option value="values" <?= selected($settings['environment_display_style'] ?? 'global', 'values') ?>><?= e(__('plugins.tl-1menu.config.environment_display_styles.values')) ?></option>
                </select>
                <?= field_error_html($fieldPrefix . 'environment_display_style', $formId) ?>
            </label>

            <div class="tl1menu-admin-checklist full-width">
                <label class="checkbox-row">
                    <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_co2]" data-tl1menu-env-preview-control value="1" <?= !empty($settings['display_co2']) ? 'checked' : '' ?>>
                    <span><?= e(__('plugins.tl-1menu.config.display_co2')) ?></span>
                </label>
                <label class="checkbox-row">
                    <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_water]" data-tl1menu-env-preview-control value="1" <?= !empty($settings['display_water']) ? 'checked' : '' ?>>
                    <span><?= e(__('plugins.tl-1menu.config.display_water')) ?></span>
                </label>
                <label class="checkbox-row">
                    <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_animal_welfare]" data-tl1menu-env-preview-control value="1" <?= !empty($settings['display_animal_welfare']) ? 'checked' : '' ?>>
                    <span><?= e(__('plugins.tl-1menu.config.display_animal_welfare')) ?></span>
                </label>
                <label class="checkbox-row">
                    <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_rainforest]" data-tl1menu-env-preview-control value="1" <?= !empty($settings['display_rainforest']) ? 'checked' : '' ?>>
                    <span><?= e(__('plugins.tl-1menu.config.display_rainforest')) ?></span>
                </label>
            </div>

            <?php
            $environmentPreviewModes = ['card', 'list'];
            $environmentPreviewLayout = 'dynamic';
            $environmentPreviewShowModeLabels = false;
            require __DIR__ . '/partials/environment_preview.php';
            ?>
        </div>
    </fieldset>

    <?php if ($priceGroups !== []): ?>
        <fieldset class="full-width">
            <legend><?= e(__('plugins.tl-1menu.config.price_title')) ?></legend>
            <div class="tl1menu-admin-checklist">
                <?php foreach ($priceGroups as $priceGroup): ?>
                    <?php $priceKey = (string)($priceGroup['key'] ?? ''); ?>
                    <?php if ($priceKey === '') { continue; } ?>
                    <label class="checkbox-row">
                        <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_price_groups][<?= e($priceKey) ?>]" value="1" <?= ($displayPriceGroups === [] || !empty($displayPriceGroups[$priceKey])) ? 'checked' : '' ?>>
                        <span><?= e($plugin->getMenuService($globalSettings)->getPriceGroupLabel($priceKey, current_locale())) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endif; ?>

    <details class="tl1menu-admin-advanced full-width">
        <summary><?= e(__('plugins.tl-1menu.config.advanced_options')) ?></summary>
        <div class="tl1menu-admin-advanced__content">
            <fieldset class="full-width">
                <legend><?= e(__('plugins.tl-1menu.config.exclude_types')) ?></legend>
                <p class="muted"><?= e(__('plugins.tl-1menu.config.exclude_types_help')) ?></p>
                <div class="tl1menu-admin-checklist">
                    <?php foreach ($foodTypes as $typeId => $typeKey): ?>
                        <label class="checkbox-row">
                            <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][exclude_types][]" value="<?= e((string)$typeId) ?>" <?= in_array((int)$typeId, $selectedTypes, true) ? 'checked' : '' ?>>
                            <span><?= e($plugin->getMenuService($globalSettings)->getFoodTypeLabel((int)$typeId, (string)$typeKey, current_locale())) ?> (<?= e((string)$typeId) ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>
    </details>
</div>
<?php require __DIR__ . '/partials/admin_settings_script.php'; ?>
