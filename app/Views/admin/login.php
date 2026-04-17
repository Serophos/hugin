<?php $title = __('auth.login_title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="card login-card">
    <h1><?= e(__('app.name', [], 'Hugin')) ?></h1>
    <p class="muted"><?= e(__('app.tagline')) ?></p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('/admin/login')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label><?= e(__('auth.username')) ?>
            <input type="text" name="username" required autofocus>
        </label>
        <label><?= e(__('auth.password')) ?>
            <input type="password" name="password" required>
        </label>
        <button type="submit"><?= e(__('common.login')) ?></button>
    </form>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
