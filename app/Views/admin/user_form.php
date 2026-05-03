<?php
$formId = 'user';
$title = $user ? __('users.edit_title') : __('users.create_title');
require __DIR__ . '/../layouts/admin_header.php';
?>
<h1><?= e($title) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <form method="post" action="<?= e($user ? url('/admin/users/' . $user['id'] . '/edit') : url('/admin/users/create')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label><?= e(__('auth.username')) ?>
            <input type="text" name="username" value="<?= e((string)old('username', $user['username'] ?? '', $formId)) ?>" placeholder="<?= e(__('users.username_placeholder')) ?>" required<?= field_attrs('username', $formId) ?>>
            <?= field_error_html('username', $formId) ?>
        </label>
        <label><?= e(__('users.display_name')) ?>
            <input type="text" name="display_name" value="<?= e((string)old('display_name', $user['display_name'] ?? '', $formId)) ?>" placeholder="<?= e(__('users.display_name_placeholder')) ?>"<?= field_attrs('display_name', $formId) ?>>
            <?= field_error_html('display_name', $formId) ?>
        </label>
        <label><?= e(__('users.role')) ?>
            <select name="role" required<?= field_attrs('role', $formId) ?>>
                <option value="admin" <?= old_selected('role', 'admin', $user['role'] ?? '', $formId) ?>><?= e(enum_label('roles', 'admin')) ?></option>
                <option value="editor" <?= old_selected('role', 'editor', $user['role'] ?? 'editor', $formId) ?>><?= e(enum_label('roles', 'editor')) ?></option>
            </select>
            <?= field_error_html('role', $formId) ?>
        </label>
        <label><?= e(__('auth.password')) ?> <?= $user ? '<span class="muted">' . e(__('auth.password_hint_keep')) . '</span>' : '' ?>
            <input type="password" name="password" placeholder="<?= e($user ? __('users.password_placeholder_edit') : __('users.password_placeholder_new')) ?>" <?= $user ? '' : 'required' ?><?= field_attrs('password', $formId) ?>>
            <?= field_error_html('password', $formId) ?>
        </label>
        <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= old_checked('is_active', $user['is_active'] ?? 1, $formId) ?>> <?= e(__('common.active')) ?></label>
        <div class="form-actions">
            <button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
            <a class="button button--normal" href="<?= e(url('/admin/users')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
