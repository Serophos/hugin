<?php $title = __('settings.title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<h1><?= e($title) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <div class="section-head">
        <div>
            <h2><?= e(__('settings.heading')) ?></h2>
            <p class="muted"><?= e(__('settings.intro')) ?></p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/admin/settings')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label><?= e(__('settings.default_background_color')) ?>
            <span class="admin-color-picker admin-color-picker--compact" data-admin-color-picker data-color-format="hex" data-color-alpha="false" data-default-color="#0f172a" data-default-alpha="1">
                <input type="color" value="#0f172a" data-color-picker-swatch>
                <input type="hidden" name="settings[default_background_color]" value="<?= e((string)old('default_background_color', $settings['default_background_color'] ?? '#0f172a', 'settings')) ?>" data-color-value>
            </span>
            <?= field_error_html('default_background_color', 'settings') ?>
        </label>
        <label><?= e(__('settings.default_text_color')) ?>
            <span class="admin-color-picker admin-color-picker--compact" data-admin-color-picker data-color-format="hex" data-color-alpha="false" data-default-color="#f8fafc" data-default-alpha="1">
                <input type="color" value="#f8fafc" data-color-picker-swatch>
                <input type="hidden" name="settings[default_text_color]" value="<?= e((string)old('default_text_color', $settings['default_text_color'] ?? '#f8fafc', 'settings')) ?>" data-color-value>
            </span>
            <?= field_error_html('default_text_color', 'settings') ?>
        </label>
        <label><?= e(__('settings.default_font_heading')) ?>
            <select name="settings[default_font_heading]">
                <option value=""><?= e(__('settings.system_default')) ?></option>
                <?php foreach ($fonts as $fontFamily => $font): ?>
                    <option value="<?= e($fontFamily) ?>" <?= old_selected('default_font_heading', $fontFamily, $settings['default_font_heading'] ?? '', 'settings') ?>><?= e($font['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error_html('default_font_heading', 'settings') ?>
        </label>
        <label><?= e(__('settings.default_font_text')) ?>
            <select name="settings[default_font_text]">
                <option value=""><?= e(__('settings.system_default')) ?></option>
                <?php foreach ($fonts as $fontFamily => $font): ?>
                    <option value="<?= e($fontFamily) ?>" <?= old_selected('default_font_text', $fontFamily, $settings['default_font_text'] ?? '', 'settings') ?>><?= e($font['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error_html('default_font_text', 'settings') ?>
        </label>
        <?php if (!$fonts): ?>
            <div class="full-width alert warning"><?= e(__('settings.no_fonts_available')) ?></div>
        <?php else: ?>
            <p class="full-width muted small"><?= e(__('settings.font_directory_help')) ?></p>
        <?php endif; ?>
        <fieldset class="full-width">
            <legend><?= e(__('settings.accessibility_heading', [], 'Accessibility')) ?></legend>
            <p class="muted"><?= e(__('settings.accessibility_intro', [], 'Configure compliant admin accessibility preferences and deployment-specific WAD contact details.')) ?></p>
            <label><?= e(__('settings.accessibility_visual_mode', [], 'Admin visual mode')) ?>
                <select name="settings[accessibility_visual_mode]"<?= field_attrs('accessibility_visual_mode', 'settings') ?>>
                    <option value="default" <?= old_selected('accessibility_visual_mode', 'default', $settings['accessibility_visual_mode'] ?? 'default', 'settings') ?>><?= e(__('settings.accessibility_visual_default', [], 'Default compliant theme')) ?></option>
                    <option value="high_contrast" <?= old_selected('accessibility_visual_mode', 'high_contrast', $settings['accessibility_visual_mode'] ?? 'default', 'settings') ?>><?= e(__('settings.accessibility_visual_high_contrast', [], 'High contrast')) ?></option>
                    <option value="system" <?= old_selected('accessibility_visual_mode', 'system', $settings['accessibility_visual_mode'] ?? 'default', 'settings') ?>><?= e(__('settings.accessibility_visual_system', [], 'Follow system contrast preference')) ?></option>
                </select>
                <?= field_error_html('accessibility_visual_mode', 'settings') ?>
            </label>
            <label><?= e(__('settings.accessibility_focus_style', [], 'Focus indicator')) ?>
                <select name="settings[accessibility_focus_style]"<?= field_attrs('accessibility_focus_style', 'settings') ?>>
                    <option value="standard" <?= old_selected('accessibility_focus_style', 'standard', $settings['accessibility_focus_style'] ?? 'standard', 'settings') ?>><?= e(__('settings.accessibility_focus_standard', [], 'Standard visible focus')) ?></option>
                    <option value="strong" <?= old_selected('accessibility_focus_style', 'strong', $settings['accessibility_focus_style'] ?? 'standard', 'settings') ?>><?= e(__('settings.accessibility_focus_strong', [], 'Strong focus')) ?></option>
                </select>
                <?= field_error_html('accessibility_focus_style', 'settings') ?>
            </label>
            <label><?= e(__('settings.accessibility_motion', [], 'Motion')) ?>
                <select name="settings[accessibility_motion]"<?= field_attrs('accessibility_motion', 'settings') ?>>
                    <option value="system" <?= old_selected('accessibility_motion', 'system', $settings['accessibility_motion'] ?? 'system', 'settings') ?>><?= e(__('settings.accessibility_motion_system', [], 'Respect system preference')) ?></option>
                    <option value="reduced" <?= old_selected('accessibility_motion', 'reduced', $settings['accessibility_motion'] ?? 'system', 'settings') ?>><?= e(__('settings.accessibility_motion_reduced', [], 'Always reduce nonessential motion')) ?></option>
                </select>
                <?= field_error_html('accessibility_motion', 'settings') ?>
            </label>
            <label><?= e(__('settings.accessibility_contact_email', [], 'Accessibility contact email')) ?>
                <input type="email" name="settings[accessibility_contact_email]" value="<?= e((string)old('accessibility_contact_email', $settings['accessibility_contact_email'] ?? '', 'settings')) ?>" placeholder="accessibility@example.org"<?= field_attrs('accessibility_contact_email', 'settings') ?>>
                <?= field_error_html('accessibility_contact_email', 'settings') ?>
            </label>
            <label><?= e(__('settings.accessibility_feedback_url', [], 'Accessibility feedback URL')) ?>
                <input type="url" name="settings[accessibility_feedback_url]" value="<?= e((string)old('accessibility_feedback_url', $settings['accessibility_feedback_url'] ?? '', 'settings')) ?>" placeholder="https://example.org/accessibility-feedback"<?= field_attrs('accessibility_feedback_url', 'settings') ?>>
                <?= field_error_html('accessibility_feedback_url', 'settings') ?>
            </label>
            <label><?= e(__('settings.accessibility_enforcement_url', [], 'Enforcement body URL')) ?>
                <input type="url" name="settings[accessibility_enforcement_url]" value="<?= e((string)old('accessibility_enforcement_url', $settings['accessibility_enforcement_url'] ?? '', 'settings')) ?>" placeholder="https://example.org/enforcement"<?= field_attrs('accessibility_enforcement_url', 'settings') ?>>
                <?= field_error_html('accessibility_enforcement_url', 'settings') ?>
            </label>
        </fieldset>
        <div class="form-actions">
            <button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
            <a class="button button--normal" href="<?= e(url('/admin')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
