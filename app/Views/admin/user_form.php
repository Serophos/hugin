<?php $title = $user ? __('users.edit_title') : __('users.create_title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<h1><?= e($title) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <form method="post" action="<?= e($user ? url('/admin/users/' . $user['id'] . '/edit') : url('/admin/users/create')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label><?= e(__('auth.username')) ?>
            <input type="text" name="username" value="<?= e($user['username'] ?? '') ?>" required>
        </label>
        <label><?= e(__('users.display_name')) ?>
            <input type="text" name="display_name" value="<?= e($user['display_name'] ?? '') ?>">
        </label>
        <label><?= e(__('users.role')) ?>
            <select name="role" required>
                <option value="admin" <?= selected($user['role'] ?? '', 'admin') ?>><?= e(enum_label('roles', 'admin')) ?></option>
                <option value="editor" <?= selected($user['role'] ?? 'editor', 'editor') ?>><?= e(enum_label('roles', 'editor')) ?></option>
            </select>
        </label>
        <label><?= e(__('auth.password')) ?> <?= $user ? '<span class="muted">' . e(__('auth.password_hint_keep')) . '</span>' : '' ?>
            <input type="password" name="password" <?= $user ? '' : 'required' ?>>
        </label>
        <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= checked($user['is_active'] ?? 1) ?>> <?= e(__('common.active')) ?></label>
        <div class="form-actions">
            <button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
            <a class="button button--normal" href="<?= e(url('/admin/users')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
