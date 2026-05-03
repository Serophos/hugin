<?php $title = __('auth.login_title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="card login-card">
    <h1><?= e(__('app.name', [], 'Hugin')) ?></h1>
    <p class="muted"><?= e(__('app.tagline')) ?></p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('/admin/login')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label><?= e(__('auth.username')) ?>
            <input type="text" name="username" value="<?= e((string)old('username', '', 'login')) ?>" placeholder="<?= e(__('auth.username_placeholder')) ?>" required autofocus<?= field_attrs('username', 'login') ?>>
            <?= field_error_html('username', 'login') ?>
        </label>
        <label><?= e(__('auth.password')) ?>
            <input type="password" name="password" placeholder="<?= e(__('auth.password_placeholder')) ?>" required<?= field_attrs('password', 'login') ?>>
            <?= field_error_html('password', 'login') ?>
        </label>
        <button type="submit" class="button button--default"><?= admin_icon('login') ?><span><?= e(__('common.login')) ?></span></button>
    </form>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
