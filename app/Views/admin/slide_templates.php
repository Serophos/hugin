<?php
$title = __('templates.plural');
$breadcrumbs = [['label' => $title]];
$templates = $templates ?? [];
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('/admin/slide-templates/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('templates.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert alert-success success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger error"><?= e($error) ?></div><?php endif; ?>
<div class="card shadow-sm">
    <?php if ($templates === []): ?>
        <p class="text-body-secondary muted"><?= e(__('templates.none')) ?></p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="table table-hover align-middle admin-table admin-table--slide-templates" data-admin-table>
                <thead>
                    <tr>
                        <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
                        <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="description" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.description')])) ?>"><?= e(__('common.description')) ?></button></th>
                        <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="usage" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('templates.used_by')])) ?>"><?= e(__('templates.used_by')) ?></button></th>
                        <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="status" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.status')])) ?>"><?= e(__('common.status')) ?></button></th>
                        <th><?= e(__('common.actions')) ?></th>
                    </tr>
                    <tr class="slide-library-filter-row">
                        <th><input type="search" data-admin-filter="name" placeholder="<?= e(__('common.name')) ?>" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>"></th>
                        <th><input type="search" data-admin-filter="description" placeholder="<?= e(__('common.description')) ?>" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.description')])) ?>"></th>
                        <th><input type="search" inputmode="numeric" data-admin-filter="usage" placeholder="<?= e(__('templates.used_by')) ?>" aria-label="<?= e(__('slide.filter_column', ['column' => __('templates.used_by')])) ?>"></th>
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
                <?php foreach ($templates as $template): ?>
                    <?php
                    $slideCount = (int)($template['slide_count'] ?? 0);
                    $statusValue = (int)($template['is_active'] ?? 0) === 1 ? 'active' : 'inactive';
                    $statusLabel = $statusValue === 'active' ? __('common.active') : __('common.inactive');
                    ?>
                    <tr data-admin-row>
                        <td data-sort-value="<?= e((string)$template['name']) ?>" data-filter-value="<?= e((string)$template['name']) ?>"><?= e((string)$template['name']) ?></td>
                        <td data-sort-value="<?= e((string)($template['description'] ?? '')) ?>" data-filter-value="<?= e((string)($template['description'] ?? '')) ?>"><?= e((string)($template['description'] ?? '')) ?></td>
                        <td data-sort-value="<?= e((string)$slideCount) ?>" data-filter-value="<?= e((string)$slideCount) ?>"><?= e((string)$slideCount) ?></td>
                        <td data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
                        <td class="actions">
                            <div class="admin-action-groups">
                                <div class="btn-group btn-group-sm admin-action-group" role="group" aria-label="<?= e(__('common.actions') . ' ' . $template['name']) ?>">
                            <a class="btn btn-primary" href="<?= e(url('/admin/slide-templates/' . $template['id'] . '/edit')) ?>" aria-label="<?= e(__('common.edit') . ' ' . $template['name']) ?>" title="<?= e(__('common.edit')) ?>"><?= admin_icon('edit') ?></a>
                            </div>
                            <div class="btn-group btn-group-sm admin-action-group" role="group" aria-label="<?= e(__('common.delete')) ?>">
                                <form method="post" action="<?= e(url('/admin/slide-templates/' . $template['id'] . '/delete')) ?>" class="inline-form" data-dialog-submit data-dialog-title="<?= e(__('common.delete')) ?>" data-dialog-message="<?= e(__('templates.delete_confirm', ['template' => $template['name']])) ?>" data-dialog-icon="trash" data-dialog-buttons="cancel,delete" data-dialog-accept="<?= e(__('common.delete')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-danger" aria-label="<?= e(($slideCount > 0 ? __('templates.archive') : __('common.delete')) . ' ' . $template['name']) ?>" title="<?= e($slideCount > 0 ? __('templates.archive') : __('common.delete')) ?>"><?= admin_icon('delete') ?></button>
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
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
