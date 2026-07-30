<?php
$title = __('locations.plural');
$breadcrumbs = [['label' => $title]];
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('/admin/locations/create?return_to=' . rawurlencode('/admin/locations'))) ?>"><?= admin_icon('add') ?><span><?= e(__('locations.add_new')) ?></span></a>
</div>

<?php if ($flash): ?><div class="alert alert-success success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger error"><?= e($error) ?></div><?php endif; ?>

<div class="organization-layout">
    <section class="organization-main">
        <div class="card shadow-sm">
            <div class="section-head">
                <div>
                    <h2><?= e(__('locations.configured')) ?></h2>
                    <p class="text-body-secondary muted"><?= e(__('locations.configured_hint')) ?></p>
                </div>
            </div>

            <?php if ($locations === []): ?>
                <p class="text-body-secondary muted"><?= e(__('locations.none')) ?></p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="table table-hover align-middle admin-table admin-table--locations" data-admin-table>
                        <thead>
                        <tr>
                            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
                            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="groups" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('display_groups.plural')])) ?>"><?= e(__('display_groups.plural')) ?></button></th>
                            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="displays" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('display.plural')])) ?>"><?= e(__('display.plural')) ?></button></th>
                            <th><?= e(__('common.actions')) ?></th>
                        </tr>
                        <tr class="slide-library-filter-row">
                            <th><input class="form-control" type="search" data-admin-filter="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
                            <th><input class="form-control" type="search" data-admin-filter="groups" aria-label="<?= e(__('slide.filter_column', ['column' => __('display_groups.plural')])) ?>" placeholder="<?= e(__('display_groups.plural')) ?>"></th>
                            <th><input class="form-control" type="search" data-admin-filter="displays" aria-label="<?= e(__('slide.filter_column', ['column' => __('display.plural')])) ?>" placeholder="<?= e(__('display.plural')) ?>"></th>
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
                                    <div class="admin-action-groups">
                                        <div class="btn-group btn-group-sm admin-action-group" role="group" aria-label="<?= e(__('common.actions') . ' ' . $location['name']) ?>">
                                    <a class="btn btn-primary" href="<?= e(url('/admin/locations/' . $location['id'] . '/edit')) ?>" aria-label="<?= e(__('common.edit') . ' ' . $location['name']) ?>" title="<?= e(__('common.edit')) ?>"><?= admin_icon('edit') ?></a>
                                    </div>
                                    <div class="btn-group btn-group-sm admin-action-group" role="group" aria-label="<?= e(__('common.delete')) ?>">
                                        <form method="post" action="<?= e(url('/admin/locations/' . $location['id'] . '/delete')) ?>" class="inline-form" data-dialog-submit data-dialog-title="<?= e(__('common.delete')) ?>" data-dialog-message="<?= e(__('locations.delete_confirm')) ?>" data-dialog-icon="trash" data-dialog-buttons="cancel,delete" data-dialog-accept="<?= e(__('common.delete')) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-danger" aria-label="<?= e(__('common.delete') . ' ' . $location['name']) ?>" title="<?= e(__('common.delete')) ?>"><?= admin_icon('delete') ?></button>
                                    </form>
                                    </div>
                                    </div>
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
        <div class="card shadow-sm">
            <h2><?= e(__('locations.unassigned')) ?></h2>
            <p class="text-body-secondary muted"><?= e(__('locations.unassigned_hint')) ?></p>
            <?php if ($unassignedDisplays === []): ?>
                <p class="text-body-secondary muted"><?= e(__('locations.unassigned_empty')) ?></p>
            <?php else: ?>
                <div class="unassigned-list">
                    <?php foreach ($unassignedDisplays as $display): ?>
                        <div class="display-action-row">
                            <a href="<?= e(url('/admin/displays/' . $display['id'] . '/edit')) ?>" class="unassigned-display unassigned-display--inline">
                                <span class="status-dot status-<?= e($display['monitoring_status']) ?>"></span>
                                <span class="display-list-copy">
                                    <strong><?= e($display['name']) ?></strong>
                                    <small><?= e(enum_label('orientations', $display['orientation'], $display['orientation'])) ?> &middot; <?= e($display['monitoring_label']) ?></small>
                                </span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
