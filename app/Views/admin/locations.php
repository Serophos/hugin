<?php
$locationCreateForm = 'location_create';
$title = __('locations.plural');
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-actions">
    <a class="button button--normal" href="<?= e(url('/admin/displays')) ?>"><?= admin_icon('back') ?><span><?= e(__('display.plural')) ?></span></a>
</div>

<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="organization-layout">
    <section class="organization-main">
        <div class="card">
            <div class="section-head">
                <div>
                    <h2><?= e(__('locations.configured')) ?></h2>
                    <p class="muted"><?= e(__('locations.configured_hint')) ?></p>
                </div>
            </div>

            <?php if ($locations === []): ?>
                <p class="muted"><?= e(__('locations.none')) ?></p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="admin-table admin-table--locations" data-admin-table>
                        <thead>
                        <tr>
                            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
                            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="groups" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('display_groups.plural')])) ?>"><?= e(__('display_groups.plural')) ?></button></th>
                            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="displays" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('display.plural')])) ?>"><?= e(__('display.plural')) ?></button></th>
                            <th><?= e(__('common.actions')) ?></th>
                        </tr>
                        <tr class="slide-library-filter-row">
                            <th><input type="search" data-admin-filter="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
                            <th><input type="search" data-admin-filter="groups" aria-label="<?= e(__('slide.filter_column', ['column' => __('display_groups.plural')])) ?>" placeholder="<?= e(__('display_groups.plural')) ?>"></th>
                            <th><input type="search" data-admin-filter="displays" aria-label="<?= e(__('slide.filter_column', ['column' => __('display.plural')])) ?>" placeholder="<?= e(__('display.plural')) ?>"></th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($locations as $location): ?>
                            <tr data-admin-row>
                                <td data-admin-cell="name" data-sort-value="<?= e((string)$location['name']) ?>" data-filter-value="<?= e((string)$location['name']) ?>"><strong><?= e($location['name']) ?></strong></td>
                                <td data-admin-cell="groups" data-sort-value="<?= e((string)$location['group_count']) ?>" data-filter-value="<?= e((string)$location['group_count']) ?>"><?= e((string)$location['group_count']) ?></td>
                                <td data-admin-cell="displays" data-sort-value="<?= e((string)$location['display_count']) ?>" data-filter-value="<?= e((string)$location['display_count']) ?>"><?= e((string)$location['display_count']) ?></td>
                                <td class="actions">
                                    <a class="button button--normal button--small" href="<?= e(url('/admin/locations/' . $location['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                                    <form method="post" action="<?= e(url('/admin/locations/' . $location['id'] . '/delete')) ?>" class="inline-form" data-dialog-submit data-dialog-title="<?= e(__('common.delete')) ?>" data-dialog-message="<?= e(__('locations.delete_confirm')) ?>" data-dialog-icon="trash" data-dialog-buttons="cancel,delete" data-dialog-accept="<?= e(__('common.delete')) ?>">
                                        <?= csrf_field() ?>
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
            <h2><?= e(__('locations.new')) ?></h2>
            <form method="post" action="<?= e(url('/admin/locations/create')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label><?= e(__('common.name')) ?>
                    <input name="name" value="<?= e((string)old('name', '', $locationCreateForm)) ?>" placeholder="<?= e(__('locations.name_placeholder')) ?>" required<?= field_attrs('name', $locationCreateForm) ?>>
                    <?= field_error_html('name', $locationCreateForm) ?>
                </label>
                <label><?= e(__('locations.address')) ?>
                    <input name="address" value="<?= e((string)old('address', '', $locationCreateForm)) ?>" placeholder="<?= e(__('locations.address_placeholder')) ?>"<?= field_attrs('address', $locationCreateForm) ?>>
                    <?= field_error_html('address', $locationCreateForm) ?>
                </label>
                <label><?= e(__('common.description')) ?>
                    <textarea name="description" rows="3" placeholder="<?= e(__('locations.description_placeholder')) ?>"<?= field_attrs('description', $locationCreateForm) ?>><?= e((string)old('description', '', $locationCreateForm)) ?></textarea>
                    <?= field_error_html('description', $locationCreateForm) ?>
                </label>
                <label><?= e(__('common.sort_order')) ?>
                    <input type="number" name="sort_order" value="<?= e((string)old('sort_order', '0', $locationCreateForm)) ?>" min="0" placeholder="<?= e(__('locations.sort_order_placeholder')) ?>"<?= field_attrs('sort_order', $locationCreateForm) ?>>
                    <?= field_error_html('sort_order', $locationCreateForm) ?>
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
