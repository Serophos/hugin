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
            <input type="color" name="settings[default_background_color]" value="<?= e((string)old('default_background_color', $settings['default_background_color'] ?? '#0f172a', 'settings')) ?>">
            <?= field_error_html('default_background_color', 'settings') ?>
        </label>
        <label><?= e(__('settings.default_text_color')) ?>
            <input type="color" name="settings[default_text_color]" value="<?= e((string)old('default_text_color', $settings['default_text_color'] ?? '#f8fafc', 'settings')) ?>">
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
        <div class="form-actions">
            <button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
            <a class="button button--normal" href="<?= e(url('/admin')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
