<?php $title = __('display.plural'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-actions">
    <a class="button button--normal" href="<?= e(url('/admin/locations')) ?>"><?= admin_icon('manage') ?><span><?= e(__('locations.manage')) ?></span></a>
    <a class="button button--default" href="<?= e(url('/admin/displays/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('display.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<div class="card">
    <div class="table-scroll">
    <table class="admin-table admin-table--displays" data-admin-table>
        <thead>
        <tr>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="url" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('display.url_label')])) ?>"><?= e(__('display.url_label')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="location" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('locations.singular')])) ?>"><?= e(__('locations.singular')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="group" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('display_groups.singular')])) ?>"><?= e(__('display_groups.singular')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="channels" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('display.channels_count')])) ?>"><?= e(__('display.channels_count')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="status" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.status')])) ?>"><?= e(__('common.status')) ?></button></th>
            <th><?= e(__('common.actions')) ?></th>
        </tr>
        <tr class="slide-library-filter-row">
            <th><input type="search" data-admin-filter="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
            <th><input type="search" data-admin-filter="url" aria-label="<?= e(__('slide.filter_column', ['column' => __('display.url_label')])) ?>" placeholder="<?= e(__('display.url_label')) ?>"></th>
            <th><input type="search" data-admin-filter="location" aria-label="<?= e(__('slide.filter_column', ['column' => __('locations.singular')])) ?>" placeholder="<?= e(__('locations.singular')) ?>"></th>
            <th><input type="search" data-admin-filter="group" aria-label="<?= e(__('slide.filter_column', ['column' => __('display_groups.singular')])) ?>" placeholder="<?= e(__('display_groups.singular')) ?>"></th>
            <th><input type="search" data-admin-filter="channels" aria-label="<?= e(__('slide.filter_column', ['column' => __('display.channels_count')])) ?>" placeholder="<?= e(__('display.channels_count')) ?>"></th>
            <th>
                <select data-admin-filter="status" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.status')])) ?>">
                    <option value=""><?= e(__('slide.filter_all_statuses')) ?></option>
                    <option value="active"><?= e(__('common.active')) ?></option>
                    <option value="inactive"><?= e(__('common.inactive')) ?></option>
                </select>
            </th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($displays as $display): ?>
            <?php
            $displayUrl = '/display/' . $display['slug'];
            $locationLabel = $display['location_name'] ?: __('locations.unassigned');
            $groupLabel = $display['group_name'] ?: __('locations.unassigned');
            $statusValue = $display['is_active'] ? 'active' : 'inactive';
            $statusLabel = $display['is_active'] ? __('common.active') : __('common.inactive');
            ?>
            <tr data-admin-row>
                <td data-admin-cell="name" data-sort-value="<?= e((string)$display['name']) ?>" data-filter-value="<?= e((string)$display['name']) ?>"><?= e($display['name']) ?></td>
                <td data-admin-cell="url" data-sort-value="<?= e($displayUrl) ?>" data-filter-value="<?= e($displayUrl) ?>"><a href="<?= e(url($displayUrl)) ?>" target="_blank"><?= e($displayUrl) ?></a></td>
                <td data-admin-cell="location" data-sort-value="<?= e($locationLabel) ?>" data-filter-value="<?= e($locationLabel) ?>"><?= e($locationLabel) ?></td>
                <td data-admin-cell="group" data-sort-value="<?= e($groupLabel) ?>" data-filter-value="<?= e($groupLabel) ?>"><?= e($groupLabel) ?></td>
                <td data-admin-cell="channels" data-sort-value="<?= e((string)$display['channel_count']) ?>" data-filter-value="<?= e((string)$display['channel_count']) ?>"><?= e((string)$display['channel_count']) ?></td>
                <td data-admin-cell="status" data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
                <td class="actions">
                    <a class="button button--normal button--small" href="<?= e(url($displayUrl)) ?>" target="_blank" rel="noopener noreferrer"><?= admin_icon('preview') ?><span><?= e(__('common.preview')) ?></span></a>
                    <a class="button button--normal button--small" href="<?= e(url('/admin/displays/' . $display['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                    <form method="post" action="<?= e(url('/admin/displays/' . $display['id'] . '/reload')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="/admin/displays">
                        <button type="submit" class="button button--normal button--small" aria-label="<?= e(__('display.reload_slideshow')) ?>"><?= admin_icon('reload') ?><span><?= e(__('common.reload')) ?></span></button>
                    </form>
                    <form method="post" action="<?= e(url('/admin/displays/' . $display['id'] . '/delete')) ?>" class="inline-form" data-dialog-submit data-dialog-title="<?= e(__('common.delete')) ?>" data-dialog-message="<?= e(__('display.delete_confirm', [], 'Delete display?')) ?>" data-dialog-icon="trash" data-dialog-buttons="cancel,delete" data-dialog-accept="<?= e(__('common.delete')) ?>">
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
