<?php $title = __('users.title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-head">
    <div>
        <h1><?= e(__('users.title')) ?></h1>
        <p class="muted"><?= e(__('users.intro')) ?></p>
    </div>
    <a class="button button--default" href="<?= e(url('/admin/users/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('users.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<div class="card">
    <table>
        <thead>
        <tr>
            <th><?= e(__('auth.username')) ?></th>
            <th><?= e(__('users.display_name')) ?></th>
            <th><?= e(__('users.role')) ?></th>
            <th><?= e(__('common.status')) ?></th>
            <th><?= e(__('users.created_at')) ?></th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= e($user['username']) ?></td>
                <td><?= e($user['display_name']) ?></td>
                <td><?= e(enum_label('roles', $user['role'], $user['role'])) ?></td>
                <td><?= e($user['is_active'] ? __('common.active') : __('common.inactive')) ?></td>
                <td><?= e((string)$user['created_at']) ?></td>
                <td class="actions">
                    <a class="button button--normal button--small" href="<?= e(url('/admin/users/' . $user['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                    <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm(<?= json_encode(__('users.delete_confirm')) ?>);">
                        <?= csrf_field() ?>
                        <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
