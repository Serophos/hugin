<?php
$formId = 'user';
$isOpenId = ($user['auth_provider'] ?? 'local') === 'openid';
$title = $user ? __('users.edit_title') : __('users.create_title');
$userBreadcrumbLabel = $user ? (string)($user['display_name'] ?: $user['username']) : __('users.create_title');
$breadcrumbs = [['label' => __('users.title'), 'url' => '/admin/users'], ['label' => $userBreadcrumbLabel]];
require __DIR__ . '/../layouts/admin_header.php';
?>
<?php if ($error): ?><div class="alert alert-danger error"><?= e($error) ?></div><?php endif; ?>
<div class="card shadow-sm">
    <form method="post" action="<?= e($user ? url('/admin/users/' . $user['id'] . '/edit') : url('/admin/users/create')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <?php if ($isOpenId): ?>
            <div class="alert alert-info"><?= e(__('openid.synced_user_notice')) ?></div>
            <label><?= e(__('openid.provider')) ?><input class="form-control" readonly value="OpenID Connect"></label>
            <label><?= e(__('auth.username')) ?><input class="form-control" readonly value="<?= e((string)$user['username']) ?>"></label>
            <label><?= e(__('users.display_name')) ?><input class="form-control" readonly value="<?= e((string)$user['display_name']) ?>"></label>
            <label><?= e(__('openid.first_name')) ?><input class="form-control" readonly value="<?= e((string)$user['first_name']) ?>"></label>
            <label><?= e(__('openid.last_name')) ?><input class="form-control" readonly value="<?= e((string)$user['last_name']) ?>"></label>
            <label><?= e(__('openid.department')) ?><input class="form-control" readonly value="<?= e((string)$user['department']) ?>"></label>
            <label><?= e(__('openid.job_title')) ?><input class="form-control" readonly value="<?= e((string)$user['title']) ?>"></label>
            <label><?= e(__('openid.picture')) ?><input class="form-control" readonly value="<?= e((string)$user['picture_url']) ?>"></label>
            <label><?= e(__('users.role')) ?><input class="form-control" readonly value="<?= e(enum_label('roles', (string)$user['role'])) ?>"></label>
            <label><?= e(__('openid.issuer_url')) ?><input class="form-control" readonly value="<?= e((string)$user['oidc_issuer']) ?>"></label>
            <label><?= e(__('openid.subject')) ?><input class="form-control" readonly value="<?= e((string)$user['oidc_subject']) ?>"></label>
        <?php else: ?>
            <label><?= e(__('auth.username')) ?>
                <input class="form-control" type="text" name="username" value="<?= e((string)old('username', $user['username'] ?? '', $formId)) ?>" required<?= field_attrs('username', $formId) ?>>
                <?= field_error_html('username', $formId) ?>
            </label>
            <label><?= e(__('users.display_name')) ?>
                <input class="form-control" type="text" name="display_name" value="<?= e((string)old('display_name', $user['display_name'] ?? '', $formId)) ?>">
            </label>
            <label><?= e(__('users.role')) ?>
                <select class="form-select" name="role" required>
                    <option value="admin" <?= old_selected('role', 'admin', $user['role'] ?? '', $formId) ?>><?= e(enum_label('roles', 'admin')) ?></option>
                    <option value="editor" <?= old_selected('role', 'editor', $user['role'] ?? 'editor', $formId) ?>><?= e(enum_label('roles', 'editor')) ?></option>
                </select>
            </label>
            <label><?= e(__('auth.password')) ?>
                <input class="form-control" type="password" name="password" <?= $user ? '' : 'required' ?> autocomplete="new-password">
                <?= field_error_html('password', $formId) ?>
            </label>
        <?php endif; ?>
        <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= old_checked('is_active', $user['is_active'] ?? 1, $formId) ?>> <?= e(__('common.active')) ?></label>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
            <a class="btn btn-secondary" href="<?= e(url('/admin/users')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
