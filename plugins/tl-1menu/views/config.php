<?php
$formId = 'slide';
$fieldPrefix = 'plugin_settings.' . $plugin->getName() . '.';
?>
<div class="plugin-settings-card">
    <h3><?= e(__('plugins.tl-1menu.config.title')) ?></h3>
    <p class="muted"><?= e(__('plugins.tl-1menu.config.intro')) ?></p>

    <label class="full-width"><?= e(__('plugins.tl-1menu.config.mensa')) ?>
        <select name="plugin_settings[<?= e($plugin->getName()) ?>][mensa]"<?= field_attrs($fieldPrefix . 'mensa', $formId) ?>>
            <?php foreach ($mensen as $mensaKey): ?>
                <option value="<?= e($mensaKey) ?>" <?= selected($settings['mensa'], $mensaKey) ?>>
                    <?= e(__('plugins.tl-1menu.locations.' . $mensaKey, [], $mensaKey)) ?>
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

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl-1menu.config.environment_title')) ?></legend>
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
                            <span><?= e($plugin->getMenuService()->getFoodTypeLabel((int)$typeId, (string)$typeKey)) ?> (<?= e((string)$typeId) ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>
    </details>
</div>
