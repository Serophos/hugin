<?php $title = __('users.title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-actions">
    <a class="button button--default" href="<?= e(url('/admin/users/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('users.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<div class="card">
    <div class="table-scroll">
    <table class="admin-table admin-table--users" data-admin-table>
        <thead>
        <tr>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="username" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('auth.username')])) ?>"><?= e(__('auth.username')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="display_name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('users.display_name')])) ?>"><?= e(__('users.display_name')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="role" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('users.role')])) ?>"><?= e(__('users.role')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="status" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.status')])) ?>"><?= e(__('common.status')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="created_at" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('users.created_at')])) ?>"><?= e(__('users.created_at')) ?></button></th>
            <th><?= e(__('common.actions')) ?></th>
        </tr>
        <tr class="slide-library-filter-row">
            <th><input type="search" data-admin-filter="username" aria-label="<?= e(__('slide.filter_column', ['column' => __('auth.username')])) ?>" placeholder="<?= e(__('auth.username')) ?>"></th>
            <th><input type="search" data-admin-filter="display_name" aria-label="<?= e(__('slide.filter_column', ['column' => __('users.display_name')])) ?>" placeholder="<?= e(__('users.display_name')) ?>"></th>
            <th><input type="search" data-admin-filter="role" aria-label="<?= e(__('slide.filter_column', ['column' => __('users.role')])) ?>" placeholder="<?= e(__('users.role')) ?>"></th>
            <th>
                <select data-admin-filter="status" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.status')])) ?>">
                    <option value=""><?= e(__('slide.filter_all_statuses')) ?></option>
                    <option value="active"><?= e(__('common.active')) ?></option>
                    <option value="inactive"><?= e(__('common.inactive')) ?></option>
                </select>
            </th>
            <th><input type="search" data-admin-filter="created_at" aria-label="<?= e(__('slide.filter_column', ['column' => __('users.created_at')])) ?>" placeholder="<?= e(__('users.created_at')) ?>"></th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <?php
            $roleLabel = enum_label('roles', $user['role'], $user['role']);
            $statusValue = $user['is_active'] ? 'active' : 'inactive';
            $statusLabel = $user['is_active'] ? __('common.active') : __('common.inactive');
            ?>
            <tr data-admin-row>
                <td data-admin-cell="username" data-sort-value="<?= e((string)$user['username']) ?>" data-filter-value="<?= e((string)$user['username']) ?>"><?= e($user['username']) ?></td>
                <td data-admin-cell="display_name" data-sort-value="<?= e((string)$user['display_name']) ?>" data-filter-value="<?= e((string)$user['display_name']) ?>"><?= e($user['display_name']) ?></td>
                <td data-admin-cell="role" data-sort-value="<?= e($roleLabel) ?>" data-filter-value="<?= e($roleLabel) ?>"><?= e($roleLabel) ?></td>
                <td data-admin-cell="status" data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
                <td data-admin-cell="created_at" data-sort-value="<?= e((string)$user['created_at']) ?>" data-filter-value="<?= e((string)$user['created_at']) ?>"><?= e((string)$user['created_at']) ?></td>
                <td class="actions">
                    <a class="button button--normal button--small" href="<?= e(url('/admin/users/' . $user['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                    <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/delete')) ?>" class="inline-form" data-confirm-submit data-confirm-title="<?= e(__('common.delete')) ?>" data-confirm-message="<?= e(__('users.delete_confirm')) ?>" data-confirm-accept="<?= e(__('common.delete')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
