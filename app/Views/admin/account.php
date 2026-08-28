<?php
$title = __('common.my_account', [], 'My account');
$breadcrumbs = [['label' => $title]];
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="card shadow-sm account-profile-card">
    <div class="card-body">
        <h2 class="h5"><?= e(current_user_name()) ?></h2>
        <dl class="row mb-0">
            <dt class="col-sm-3"><?= e(__('auth.username')) ?></dt><dd class="col-sm-9"><?= e((string)($user['username'] ?? '')) ?></dd>
            <dt class="col-sm-3"><?= e(__('openid.first_name')) ?></dt><dd class="col-sm-9"><?= e((string)($user['first_name'] ?? '')) ?></dd>
            <dt class="col-sm-3"><?= e(__('openid.last_name')) ?></dt><dd class="col-sm-9"><?= e((string)($user['last_name'] ?? '')) ?></dd>
            <dt class="col-sm-3"><?= e(__('openid.department')) ?></dt><dd class="col-sm-9"><?= e((string)($user['department'] ?? '')) ?></dd>
            <dt class="col-sm-3"><?= e(__('openid.job_title')) ?></dt><dd class="col-sm-9"><?= e((string)($user['title'] ?? '')) ?></dd>
            <dt class="col-sm-3"><?= e(__('users.role')) ?></dt><dd class="col-sm-9"><?= e(current_user_role_label()) ?></dd>
        </dl>
        <?php if (($user['auth_provider'] ?? 'local') === 'local'): ?>
            <a class="btn btn-primary mt-3" href="<?= e(url('/admin/account/password')) ?>"><?= e(__('auth.change_password_title')) ?></a>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
