<?php
$settings = is_array($settings ?? null) ? $settings : [];
$globalSettings = is_array($globalSettings ?? null) ? $globalSettings : [];
$availableMensen = is_array($mensen ?? null) ? $mensen : [];
$foodTypes = is_array($foodTypes ?? null) ? $foodTypes : [];
$priceGroups = is_array($priceGroups ?? null) ? $priceGroups : [];
$imageMediaAssets = is_array($imageMediaAssets ?? null) ? $imageMediaAssets : [];
$displayModePreviews = is_array($displayModePreviews ?? null) ? $displayModePreviews : [];
$selectedMensa = (string)($settings['mensa'] ?? '');
$language = (string)($settings['language'] ?? 'de');
$displayMode = (string)($settings['display_mode'] ?? 'card');
$excludedTypes = array_values(array_map('intval', is_array($settings['exclude_types'] ?? null) ? $settings['exclude_types'] : []));
$displayPriceGroups = is_array($settings['display_price_groups'] ?? null) ? $settings['display_price_groups'] : [];
$backgroundColorMode = (string)($settings['background_color_mode'] ?? 'global');
$backgroundImageMode = (string)($settings['background_image_mode'] ?? 'global');
$backgroundColor = normalize_hex_color((string)($settings['background_color'] ?? ($globalSettings['background_color'] ?? '#f1f5f9')), '#f1f5f9');
$selectedAssetId = (string)($settings['background_media_asset_id'] ?? '');
$environmentDisplayStyle = (string)($settings['environment_display_style'] ?? 'global');
$globalEnvironmentDisplayStyle = (string)($globalSettings['environment_display_style'] ?? 'symbols');
if (!in_array($globalEnvironmentDisplayStyle, ['symbols', 'values'], true)) {
    $globalEnvironmentDisplayStyle = 'symbols';
}
$previewLanguageSetting = (string)current_locale();
$previewLanguage = in_array($previewLanguageSetting, ['de', 'en'], true) ? $previewLanguageSetting : 'de';
$environmentPreviewMetrics = $plugin->getEnvironmentPreviewMetrics($previewLanguage);
$fieldPrefix = 'plugin_settings.' . $plugin->getName() . '.';
$formId = 'slide';
?>
<link rel="stylesheet" href="<?= e(url('/plugin-assets/' . $plugin->getName() . '/assets/tl1menu.css')) ?>">
<div class="plugin-settings-card tl1menu-slide-settings" data-tl1menu-settings>
    <h3><?= e(__('plugins.tl1-menu.config.title')) ?></h3>
    <p class="muted"><?= e(__('plugins.tl1-menu.config.intro')) ?></p>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl1-menu.config.title')) ?></legend>
        <div class="tl1menu-admin-grid">
            <label><?= e(__('plugins.tl1-menu.config.mensa')) ?>
                <select name="plugin_settings[<?= e($plugin->getName()) ?>][mensa]"<?= field_attrs($fieldPrefix . 'mensa', $formId) ?>>
                    <option value=""><?= e(__('common.none')) ?></option>
                    <?php if ($selectedMensa !== '' && !in_array($selectedMensa, $availableMensen, true)): ?>
                        <option value="<?= e($selectedMensa) ?>" selected><?= e($selectedMensa) ?></option>
                    <?php endif; ?>
                    <?php foreach ($availableMensen as $mensaKey): ?>
                        <option value="<?= e((string)$mensaKey) ?>" <?= selected($selectedMensa, (string)$mensaKey) ?>><?= e($menuService->getMensaLabel((string)$mensaKey)) ?> (<?= e((string)$mensaKey) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?= field_error_html($fieldPrefix . 'mensa', $formId) ?>
            </label>
            <label><?= e(__('plugins.tl1-menu.config.language')) ?>
                <select name="plugin_settings[<?= e($plugin->getName()) ?>][language]"<?= field_attrs($fieldPrefix . 'language', $formId) ?>>
                    <option value="de" <?= selected($language, 'de') ?>><?= e(__('plugins.tl1-menu.languages.de')) ?></option>
                    <option value="en" <?= selected($language, 'en') ?>><?= e(__('plugins.tl1-menu.languages.en')) ?></option>
                </select>
                <?= field_error_html($fieldPrefix . 'language', $formId) ?>
            </label>
            <label class="checkbox-row tl1menu-admin-inline-check">
                <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_header]" value="1" <?= checked(!empty($settings['show_header'])) ?>>
                <span><?= e(__('plugins.tl1-menu.config.show_header')) ?></span>
            </label>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl1-menu.config.display_mode')) ?></legend>
        <div class="tl1menu-display-mode-field">
            <div class="tl1menu-display-mode-options">
                <?php foreach (['card', 'list'] as $mode): ?>
                    <label class="tl1menu-display-mode-option">
                        <input class="tl1menu-display-mode-option__input" type="radio" name="plugin_settings[<?= e($plugin->getName()) ?>][display_mode]" value="<?= e($mode) ?>" <?= checked($displayMode === $mode) ?><?= field_attrs($fieldPrefix . 'display_mode', $formId) ?>>
                        <span class="tl1menu-display-mode-option__body">
                            <?php if (!empty($displayModePreviews[$mode])): ?>
                                <img class="tl1menu-display-mode-option__preview" src="<?= e((string)$displayModePreviews[$mode]) ?>" alt="">
                            <?php endif; ?>
                            <span class="tl1menu-display-mode-option__title"><?= e(__('plugins.tl1-menu.config.display_modes.' . $mode)) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= field_error_html($fieldPrefix . 'display_mode', $formId) ?>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl1-menu.config.price_title')) ?></legend>
        <div class="tl1menu-admin-checklist">
            <?php foreach ($priceGroups as $priceGroup): ?>
                <?php $priceKey = (string)($priceGroup['key'] ?? ''); ?>
                <?php if ($priceKey === '') continue; ?>
                <label class="checkbox-row">
                    <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_price_groups][<?= e($priceKey) ?>]" value="1" <?= checked(!empty($displayPriceGroups[$priceKey])) ?>>
                    <span><?= e($menuService->getPriceGroupLabel($priceKey, $language)) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl1-menu.config.environment_title')) ?></legend>
        <div class="tl1menu-admin-grid">
            <label><?= e(__('plugins.tl1-menu.config.environment_display_style')) ?>
                <select name="plugin_settings[<?= e($plugin->getName()) ?>][environment_display_style]" data-tl1menu-env-preview-control<?= field_attrs($fieldPrefix . 'environment_display_style', $formId) ?>>
                    <?php foreach (['global', 'symbols', 'values'] as $style): ?>
                        <option value="<?= e($style) ?>" <?= selected($environmentDisplayStyle, $style) ?>><?= e(__('plugins.tl1-menu.config.environment_display_styles.' . $style)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error_html($fieldPrefix . 'environment_display_style', $formId) ?>
            </label>
            <div class="tl1menu-admin-checklist full-width">
                <?php foreach (['display_co2', 'display_water', 'display_animal_welfare', 'display_rainforest'] as $key): ?>
                    <label class="checkbox-row">
                        <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][<?= e($key) ?>]" value="1" <?= checked(!empty($settings[$key])) ?>>
                        <span><?= e(__('plugins.tl1-menu.config.' . $key)) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php
            $environmentPreviewModes = ['card', 'list'];
            $environmentPreviewLayout = 'dynamic';
            $environmentPreviewShowModeLabels = true;
            require __DIR__ . '/partials/environment_preview.php';
            ?>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl1-menu.config.exclude_types')) ?></legend>
        <p class="muted"><?= e(__('plugins.tl1-menu.config.exclude_types_help')) ?></p>
        <div class="tl1menu-admin-checklist">
            <?php foreach ($foodTypes as $typeId => $typeKey): ?>
                <label class="checkbox-row">
                    <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][exclude_types][]" value="<?= e((string)$typeId) ?>" <?= checked(in_array((int)$typeId, $excludedTypes, true)) ?>>
                    <span><?= e($menuService->getFoodTypeLabel((int)$typeId, (string)$typeKey, $language)) ?> (<?= e((string)$typeId) ?>)</span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <details class="tl1menu-admin-advanced full-width">
        <summary><?= e(__('plugins.tl1-menu.config.advanced_options')) ?></summary>
        <div class="tl1menu-admin-advanced__content">
            <fieldset class="full-width">
                <legend><?= e(__('plugins.tl1-menu.config.background_title')) ?></legend>
                <p class="muted"><?= e(__('plugins.tl1-menu.config.background_help')) ?></p>
                <div class="tl1menu-admin-grid">
                    <label><?= e(__('plugins.tl1-menu.config.background_color_mode')) ?>
                        <select name="plugin_settings[<?= e($plugin->getName()) ?>][background_color_mode]" data-tl1menu-toggle="background-color"<?= field_attrs($fieldPrefix . 'background_color_mode', $formId) ?>>
                            <?php foreach (['global', 'custom'] as $mode): ?>
                                <option value="<?= e($mode) ?>" <?= selected($backgroundColorMode, $mode) ?>><?= e(__('plugins.tl1-menu.config.background_color_modes.' . $mode)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error_html($fieldPrefix . 'background_color_mode', $formId) ?>
                    </label>
                    <label data-tl1menu-toggle-target="background-color" data-tl1menu-show-when="custom"><?= e(__('plugins.tl1-menu.config.background_color')) ?>
                        <span class="admin-color-picker admin-color-picker--compact" data-admin-color-picker data-color-format="hex" data-color-alpha="false" data-default-color="#f1f5f9" data-default-alpha="1">
                            <input type="color" value="#f1f5f9" data-color-picker-swatch>
                            <input type="hidden" name="plugin_settings[<?= e($plugin->getName()) ?>][background_color]" value="<?= e($backgroundColor) ?>" data-color-value<?= field_attrs($fieldPrefix . 'background_color', $formId) ?>>
                        </span>
                        <?= field_error_html($fieldPrefix . 'background_color', $formId) ?>
                    </label>
                    <label><?= e(__('plugins.tl1-menu.config.background_image_mode')) ?>
                        <select name="plugin_settings[<?= e($plugin->getName()) ?>][background_image_mode]" data-tl1menu-toggle="background-image"<?= field_attrs($fieldPrefix . 'background_image_mode', $formId) ?>>
                            <?php foreach (['global', 'none', 'custom'] as $mode): ?>
                                <option value="<?= e($mode) ?>" <?= selected($backgroundImageMode, $mode) ?>><?= e(__('plugins.tl1-menu.config.background_image_modes.' . $mode)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error_html($fieldPrefix . 'background_image_mode', $formId) ?>
                    </label>
                    <label data-tl1menu-toggle-target="background-image" data-tl1menu-show-when="custom"><?= e(__('plugins.tl1-menu.config.background_media_asset')) ?>
                        <select name="plugin_settings[<?= e($plugin->getName()) ?>][background_media_asset_id]"<?= field_attrs($fieldPrefix . 'background_media_asset_id', $formId) ?>>
                            <option value=""><?= e(__('common.none')) ?></option>
                            <?php foreach ($imageMediaAssets as $asset): ?>
                                <option value="<?= e((string)$asset['id']) ?>" <?= selected($selectedAssetId, (string)$asset['id']) ?>><?= e($asset['name']) ?> · <?= e($asset['original_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error_html($fieldPrefix . 'background_media_asset_id', $formId) ?>
                    </label>
                    <label data-tl1menu-toggle-target="background-image" data-tl1menu-show-when="custom"><?= e(__('plugins.tl1-menu.config.upload_background_image')) ?>
                        <input type="file" name="plugin_settings[<?= e($plugin->getName()) ?>][background_image_file]" accept="image/*"<?= field_attrs($fieldPrefix . 'background_image_file', $formId) ?>>
                        <?= field_error_html($fieldPrefix . 'background_image_file', $formId) ?>
                        <small class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
                    </label>
                    <?php if (!empty($backgroundImageUrl)): ?>
                        <div class="tl1menu-admin-preview tl1menu-admin-preview--inline" data-tl1menu-toggle-target="background-image" data-tl1menu-show-when="custom" data-tl1menu-preserve-slot="true">
                            <div class="tl1menu-admin-preview__label"><?= e(__('plugins.tl1-menu.config.current_background_image')) ?></div>
                            <img src="<?= e($backgroundImageUrl) ?>" alt="" class="tl1menu-admin-preview__image">
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>
        </div>
    </details>
</div>
<?php require __DIR__ . '/partials/admin_settings_script.php'; ?>
