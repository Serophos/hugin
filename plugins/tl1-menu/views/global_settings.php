<?php
$settings = is_array($settings ?? null) ? $settings : [];
$backgroundColor = normalize_hex_color((string)($settings['background_color'] ?? '#f1f5f9'), '#f1f5f9');
$selectedAssetId = (string)($settings['background_media_asset_id'] ?? '');
$environmentDisplayStyle = (string)($settings['environment_display_style'] ?? 'symbols');
if (!in_array($environmentDisplayStyle, ['symbols', 'values'], true)) {
    $environmentDisplayStyle = 'symbols';
}
$defaultLanguage = (string)($settings['default_language'] ?? 'de');
$defaultMensa = (string)($settings['default_mensa'] ?? '');
$defaultExclude = array_values(array_map('intval', is_array($settings['default_exclude'] ?? null) ? $settings['default_exclude'] : preg_split('/\s*,\s*/', (string)($settings['default_exclude'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)));
$availableMensen = is_array($mensen ?? null) ? $mensen : [];
$foodTypes = is_array($foodTypes ?? null) ? $foodTypes : [];
$environmentIcons = is_array($settings['environment_rating_icons'] ?? null) ? $settings['environment_rating_icons'] : [];
$previewLanguageSetting = (string)current_locale();
$previewLanguage = in_array($previewLanguageSetting, ['de', 'en'], true) ? $previewLanguageSetting : 'de';
$environmentPreviewMetrics = array_map(static function (array $metric): array {
    $metric['setting'] = '';
    return $metric;
}, $plugin->getEnvironmentPreviewMetrics($previewLanguage));
$globalEnvironmentDisplayStyle = $environmentDisplayStyle;
$environmentIconAssets = is_array($environmentIconAssets ?? null) ? $environmentIconAssets : [];
$categoryIconChoices = is_array($categoryIconChoices ?? null) ? $categoryIconChoices : [];
$formId = 'plugin_settings';
$setupActionBaseUrl = (string)($setupActionBaseUrl ?? '');
$parserConfig = is_array($parserConfig ?? null) ? $parserConfig : [];
?>
<link rel="stylesheet" href="<?= e(plugin_asset_url($plugin->getName(), 'assets/tl1menu.css')) ?>">
<?php
$setupI18n = [
    'no_field' => __('plugins.tl1-menu.setup.no_field'),
    'summary.rows' => __('plugins.tl1-menu.setup.summary.rows'),
    'summary.locations' => __('plugins.tl1-menu.setup.summary.locations'),
    'summary.food_types' => __('plugins.tl1-menu.setup.summary.food_types'),
    'summary.tokens' => __('plugins.tl1-menu.setup.summary.tokens'),
    'sections.field_mapping' => __('plugins.tl1-menu.setup.sections.field_mapping'),
    'sections.price_groups' => __('plugins.tl1-menu.setup.sections.price_groups'),
    'sections.locations' => __('plugins.tl1-menu.setup.sections.locations'),
    'sections.food_types' => __('plugins.tl1-menu.setup.sections.food_types'),
    'sections.tokens' => __('plugins.tl1-menu.setup.sections.tokens'),
    'sections.categories' => __('plugins.tl1-menu.setup.sections.categories'),
    'fields.id' => __('plugins.tl1-menu.setup.fields.id'),
    'fields.key' => __('plugins.tl1-menu.setup.fields.key'),
    'fields.field' => __('plugins.tl1-menu.setup.fields.field'),
    'fields.label' => __('plugins.tl1-menu.setup.fields.label'),
    'fields.location_ids' => __('plugins.tl1-menu.setup.fields.location_ids'),
    'fields.categories' => __('plugins.tl1-menu.setup.fields.categories'),
    'fields.code' => __('plugins.tl1-menu.setup.fields.code'),
    'fields.kind' => __('plugins.tl1-menu.setup.fields.kind'),
    'fields.category' => __('plugins.tl1-menu.setup.fields.category'),
    'fields.icon' => __('plugins.tl1-menu.setup.fields.icon'),
    'fields.de' => __('plugins.tl1-menu.setup.fields.de'),
    'fields.en' => __('plugins.tl1-menu.setup.fields.en'),
    'fields.actions' => __('plugins.tl1-menu.setup.fields.actions'),
    'actions.remove' => __('plugins.tl1-menu.setup.actions.remove'),
    'actions.add_price_group' => __('plugins.tl1-menu.setup.actions.add_price_group'),
    'actions.add_location' => __('plugins.tl1-menu.setup.actions.add_location'),
    'actions.add_food_type' => __('plugins.tl1-menu.setup.actions.add_food_type'),
    'actions.add_token' => __('plugins.tl1-menu.setup.actions.add_token'),
    'actions.add_category' => __('plugins.tl1-menu.setup.actions.add_category'),
    'icon_upload.choose_file' => __('plugins.tl1-menu.setup.icon_upload.choose_file'),
    'icon_upload.uploading' => __('plugins.tl1-menu.setup.icon_upload.uploading'),
    'icon_upload.uploaded' => __('plugins.tl1-menu.setup.icon_upload.uploaded'),
    'empty_table' => __('plugins.tl1-menu.setup.empty_table'),
    'test_empty' => __('plugins.tl1-menu.setup.test_empty'),
    'prompts.location' => __('plugins.tl1-menu.setup.prompts.location'),
    'prompts.food_type' => __('plugins.tl1-menu.setup.prompts.food_type'),
    'prompts.token' => __('plugins.tl1-menu.setup.prompts.token'),
    'prompts.category' => __('plugins.tl1-menu.setup.prompts.category'),
    'mapping.id' => __('plugins.tl1-menu.setup.mapping.id'),
    'mapping.date' => __('plugins.tl1-menu.setup.mapping.date'),
    'mapping.mensa_name' => __('plugins.tl1-menu.setup.mapping.mensa_name'),
    'mapping.location_id' => __('plugins.tl1-menu.setup.mapping.location_id'),
    'mapping.location_name' => __('plugins.tl1-menu.setup.mapping.location_name'),
    'mapping.type_id' => __('plugins.tl1-menu.setup.mapping.type_id'),
    'mapping.type_name' => __('plugins.tl1-menu.setup.mapping.type_name'),
    'mapping.spalte' => __('plugins.tl1-menu.setup.mapping.spalte'),
    'mapping.allergen_codes' => __('plugins.tl1-menu.setup.mapping.allergen_codes'),
    'mapping.title_de' => __('plugins.tl1-menu.setup.mapping.title_de'),
    'mapping.description_de' => __('plugins.tl1-menu.setup.mapping.description_de'),
    'mapping.title_en' => __('plugins.tl1-menu.setup.mapping.title_en'),
    'mapping.description_en' => __('plugins.tl1-menu.setup.mapping.description_en'),
    'mapping.token_fields' => __('plugins.tl1-menu.setup.mapping.token_fields'),
    'mapping.allergen_names_de' => __('plugins.tl1-menu.setup.mapping.allergen_names_de'),
    'mapping.allergen_names_en' => __('plugins.tl1-menu.setup.mapping.allergen_names_en'),
    'mapping.co2_value' => __('plugins.tl1-menu.setup.mapping.co2_value'),
    'mapping.co2_rating' => __('plugins.tl1-menu.setup.mapping.co2_rating'),
    'mapping.co2_saving' => __('plugins.tl1-menu.setup.mapping.co2_saving'),
    'mapping.water_value' => __('plugins.tl1-menu.setup.mapping.water_value'),
    'mapping.water_rating' => __('plugins.tl1-menu.setup.mapping.water_rating'),
    'mapping.animal_welfare' => __('plugins.tl1-menu.setup.mapping.animal_welfare'),
    'mapping.rainforest' => __('plugins.tl1-menu.setup.mapping.rainforest'),
    'kind.allergen' => __('plugins.tl1-menu.setup.kind.allergen'),
    'kind.additive' => __('plugins.tl1-menu.setup.kind.additive'),
    'kind.category' => __('plugins.tl1-menu.setup.kind.category'),
    'kind.ignore' => __('plugins.tl1-menu.setup.kind.ignore'),
    'status.analyzing' => __('plugins.tl1-menu.setup.status.analyzing'),
    'status.analysis_complete' => __('plugins.tl1-menu.setup.status.analysis_complete'),
    'status.previewing' => __('plugins.tl1-menu.setup.status.previewing'),
    'status.preview_updated' => __('plugins.tl1-menu.setup.status.preview_updated'),
    'status.saving' => __('plugins.tl1-menu.setup.status.saving'),
    'status.saved' => __('plugins.tl1-menu.setup.status.saved'),
    'status.showing_schema' => __('plugins.tl1-menu.setup.status.showing_schema'),
    'status.schema_refreshed' => __('plugins.tl1-menu.setup.status.schema_refreshed'),
    'preview.reload' => __('plugins.tl1-menu.setup.preview_actions.reload'),
    'dialog.cancel' => __('plugins.tl1-menu.setup.dialog.cancel'),
    'dialog.analyze_title' => __('plugins.tl1-menu.setup.dialog.analyze_title'),
    'dialog.save_title' => __('plugins.tl1-menu.setup.dialog.save_title'),
    'dialog.remove_title' => __('plugins.tl1-menu.setup.dialog.remove_title'),
    'dialog.accept_analyze' => __('plugins.tl1-menu.setup.dialog.accept_analyze'),
    'dialog.accept_save' => __('plugins.tl1-menu.setup.dialog.accept_save'),
    'dialog.accept_remove' => __('plugins.tl1-menu.setup.dialog.accept_remove'),
    'dialog.accept_add' => __('common.create'),
    'tabs.schema' => __('plugins.tl1-menu.setup.tabs.schema'),
    'tabs.test' => __('plugins.tl1-menu.setup.tabs.test'),
    'confirm_save' => __('plugins.tl1-menu.setup.confirm_save'),
    'confirm_analyze' => __('plugins.tl1-menu.setup.confirm_analyze'),
    'confirm_remove' => __('plugins.tl1-menu.setup.confirm_remove'),
    'errors.missing_action' => __('plugins.tl1-menu.setup.errors.missing_action'),
    'errors.request_failed' => __('plugins.tl1-menu.setup.errors.request_failed'),
    'errors.empty_key' => __('plugins.tl1-menu.setup.errors.empty_key'),
    'errors.duplicate_key' => __('plugins.tl1-menu.setup.errors.duplicate_key'),
];
?>
<div class="plugin-global-settings-form tl1menu-global-settings" data-tl1menu-settings data-tl1menu-setup data-plugin-name="<?= e($plugin->getName()) ?>" data-action-base="<?= e($setupActionBaseUrl) ?>" data-i18n="<?= e(json_encode($setupI18n, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') ?>" data-category-icons="<?= e(json_encode($categoryIconChoices, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]') ?>">
    <h3><?= e(__('plugins.tl1-menu.global_config.title')) ?></h3>
    <p class="muted"><?= e(__('plugins.tl1-menu.global_config.intro')) ?></p>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl1-menu.global_config.feed_title')) ?></legend>
        <div class="tl1menu-admin-grid">
            <label><?= e(__('plugins.tl1-menu.global_config.menu_url')) ?>
                <input type="url" name="plugin_global_settings[<?= e($plugin->getName()) ?>][menu_url]" value="<?= e((string)($settings['menu_url'] ?? '')) ?>" data-tl1menu-menu-url<?= field_attrs('menu_url', $formId) ?>>
                <?= field_error_html('menu_url', $formId) ?>
            </label>
            <label><?= e(__('plugins.tl1-menu.global_config.cache_ttl')) ?>
                <input type="number" min="60" step="60" name="plugin_global_settings[<?= e($plugin->getName()) ?>][cache_ttl]" value="<?= e((string)($settings['cache_ttl'] ?? 1800)) ?>"<?= field_attrs('cache_ttl', $formId) ?>>
                <?= field_error_html('cache_ttl', $formId) ?>
            </label>
            <label><?= e(__('plugins.tl1-menu.global_config.debug_date')) ?>
                <input type="text" name="plugin_global_settings[<?= e($plugin->getName()) ?>][debug_date]" value="<?= e((string)($settings['debug_date'] ?? '')) ?>" placeholder="2026-05-15"<?= field_attrs('debug_date', $formId) ?>>
                <?= field_error_html('debug_date', $formId) ?>
            </label>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl1-menu.global_config.slide_defaults_title')) ?></legend>
        <div class="tl1menu-admin-grid">
            <label><?= e(__('plugins.tl1-menu.config.language')) ?>
                <select name="plugin_global_settings[<?= e($plugin->getName()) ?>][default_language]">
                    <option value="de" <?= selected($defaultLanguage, 'de') ?>><?= e(__('plugins.tl1-menu.languages.de')) ?></option>
                    <option value="en" <?= selected($defaultLanguage, 'en') ?>><?= e(__('plugins.tl1-menu.languages.en')) ?></option>
                </select>
            </label>
            <label><?= e(__('plugins.tl1-menu.global_config.default_mensa')) ?>
                <select name="plugin_global_settings[<?= e($plugin->getName()) ?>][default_mensa]">
                    <option value=""><?= e(__('common.none')) ?></option>
                    <?php if ($defaultMensa !== '' && !in_array($defaultMensa, $availableMensen, true)): ?>
                        <option value="<?= e($defaultMensa) ?>" selected><?= e($defaultMensa) ?></option>
                    <?php endif; ?>
                    <?php foreach ($availableMensen as $mensaKey): ?>
                        <option value="<?= e($mensaKey) ?>" <?= selected($defaultMensa, $mensaKey) ?>><?= e($menuService->getMensaLabel((string)$mensaKey)) ?> (<?= e((string)$mensaKey) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="tl1menu-admin-checklist full-width">
                <?php foreach (['default_show_header', 'default_display_co2', 'default_display_water', 'default_display_animal_welfare', 'default_display_rainforest'] as $key): ?>
                    <label class="checkbox-row">
                        <input type="checkbox" name="plugin_global_settings[<?= e($plugin->getName()) ?>][<?= e($key) ?>]" value="1" <?= !empty($settings[$key]) ? 'checked' : '' ?>>
                        <span><?= e(__('plugins.tl1-menu.global_config.' . $key)) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl1-menu.config.exclude_types')) ?></legend>
        <p class="muted"><?= e(__('plugins.tl1-menu.config.exclude_types_help')) ?></p>
        <div class="tl1menu-admin-checklist">
            <?php foreach ($foodTypes as $typeId => $typeKey): ?>
                <label class="checkbox-row">
                    <input type="checkbox" name="plugin_global_settings[<?= e($plugin->getName()) ?>][default_exclude][]" value="<?= e((string)$typeId) ?>" <?= in_array((int)$typeId, $defaultExclude, true) ? 'checked' : '' ?>>
                    <span><?= e($menuService->getFoodTypeLabel((int)$typeId, (string)$typeKey, current_locale())) ?> (<?= e((string)$typeId) ?>)</span>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl1-menu.global_config.background_title')) ?></legend>
        <div class="tl1menu-admin-grid">
            <label class="tl1menu-color-control"><?= e(__('plugins.tl1-menu.global_config.color_picker')) ?>
                <span class="admin-color-picker admin-color-picker--compact" data-admin-color-picker data-color-format="hex" data-color-alpha="false" data-default-color="#f1f5f9" data-default-alpha="1">
                    <input type="color" value="#f1f5f9" data-color-picker-swatch>
                    <input type="hidden" name="plugin_global_settings[<?= e($plugin->getName()) ?>][background_color]" value="<?= e($backgroundColor) ?>" data-color-value<?= field_attrs('background_color', $formId) ?>>
                </span>
                <?= field_error_html('background_color', $formId) ?>
            </label>
            <label><?= e(__('plugins.tl1-menu.global_config.media_library_image')) ?>
                <select name="plugin_global_settings[<?= e($plugin->getName()) ?>][background_media_asset_id]"<?= field_attrs('background_media_asset_id', $formId) ?>>
                    <option value=""><?= e(__('common.none')) ?></option>
                    <?php foreach (($imageMediaAssets ?? []) as $asset): ?>
                        <option value="<?= e((string)$asset['id']) ?>" <?= selected($selectedAssetId, $asset['id']) ?>><?= e($asset['name']) ?> · <?= e($asset['original_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error_html('background_media_asset_id', $formId) ?>
            </label>
            <label><?= e(__('plugins.tl1-menu.global_config.upload_background_image')) ?>
                <input type="file" name="plugin_global_settings[<?= e($plugin->getName()) ?>][background_image_file]" accept="image/*"<?= field_attrs('background_image_file', $formId) ?>>
                <?= field_error_html('background_image_file', $formId) ?>
                <small class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
            </label>
            <?php if (!empty($backgroundImageUrl)): ?>
                <div class="tl1menu-admin-preview tl1menu-admin-preview--inline">
                    <div class="tl1menu-admin-preview__label"><?= e(__('plugins.tl1-menu.global_config.current_background_image')) ?></div>
                    <img src="<?= e($backgroundImageUrl) ?>" alt="" class="tl1menu-admin-preview__image">
                </div>
            <?php endif; ?>
        </div>
    </fieldset>

    <fieldset class="full-width">
        <legend><?= e(__('plugins.tl1-menu.global_config.environment_display_style')) ?></legend>
        <div class="tl1menu-admin-grid">
            <label><?= e(__('plugins.tl1-menu.global_config.environment_display_style_help')) ?>
                <select name="plugin_global_settings[<?= e($plugin->getName()) ?>][environment_display_style]" data-tl1menu-env-preview-control<?= field_attrs('environment_display_style', $formId) ?>>
                    <option value="symbols" <?= selected($environmentDisplayStyle, 'symbols') ?>><?= e(__('plugins.tl1-menu.config.environment_display_styles.symbols')) ?></option>
                    <option value="values" <?= selected($environmentDisplayStyle, 'values') ?>><?= e(__('plugins.tl1-menu.config.environment_display_styles.values')) ?></option>
                </select>
                <?= field_error_html('environment_display_style', $formId) ?>
            </label>
            <?php foreach (['co2', 'water', 'animal_welfare', 'rainforest'] as $iconKey): ?>
                <label><?= e(__('plugins.tl1-menu.global_config.environment_icons.' . $iconKey)) ?>
                    <input type="text" name="plugin_global_settings[<?= e($plugin->getName()) ?>][environment_rating_icons][<?= e($iconKey) ?>]" value="<?= e((string)($environmentIcons[$iconKey] ?? '')) ?>">
                </label>
            <?php endforeach; ?>
            <?php
            $environmentPreviewModes = ['card', 'list'];
            $environmentPreviewLayout = 'all';
            $environmentPreviewShowModeLabels = true;
            require __DIR__ . '/partials/environment_preview.php';
            ?>
        </div>
    </fieldset>

    <div class="form-actions tl1menu-global-settings__mid-actions">
        <button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
        <a class="button button--normal" href="<?= e(url('/admin/plugins')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
    </div>

    <fieldset class="full-width tl1menu-setup">
        <legend><?= e(__('plugins.tl1-menu.setup.title')) ?></legend>
        <div class="tl1menu-setup__toolbar">
            <button type="button" class="button button--normal" data-tl1menu-setup-analyze><?= admin_icon('reload') ?><span><?= e(__('plugins.tl1-menu.setup.analyze')) ?></span></button>
        </div>
        <p class="muted"><?= e(__('plugins.tl1-menu.setup.help')) ?></p>
        <p class="muted"><?= e(__('plugins.tl1-menu.setup.save_scope_help')) ?></p>
        <div class="tl1menu-setup__status" data-tl1menu-setup-status role="status" aria-live="polite" aria-atomic="true"></div>
        <div class="tl1menu-setup__grid" data-tl1menu-setup-grid>
            <div data-tl1menu-setup-summary></div>
            <div class="tl1menu-setup__field-editor" data-tl1menu-setup-field-editor></div>
            <div class="tl1menu-setup__preview-panel" data-tl1menu-preview-panel>
                <div class="tl1menu-setup__preview-head">
                    <div class="tl1menu-setup__preview-tabs" role="tablist" aria-label="<?= e(__('plugins.tl1-menu.setup.preview_tabs_label')) ?>">
                        <button type="button" class="tl1menu-setup__preview-tab is-active" data-tl1menu-preview-tab="schema" role="tab" aria-selected="true"><?= e(__('plugins.tl1-menu.setup.tabs.schema')) ?></button>
                        <button type="button" class="tl1menu-setup__preview-tab" data-tl1menu-preview-tab="test" role="tab" aria-selected="false"><?= e(__('plugins.tl1-menu.setup.tabs.test')) ?></button>
                    </div>
                    <button type="button" class="tl1menu-setup__preview-reload" data-tl1menu-preview-reload title="<?= e(__('plugins.tl1-menu.setup.preview_actions.reload')) ?>" aria-label="<?= e(__('plugins.tl1-menu.setup.preview_actions.reload')) ?>"><?= admin_icon('reload') ?></button>
                </div>
                <pre class="tl1menu-setup__preview" data-tl1menu-setup-preview-output><?= e(json_encode($parserConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?></pre>
            </div>
            <div class="tl1menu-setup__editor" data-tl1menu-setup-editor></div>
        </div>
        <div class="tl1menu-setup__icon-upload" data-tl1menu-category-icon-upload>
            <label class="tl1menu-setup__icon-upload-field">
                <span><?= e(__('plugins.tl1-menu.setup.icon_upload.title')) ?></span>
                <input type="file" accept=".svg,.png,.webp,image/svg+xml,image/png,image/webp" data-tl1menu-category-icon-file>
            </label>
            <button type="button" class="button button--normal button--small" data-tl1menu-category-icon-upload-button><?= admin_icon('upload') ?><span><?= e(__('plugins.tl1-menu.setup.icon_upload.button')) ?></span></button>
            <small class="field-note tl1menu-setup__icon-upload-help"><?= e(__('plugins.tl1-menu.setup.icon_upload.help')) ?></small>
            <span class="tl1menu-setup__icon-upload-status" data-tl1menu-category-icon-upload-status role="status" aria-live="polite"></span>
        </div>

        <textarea class="tl1menu-setup__json" data-tl1menu-setup-json><?= e(json_encode($parserConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?></textarea>
        <div class="form-actions tl1menu-setup__footer-actions">
            <button type="button" class="button button--default" data-tl1menu-setup-save><?= admin_icon('save') ?><span><?= e(__('plugins.tl1-menu.setup.save_generated')) ?></span></button>
        </div>
    </fieldset>

    <dialog class="admin-dialog tl1menu-confirm-dialog" data-tl1menu-confirm-dialog aria-modal="true" aria-labelledby="tl1menu-confirm-title" aria-describedby="tl1menu-confirm-message">
        <div class="admin-dialog__panel tl1menu-confirm-dialog__panel" role="document">
            <h2 id="tl1menu-confirm-title" data-tl1menu-confirm-title></h2>
            <p class="muted tl1menu-confirm-dialog__message" id="tl1menu-confirm-message" data-tl1menu-confirm-message></p>
            <div class="form-actions">
                <button type="button" class="button button--normal" data-tl1menu-confirm-cancel><?= admin_icon('cancel') ?><span><?= e(__('plugins.tl1-menu.setup.dialog.cancel')) ?></span></button>
                <button type="button" class="button button--danger" data-tl1menu-confirm-accept><span data-tl1menu-confirm-accept-label><?= e(__('plugins.tl1-menu.setup.dialog.accept_save')) ?></span></button>
            </div>
        </div>
    </dialog>

    <dialog class="admin-dialog tl1menu-confirm-dialog tl1menu-value-dialog" data-tl1menu-value-dialog aria-modal="true" aria-labelledby="tl1menu-value-title">
        <div class="admin-dialog__panel tl1menu-confirm-dialog__panel" role="document">
            <h2 id="tl1menu-value-title" data-tl1menu-value-title></h2>
            <label class="tl1menu-value-dialog__field">
                <span data-tl1menu-value-label></span>
                <input class="tl1menu-value-dialog__input" type="text" autocomplete="off" data-tl1menu-value-input>
            </label>
            <div class="form-actions">
                <button type="button" class="button button--normal" data-tl1menu-value-cancel><?= admin_icon('cancel') ?><span><?= e(__('plugins.tl1-menu.setup.dialog.cancel')) ?></span></button>
                <button type="button" class="button button--default" data-tl1menu-value-accept><?= admin_icon('add') ?><span data-tl1menu-value-accept-label><?= e(__('common.create')) ?></span></button>
            </div>
        </div>
    </dialog>
</div>
<?php require __DIR__ . '/partials/admin_settings_script.php'; ?>
<script src="<?= e(plugin_asset_url($plugin->getName(), 'assets/tl1menu-setup.js')) ?>"></script>
