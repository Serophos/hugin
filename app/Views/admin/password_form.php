<?php
$formId = 'password';
$title = __('auth.change_password_title');
$breadcrumbs = [
    ['label' => __('nav.account')],
    ['label' => $title],
];
require __DIR__ . '/../layouts/admin_header.php';
?>
<?php if ($flash): ?><div class="alert alert-success success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger error"><?= e($error) ?></div><?php endif; ?>
<div class="card shadow-sm">
    <form method="post" action="<?= e(url('/admin/account/password')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label><?= e(__('auth.current_password')) ?>
            <input class="form-control" type="password" name="current_password" autocomplete="current-password" required<?= field_attrs('current_password', $formId) ?>>
            <?= field_error_html('current_password', $formId) ?>
        </label>
        <label><?= e(__('auth.new_password')) ?>
            <input class="form-control" type="password" name="password" autocomplete="new-password" required<?= field_attrs('password', $formId) ?>>
            <?= field_error_html('password', $formId) ?>
        </label>
        <label><?= e(__('auth.password_confirmation')) ?>
            <input class="form-control" type="password" name="password_confirmation" autocomplete="new-password" required<?= field_attrs('password_confirmation', $formId) ?>>
            <?= field_error_html('password_confirmation', $formId) ?>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
            <a class="btn btn-outline-secondary" href="<?= e(url('/admin')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
