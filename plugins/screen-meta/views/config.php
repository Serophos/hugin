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

?>
<div class="plugin-settings-card">
    <h3><?= e(__('plugins.screen-meta.config.title')) ?></h3>
    <p class="muted"><?= e(__('plugins.screen-meta.config.intro')) ?></p>
    <label class="full-width"><?= e(__('plugins.screen-meta.config.heading')) ?>
        <input type="text" name="plugin_settings[<?= e($plugin->getName()) ?>][heading]" value="<?= e($settings['heading']) ?>">
    </label>
    <div class="checkbox-grid compact">
        <label class="checkbox-row"><input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_browser]" value="1" <?= checked($settings['show_browser']) ?>> <?= e(__('plugins.screen-meta.config.show_browser')) ?></label>
        <label class="checkbox-row"><input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_os]" value="1" <?= checked($settings['show_os']) ?>> <?= e(__('plugins.screen-meta.config.show_os')) ?></label>
        <label class="checkbox-row"><input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_resolution]" value="1" <?= checked($settings['show_resolution']) ?>> <?= e(__('plugins.screen-meta.config.show_resolution')) ?></label>
        <label class="checkbox-row"><input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_viewport]" value="1" <?= checked($settings['show_viewport']) ?>> <?= e(__('plugins.screen-meta.config.show_viewport')) ?></label>
        <label class="checkbox-row"><input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_ip]" value="1" <?= checked($settings['show_ip']) ?>> <?= e(__('plugins.screen-meta.config.show_ip')) ?></label>
        <label class="checkbox-row"><input type="checkbox" name="plugin_settings[<?= e($plugin->getName()) ?>][show_timezone]" value="1" <?= checked($settings['show_timezone']) ?>> <?= e(__('plugins.screen-meta.config.show_timezone')) ?></label>
    </div>
    <label class="full-width"><?= e(__('plugins.screen-meta.config.note')) ?>
        <textarea name="plugin_settings[<?= e($plugin->getName()) ?>][note]" rows="3"><?= e($settings['note']) ?></textarea>
    </label>
</div>
