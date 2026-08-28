<?php
$title = __('auth.login_title');
$breadcrumbs = [['label' => $title]];
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="login-logo">
    <img src="<?= e(url('/assets/img/hugin-logo.webp')) ?>" alt="<?= e(__('app.name', [], 'Hugin')) ?>">
</div>
<div class="card login-card">
    <div class="card-body login-card-body">
        <p class="login-box-msg"><?= e(__('auth.login_prompt', [], 'Log in to Hugin to manage your displays.')) ?></p>
        <?php if ($error): ?><div class="alert alert-danger error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="<?= e(url('/admin/login/local')) ?>">
            <?= csrf_field() ?>
            <label class="visually-hidden" for="login-username"><?= e(__('auth.username')) ?></label>
            <div class="input-group mb-3">
                <input id="login-username" class="form-control" type="text" name="username" value="<?= e((string)old('username', '', 'login')) ?>" placeholder="<?= e(__('auth.username')) ?>" autocomplete="username" required autofocus<?= field_attrs('username', 'login') ?>>
                <span class="input-group-text" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-5.33 0-8 2.67-8 6v2h16v-2c0-3.33-2.67-6-8-6Z"/></svg>
                </span>
            </div>
            <?= field_error_html('username', 'login') ?>
            <label class="visually-hidden" for="login-password"><?= e(__('auth.password')) ?></label>
            <div class="input-group mb-3">
                <input id="login-password" class="form-control" type="password" name="password" placeholder="<?= e(__('auth.password')) ?>" autocomplete="current-password" required<?= field_attrs('password', 'login') ?>>
                <span class="input-group-text" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><path d="M17 8h-1V6a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2Zm-7-2a2 2 0 0 1 4 0v2h-4V6Zm3 10.73V19h-2v-2.27a2 2 0 1 1 2 0Z"/></svg>
                </span>
            </div>
            <?= field_error_html('password', 'login') ?>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary"><?= e(__('common.login')) ?></button>
            </div>
            <?php if (!empty($openidAvailable)): ?><a class="btn btn-outline-secondary mt-3" href="<?= e(url('/admin/oidc/start')) ?>"><?= e(__('openid.sign_in')) ?></a><?php endif; ?>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
