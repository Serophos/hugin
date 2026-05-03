<?php
$locationEditForm = 'location_edit';
$groupCreateForm = 'display_group_create';
$title = __('common.edit') . ': ' . $location['name'];
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-head">
    <div>
        <h1><?= e($location['name']) ?></h1>
        <p class="muted"><?= e(__('locations.counts', ['groups' => (int)$location['group_count'], 'displays' => (int)$location['display_count']])) ?></p>
    </div>
    <a class="button button--normal" href="<?= e(url('/admin/locations')) ?>"><?= admin_icon('back') ?><span><?= e(__('locations.plural')) ?></span></a>
</div>

<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="organization-layout">
    <section class="organization-main">
        <div class="card">
            <h2><?= e(__('locations.singular')) ?></h2>
            <form method="post" action="<?= e(url('/admin/locations/' . $location['id'] . '/edit')) ?>" class="form-grid compact-grid">
                <?= csrf_field() ?>
                <label><?= e(__('common.name')) ?>
                    <input name="name" value="<?= e((string)old('name', $location['name'], $locationEditForm)) ?>" placeholder="<?= e(__('locations.name_placeholder')) ?>" required<?= field_attrs('name', $locationEditForm) ?>>
                    <?= field_error_html('name', $locationEditForm) ?>
                </label>
                <label><?= e(__('locations.address')) ?>
                    <input name="address" value="<?= e((string)old('address', $location['address'] ?? '', $locationEditForm)) ?>" placeholder="<?= e(__('locations.address_placeholder')) ?>"<?= field_attrs('address', $locationEditForm) ?>>
                    <?= field_error_html('address', $locationEditForm) ?>
                </label>
                <label><?= e(__('common.sort_order')) ?>
                    <input type="number" name="sort_order" value="<?= e((string)old('sort_order', $location['sort_order'], $locationEditForm)) ?>" min="0" placeholder="<?= e(__('locations.sort_order_placeholder')) ?>"<?= field_attrs('sort_order', $locationEditForm) ?>>
                    <?= field_error_html('sort_order', $locationEditForm) ?>
                </label>
                <label class="full-width"><?= e(__('common.description')) ?>
                    <textarea name="description" rows="3" placeholder="<?= e(__('locations.description_placeholder')) ?>"<?= field_attrs('description', $locationEditForm) ?>><?= e((string)old('description', $location['description'] ?? '', $locationEditForm)) ?></textarea>
                    <?= field_error_html('description', $locationEditForm) ?>
                </label>
                <div class="form-actions full-width">
                    <button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
                    <a class="button button--normal" href="<?= e(url('/admin/locations')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="section-head">
                <div>
                    <h2><?= e(__('display_groups.plural')) ?></h2>
                    <p class="muted"><?= e(__('display_groups.arrangement_hint')) ?></p>
                </div>
            </div>

            <?php if ($groups === []): ?>
                <p class="muted"><?= e(__('display_groups.none')) ?></p>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead>
                        <tr>
                            <th><?= e(__('common.name')) ?></th>
                            <th><?= e(__('display.plural')) ?></th>
                            <th><?= e(__('common.description')) ?></th>
                            <th><?= e(__('common.actions')) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($groups as $group): ?>
                            <tr>
                                <td><strong><?= e($group['name']) ?></strong></td>
                                <td><?= e((string)$group['display_count']) ?></td>
                                <td><?= e($group['description'] ?: __('common.none')) ?></td>
                                <td class="actions">
                                    <a class="button button--normal button--small" href="<?= e(url('/admin/display-groups/' . $group['id'])) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                                    <form method="post" action="<?= e(url('/admin/display-groups/' . $group['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm(<?= e(json_encode(__('display_groups.delete_confirm'))) ?>);">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to" value="<?= e('/admin/locations/' . $location['id'] . '/edit') ?>">
                                        <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <aside class="organization-side">
        <div class="card">
            <h2><?= e(__('display_groups.new')) ?></h2>
            <form method="post" action="<?= e(url('/admin/display-groups/create')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="location_id" value="<?= e((string)$location['id']) ?>">
                <input type="hidden" name="return_to" value="<?= e('/admin/locations/' . $location['id'] . '/edit') ?>">
                <label><?= e(__('common.name')) ?>
                    <input name="name" value="<?= e((string)old('name', '', $groupCreateForm)) ?>" placeholder="<?= e(__('display_groups.name_placeholder')) ?>" required<?= field_attrs('name', $groupCreateForm) ?>>
                    <?= field_error_html('name', $groupCreateForm) ?>
                </label>
                <label><?= e(__('common.description')) ?>
                    <textarea name="description" rows="3" placeholder="<?= e(__('display_groups.description_placeholder')) ?>"<?= field_attrs('description', $groupCreateForm) ?>><?= e((string)old('description', '', $groupCreateForm)) ?></textarea>
                    <?= field_error_html('description', $groupCreateForm) ?>
                </label>
                <label><?= e(__('common.sort_order')) ?>
                    <input type="number" name="sort_order" value="<?= e((string)old('sort_order', '0', $groupCreateForm)) ?>" min="0" placeholder="<?= e(__('display_groups.sort_order_placeholder')) ?>"<?= field_attrs('sort_order', $groupCreateForm) ?>>
                    <?= field_error_html('sort_order', $groupCreateForm) ?>
                </label>
                <button type="submit" class="button button--default"><?= admin_icon('add') ?><span><?= e(__('common.create')) ?></span></button>
            </form>
        </div>

        <div class="card">
            <h2><?= e(__('locations.unassigned')) ?></h2>
            <p class="muted"><?= e(__('locations.unassigned_hint')) ?></p>
            <?php if ($unassignedDisplays === []): ?>
                <p class="muted"><?= e(__('locations.unassigned_empty')) ?></p>
            <?php else: ?>
                <div class="unassigned-list">
                    <?php foreach ($unassignedDisplays as $display): ?>
                        <a href="<?= e(url('/admin/displays/' . $display['id'] . '/edit')) ?>" class="unassigned-display">
                            <span class="status-dot status-<?= e($display['monitoring_status']) ?>"></span>
                            <span>
                                <strong><?= e($display['name']) ?></strong>
                                <small><?= e(enum_label('orientations', $display['orientation'], $display['orientation'])) ?> &middot; <?= e($display['monitoring_label']) ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
