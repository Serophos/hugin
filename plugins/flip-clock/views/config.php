<?php
/**
 * Hugin - Digital Signage System
 * Copyright (C) 2026 Thees Winkler
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Source code: https://github.com/Serophos/hugin
 */

$formId = 'slide';
$fieldPrefix = 'plugin_settings.' . $plugin->getName() . '.';
$selectedAssetId = (string)($settings['background_media_asset_id'] ?? '');
?>
<div class="plugin-settings-card">
    <h3><?= e(__('plugins.flip-clock.config.title')) ?></h3>
    <p class="muted"><?= e(__('plugins.flip-clock.config.intro')) ?></p>
    <label><?= e(__('plugins.flip-clock.config.background_color')) ?>
        <input type="color" name="plugin_settings[<?= e($plugin->getName()) ?>][background_color]" value="<?= e($settings['background_color']) ?>"<?= field_attrs($fieldPrefix . 'background_color', $formId) ?>>
        <?= field_error_html($fieldPrefix . 'background_color', $formId) ?>
    </label>
    <label class="full-width"><?= e(__('plugins.flip-clock.config.background_media_asset')) ?>
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
    <label class="full-width"><?= e(__('plugins.flip-clock.config.upload_background_image')) ?>
        <input type="file" name="plugin_settings[<?= e($plugin->getName()) ?>][background_image_file]" accept="image/*"<?= field_attrs($fieldPrefix . 'background_image_file', $formId) ?>>
        <?= field_error_html($fieldPrefix . 'background_image_file', $formId) ?>
        <small class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
    </label>
    <?php if (!empty($backgroundImageUrl)): ?>
        <div class="flip-clock-admin-preview">
            <div class="flip-clock-admin-preview__label"><?= e(__('plugins.flip-clock.config.current_background_image')) ?></div>
            <img src="<?= e($backgroundImageUrl) ?>" alt="" class="flip-clock-admin-preview__image">
        </div>
    <?php endif; ?>
    <label class="checkbox-row">
        <input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_seconds]" value="1" <?= checked(!empty($settings['show_seconds'])) ?>>
        <span><?= e(__('plugins.flip-clock.config.show_seconds')) ?></span>
    </label>
</div>
