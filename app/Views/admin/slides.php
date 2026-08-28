<?php
$title = __('slide.plural');
$breadcrumbs = [['label' => $title]];
$allSlides = $allSlides ?? [];
$pluginLabels = $pluginLabels ?? [];
$slideTypeDefinitions = array_values($slideTypeDefinitions ?? []);
$slideTypeOptions = [];
foreach ($allSlides as $slide) {
    $typeLabel = $pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', (string)$slide['slide_type'], (string)$slide['slide_type']);
    $slideTypeOptions[$typeLabel] = $typeLabel;
}
natcasesort($slideTypeOptions);

require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('/admin/slides/create?return_to=' . rawurlencode('/admin/slides'))) ?>" data-open-slide-type-dialog data-create-url="<?= e(url('/admin/slides/create')) ?>" data-return-to="/admin/slides" aria-haspopup="dialog"><?= admin_icon('add') ?><span><?= e(__('slide.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert alert-success success"><?= e($flash) ?></div><?php endif; ?>

<section class="slide-workspace-section">
    <section class="card shadow-sm slide-library-card">
        <div class="card-body">
            <?php if ($allSlides === []): ?>
                <p class="muted slide-library-empty"><?= e(__('slide.no_slides')) ?></p>
            <?php else: ?>
                <div class="slide-library-toolbar">
                    <span class="slide-library-toolbar__meta" data-slide-library-count data-template="<?= e(__('slide.library_filter_count', ['visible' => '__VISIBLE__', 'total' => '__TOTAL__'])) ?>" aria-live="polite"></span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-slide-library-reset hidden><?= e(__('slide.clear_filters')) ?></button>
                </div>
                <div class="table-scroll">
                    <table class="table table-hover align-middle admin-table slide-library-table" data-admin-table data-admin-table-state-key="slides" data-slide-library-table>
                        <thead>
                            <tr>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="type" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.type')])) ?>"><?= e(__('common.type')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="channels" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('slide.assigned_channel_names')])) ?>"><?= e(__('slide.assigned_channel_names')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="duration" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.duration')])) ?>"><?= e(__('common.duration')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="status" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.status')])) ?>"><?= e(__('common.status')) ?></button></th>
                                <th><?= e(__('common.actions')) ?></th>
                            </tr>
                            <tr class="slide-library-filter-row">
                                <th><input class="form-control" type="search" data-admin-filter="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
                                <th>
                                    <select class="form-select" data-admin-filter="type" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.type')])) ?>">
                                        <option value=""><?= e(__('slide.filter_all_types')) ?></option>
                                        <?php foreach ($slideTypeOptions as $typeLabel): ?>
                                            <option value="<?= e($typeLabel) ?>"><?= e($typeLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                                <th><input class="form-control" type="search" data-admin-filter="channels" aria-label="<?= e(__('slide.filter_column', ['column' => __('slide.assigned_channel_names')])) ?>" placeholder="<?= e(__('slide.assigned_channel_names')) ?>"></th>
                                <th><input class="form-control" type="search" data-admin-filter="duration" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.duration')])) ?>" placeholder="<?= e(__('common.duration')) ?>"></th>
                                <th>
                                    <select class="form-select" data-admin-filter="status" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.status')])) ?>">
                                        <option value=""><?= e(__('slide.filter_all_statuses')) ?></option>
                                        <option value="active"><?= e(__('common.active')) ?></option>
                                        <option value="inactive"><?= e(__('common.inactive')) ?></option>
                                    </select>
                                </th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($allSlides as $slide): ?>
                            <?php
                            $typeLabel = $pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', (string)$slide['slide_type'], (string)$slide['slide_type']);
                            $channelLabel = $slide['channel_names'] ?: __('slide.no_channels');
                            $durationSort = $slide['duration_seconds'] ?? '';
                            $durationLabel = (string)($slide['duration_seconds'] ?? __('common.default'));
                            $statusValue = $slide['is_active'] ? 'active' : 'inactive';
                            $statusLabel = $slide['is_active'] ? __('common.active') : __('common.inactive');
                            ?>
                            <tr data-admin-row>
                                <td data-admin-cell="name" data-sort-value="<?= e((string)$slide['name']) ?>" data-filter-value="<?= e((string)$slide['name']) ?>"><?= e($slide['name']) ?></td>
                                <td data-admin-cell="type" data-sort-value="<?= e($typeLabel) ?>" data-filter-value="<?= e($typeLabel) ?>"><?= e($typeLabel) ?></td>
                                <td data-admin-cell="channels" data-sort-value="<?= e($channelLabel) ?>" data-filter-value="<?= e($channelLabel) ?>"><?= e($channelLabel) ?></td>
                                <td data-admin-cell="duration" data-sort-value="<?= e((string)$durationSort) ?>" data-filter-value="<?= e($durationLabel) ?>"><?= e($durationLabel) ?></td>
                                <td data-admin-cell="status" data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
                                <td class="actions">
                                    <div class="admin-action-groups">
                                        <div class="btn-group btn-group-sm admin-action-group" role="group" aria-label="<?= e(__('common.actions') . ' ' . $slide['name']) ?>">
                                            <a class="btn btn-secondary" target="_blank" rel="noopener noreferrer" href="<?= e(url('/preview-slide/' . $slide['id'])) ?>" aria-label="<?= e(__('common.preview') . ' ' . $slide['name']) ?>" title="<?= e(__('common.preview')) ?>"><?= admin_icon('preview') ?></a>
                                            <a class="btn btn-primary" href="<?= e(url('/admin/slides/' . $slide['id'] . '/edit')) ?>" aria-label="<?= e(__('common.edit') . ' ' . $slide['name']) ?>" title="<?= e(__('common.edit')) ?>"><?= admin_icon('edit') ?></a>
                                        </div>
                                        <div class="btn-group btn-group-sm admin-action-group" role="group" aria-label="<?= e(__('common.delete') . ' ' . $slide['name']) ?>">
                                            <form
                                                method="post"
                                                action="<?= e(url('/admin/slides/' . $slide['id'] . '/delete')) ?>"
                                                class="inline-form"
                                                data-dialog-submit
                                                data-dialog-title="<?= e(__('common.delete')) ?>"
                                                data-dialog-message="<?= e(__('slide.delete_everywhere_confirm', ['slide' => $slide['name'], 'count' => (int)($slide['channel_count'] ?? 0)])) ?>"
                                                data-dialog-icon="trash"
                                                data-dialog-buttons="cancel,delete"
                                                data-dialog-accept="<?= e(__('common.delete')) ?>"
                                            >
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-danger" aria-label="<?= e(__('common.delete') . ' ' . $slide['name']) ?>" title="<?= e(__('common.delete')) ?>"><?= admin_icon('delete') ?></button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="muted slide-library-empty" data-slide-library-empty hidden><?= e(__('slide.library_filter_empty')) ?></p>
            <?php endif; ?>
        </div>
    </section>
</section>

<?php
$slideTypeCreateUrl = url('/admin/slides/create');
$slideTypeReturnTo = '/admin/slides';
require __DIR__ . '/partials/slide_type_dialog.php';
?>

<script>
(() => {
    const resetFilters = document.querySelector('[data-slide-library-reset]');
    const count = document.querySelector('[data-slide-library-count]');
    const empty = document.querySelector('[data-slide-library-empty]');

    document.addEventListener('admin-table-updated', (event) => {
        const table = event.target instanceof Element ? event.target.closest('[data-slide-library-table]') : null;
        if (!table) return;

        const visible = Number(event.detail?.visible || 0);
        const total = Number(event.detail?.total || 0);
        if (count) {
            const template = count.dataset.template || '__VISIBLE__ / __TOTAL__';
            count.textContent = template.replace('__VISIBLE__', String(visible)).replace('__TOTAL__', String(total));
        }
        if (empty) {
            empty.hidden = visible > 0;
        }
        if (resetFilters) {
            resetFilters.hidden = visible === total;
        }
    });

    resetFilters?.addEventListener('click', () => {
        document.querySelector('[data-slide-library-table]')?.dispatchEvent(new CustomEvent('admin-table-reset'));
    });

})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
