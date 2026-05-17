<?php
$formId = 'slide';
$fieldPrefix = 'plugin_settings.' . $plugin->getName() . '.';
$backgroundColorMode = (string)($settings['background_color_mode'] ?? 'global');
$backgroundImageMode = (string)($settings['background_image_mode'] ?? 'global');
$slideBackgroundColor = normalize_hex_color((string)($settings['background_color'] ?? ''), normalize_hex_color((string)($globalSettings['background_color'] ?? '#f1f5f9'), '#f1f5f9'));
$selectedAssetId = (string)($settings['background_media_asset_id'] ?? '');
?>
<div class="plugin-settings-card tl1menu-slide-settings">
    <h3><?= e(__('plugins.tl-1menu.config.title')) ?></h3>
    <p class="muted"><?= e(__('plugins.tl-1menu.config.intro')) ?></p>

    <label class="full-width"><?= e(__('plugins.tl-1menu.config.mensa')) ?>
        <select name="plugin_settings[<?= e($plugin->getName()) ?>][mensa]"<?= field_attrs($fieldPrefix . 'mensa', $formId) ?>>
            <?php foreach ($mensen as $mensaKey): ?>
                <option value="<?= e($mensaKey) ?>" <?= selected($settings['mensa'], $mensaKey) ?>>
                    <?= e($plugin->getMenuService()->getMensaLabel($mensaKey)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?= field_error_html($fieldPrefix . 'mensa', $formId) ?>
    </label>

    <label class="full-width"><?= e(__('plugins.tl-1menu.config.language')) ?>
        <select name="plugin_settings[<?= e($plugin->getName()) ?>][language]"<?= field_attrs($fieldPrefix . 'language', $formId) ?>>
            <option value="de" <?= selected($settings['language'], 'de') ?>><?= e(__('plugins.tl-1menu.languages.de')) ?></option>
            <option value="en" <?= selected($settings['language'], 'en') ?>><?= e(__('plugins.tl-1menu.languages.en')) ?></option>
        </select>
        <?= field_error_html($fieldPrefix . 'language', $formId) ?>
    </label>

    <label class="full-width"><?= e(__('plugins.tl-1menu.config.display_mode')) ?>
        <select name="plugin_settings[<?= e($plugin->getName()) ?>][display_mode]"<?= field_attrs($fieldPrefix . 'display_mode', $formId) ?>>
            <option value="card" <?= selected($settings['display_mode'] ?? 'card', 'card') ?>><?= e(__('plugins.tl-1menu.config.display_modes.card')) ?></option>
            <option value="list" <?= selected($settings['display_mode'] ?? 'card', 'list') ?>><?= e(__('plugins.tl-1menu.config.display_modes.list')) ?></option>
        </select>
        <?= field_error_html($fieldPrefix . 'display_mode', $formId) ?>
    </label>


    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl-1menu.config.background_title')) ?></legend>
        <label class="full-width"><?= e(__('plugins.tl-1menu.config.background_color_mode')) ?>
            <select name="plugin_settings[<?= e($plugin->getName()) ?>][background_color_mode]"<?= field_attrs($fieldPrefix . 'background_color_mode', $formId) ?>>
                <option value="global" <?= selected($backgroundColorMode, 'global') ?>><?= e(__('plugins.tl-1menu.config.background_color_modes.global')) ?></option>
                <option value="custom" <?= selected($backgroundColorMode, 'custom') ?>><?= e(__('plugins.tl-1menu.config.background_color_modes.custom')) ?></option>
            </select>
            <?= field_error_html($fieldPrefix . 'background_color_mode', $formId) ?>
        </label>
        <label class="tl1menu-color-control"><?= e(__('plugins.tl-1menu.config.background_color')) ?>
            <input type="color" name="plugin_settings[<?= e($plugin->getName()) ?>][background_color]" value="<?= e($slideBackgroundColor) ?>"<?= field_attrs($fieldPrefix . 'background_color', $formId) ?>>
            <?= field_error_html($fieldPrefix . 'background_color', $formId) ?>
        </label>
        <label class="full-width"><?= e(__('plugins.tl-1menu.config.background_image_mode')) ?>
            <select name="plugin_settings[<?= e($plugin->getName()) ?>][background_image_mode]"<?= field_attrs($fieldPrefix . 'background_image_mode', $formId) ?>>
                <option value="global" <?= selected($backgroundImageMode, 'global') ?>><?= e(__('plugins.tl-1menu.config.background_image_modes.global')) ?></option>
                <option value="none" <?= selected($backgroundImageMode, 'none') ?>><?= e(__('plugins.tl-1menu.config.background_image_modes.none')) ?></option>
                <option value="custom" <?= selected($backgroundImageMode, 'custom') ?>><?= e(__('plugins.tl-1menu.config.background_image_modes.custom')) ?></option>
            </select>
            <?= field_error_html($fieldPrefix . 'background_image_mode', $formId) ?>
        </label>
        <label class="full-width"><?= e(__('plugins.tl-1menu.config.background_media_asset')) ?>
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
        <label class="full-width"><?= e(__('plugins.tl-1menu.config.upload_background_image')) ?>
            <input type="file" name="plugin_settings[<?= e($plugin->getName()) ?>][background_image_file]" accept="image/*"<?= field_attrs($fieldPrefix . 'background_image_file', $formId) ?>>
            <?= field_error_html($fieldPrefix . 'background_image_file', $formId) ?>
            <small class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
        </label>
        <p class="muted"><?= e(__('plugins.tl-1menu.config.background_help')) ?></p>
        <?php if (!empty($backgroundImageUrl)): ?>
            <div class="tl1menu-admin-preview">
                <div class="tl1menu-admin-preview__label"><?= e(__('plugins.tl-1menu.config.current_background_image')) ?></div>
                <img src="<?= e($backgroundImageUrl) ?>" alt="" class="tl1menu-admin-preview__image">
            </div>
        <?php endif; ?>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl-1menu.config.environment_title')) ?></legend>
        <label class="full-width"><?= e(__('plugins.tl-1menu.config.environment_display_style')) ?>
            <select name="plugin_settings[<?= e($plugin->getName()) ?>][environment_display_style]"<?= field_attrs($fieldPrefix . 'environment_display_style', $formId) ?>>
                <option value="global" <?= selected($settings['environment_display_style'] ?? 'global', 'global') ?>><?= e(__('plugins.tl-1menu.config.environment_display_styles.global')) ?></option>
                <option value="symbols" <?= selected($settings['environment_display_style'] ?? 'global', 'symbols') ?>><?= e(__('plugins.tl-1menu.config.environment_display_styles.symbols')) ?></option>
                <option value="values" <?= selected($settings['environment_display_style'] ?? 'global', 'values') ?>><?= e(__('plugins.tl-1menu.config.environment_display_styles.values')) ?></option>
            </select>
            <?= field_error_html($fieldPrefix . 'environment_display_style', $formId) ?>
        </label>
        <div class="tl1menu-admin-checklist">
            <label class="checkbox-row">
                <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_co2]" value="1" <?= !empty($settings['display_co2']) ? 'checked' : '' ?>>
                <span><?= e(__('plugins.tl-1menu.config.display_co2')) ?></span>
            </label>
            <label class="checkbox-row">
                <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_water]" value="1" <?= !empty($settings['display_water']) ? 'checked' : '' ?>>
                <span><?= e(__('plugins.tl-1menu.config.display_water')) ?></span>
            </label>
            <label class="checkbox-row">
                <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_animal_welfare]" value="1" <?= !empty($settings['display_animal_welfare']) ? 'checked' : '' ?>>
                <span><?= e(__('plugins.tl-1menu.config.display_animal_welfare')) ?></span>
            </label>
            <label class="checkbox-row">
                <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_rainforest]" value="1" <?= !empty($settings['display_rainforest']) ? 'checked' : '' ?>>
                <span><?= e(__('plugins.tl-1menu.config.display_rainforest')) ?></span>
            </label>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl-1menu.config.price_title')) ?></legend>
        <div class="tl1menu-admin-checklist">
            <label class="checkbox-row">
                <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_student_price]" value="1" <?= !empty($settings['display_student_price']) ? 'checked' : '' ?>>
                <span><?= e(__('plugins.tl-1menu.config.display_student_price')) ?></span>
            </label>
            <label class="checkbox-row">
                <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_employee_price]" value="1" <?= !empty($settings['display_employee_price']) ? 'checked' : '' ?>>
                <span><?= e(__('plugins.tl-1menu.config.display_employee_price')) ?></span>
            </label>
            <label class="checkbox-row">
                <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][display_guest_price]" value="1" <?= !empty($settings['display_guest_price']) ? 'checked' : '' ?>>
                <span><?= e(__('plugins.tl-1menu.config.display_guest_price')) ?></span>
            </label>
        </div>
    </fieldset>

    <details class="tl1menu-admin-advanced full-width">
        <summary><?= e(__('plugins.tl-1menu.config.advanced_options')) ?></summary>
        <div class="tl1menu-admin-advanced__content">
            <label class="checkbox-row">
                <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_header]" value="1" <?= !empty($settings['show_header']) ? 'checked' : '' ?>>
                <span><?= e(__('plugins.tl-1menu.config.show_header')) ?></span>
            </label>

            <fieldset class="full-width">
                <legend><?= e(__('plugins.tl-1menu.config.exclude_types')) ?></legend>
                <p class="muted"><?= e(__('plugins.tl-1menu.config.exclude_types_help')) ?></p>
                <div class="tl1menu-admin-checklist">
                    <?php $selectedTypes = array_map('intval', is_array($settings['exclude_types'] ?? null) ? $settings['exclude_types'] : []); ?>
                    <?php foreach ($foodTypes as $typeId => $typeKey): ?>
                        <label class="checkbox-row">
                            <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][exclude_types][]" value="<?= e((string)$typeId) ?>" <?= in_array((int)$typeId, $selectedTypes, true) ? 'checked' : '' ?>>
                            <span><?= e($plugin->getMenuService()->getFoodTypeLabel((int)$typeId, (string)$typeKey, current_locale())) ?> (<?= e((string)$typeId) ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>
    </details>
</div>
