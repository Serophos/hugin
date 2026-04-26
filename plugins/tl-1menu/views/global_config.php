<?php
$backgroundColor = normalize_hex_color((string)($settings['background_color'] ?? '#f1f5f9'), '#f1f5f9');
$selectedAssetId = (string)($settings['background_media_asset_id'] ?? '');
?>
<div class="plugin-settings-card tl1menu-global-settings">
    <h3><?= e(__('plugins.tl-1menu.global_config.title')) ?></h3>
    <p class="muted"><?= e(__('plugins.tl-1menu.global_config.intro')) ?></p>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl-1menu.global_config.background_color')) ?></legend>
        <label class="tl1menu-color-control"><?= e(__('plugins.tl-1menu.global_config.color_picker')) ?>
            <input type="color" name="plugin_global_settings[<?= e($plugin->getName()) ?>][background_color]" value="<?= e($backgroundColor) ?>">
        </label>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl-1menu.global_config.background_image')) ?></legend>
        <label class="full-width"><?= e(__('plugins.tl-1menu.global_config.media_library_image')) ?>
            <select name="plugin_global_settings[<?= e($plugin->getName()) ?>][background_media_asset_id]">
                <option value=""><?= e(__('common.none')) ?></option>
                <?php foreach (($imageMediaAssets ?? []) as $asset): ?>
                    <option value="<?= e((string)$asset['id']) ?>" <?= selected($selectedAssetId, $asset['id']) ?>>
                        <?= e($asset['name']) ?> · <?= e($asset['original_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="full-width"><?= e(__('plugins.tl-1menu.global_config.upload_background_image')) ?>
            <input type="file" name="plugin_global_settings[<?= e($plugin->getName()) ?>][background_image_file]" accept="image/*">
        </label>
        <p class="muted"><?= e(__('plugins.tl-1menu.global_config.background_image_help')) ?></p>

        <?php if (!empty($backgroundImageUrl)): ?>
            <div class="tl1menu-admin-preview">
                <div class="tl1menu-admin-preview__label"><?= e(__('plugins.tl-1menu.global_config.current_background_image')) ?></div>
                <img src="<?= e($backgroundImageUrl) ?>" alt="" class="tl1menu-admin-preview__image">
            </div>
        <?php endif; ?>
    </fieldset>
</div>
