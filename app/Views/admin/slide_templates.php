<?php
$title = __('templates.plural');
$breadcrumbs = [['label' => $title]];
$templates = $templates ?? [];
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-actions">
    <a class="btn btn-primary button button--default" href="<?= e(url('/admin/slide-templates/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('templates.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert alert-success success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger error"><?= e($error) ?></div><?php endif; ?>
<div class="card shadow-sm">
    <?php if ($templates === []): ?>
        <p class="text-body-secondary muted"><?= e(__('templates.none')) ?></p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="table table-hover align-middle admin-table">
                <thead><tr><th><?= e(__('common.name')) ?></th><th><?= e(__('common.description')) ?></th><th><?= e(__('templates.used_by')) ?></th><th><?= e(__('common.status')) ?></th><th><?= e(__('common.actions')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td><?= e((string)$template['name']) ?></td>
                        <td><?= e((string)($template['description'] ?? '')) ?></td>
                        <td><?= e((string)(int)($template['slide_count'] ?? 0)) ?></td>
                        <td><?= (int)($template['is_active'] ?? 0) === 1 ? e(__('common.active')) : e(__('common.inactive')) ?></td>
                        <td class="actions">
                            <div class="btn-group btn-group-sm admin-action-group" role="group" aria-label="<?= e(__('common.actions') . ' ' . $template['name']) ?>">
                            <a class="btn btn-primary" href="<?= e(url('/admin/slide-templates/' . $template['id'] . '/edit')) ?>" aria-label="<?= e(__('common.edit') . ' ' . $template['name']) ?>" title="<?= e(__('common.edit')) ?>"><?= admin_icon('edit') ?></a>
                            <form method="post" action="<?= e(url('/admin/slide-templates/' . $template['id'] . '/delete')) ?>" class="inline-form" data-dialog-submit data-dialog-title="<?= e(__('common.delete')) ?>" data-dialog-message="<?= e(__('templates.delete_confirm', ['template' => $template['name']])) ?>" data-dialog-icon="trash" data-dialog-buttons="cancel,delete" data-dialog-accept="<?= e(__('common.delete')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-danger" aria-label="<?= e(((int)($template['slide_count'] ?? 0) > 0 ? __('templates.archive') : __('common.delete')) . ' ' . $template['name']) ?>" title="<?= e((int)($template['slide_count'] ?? 0) > 0 ? __('templates.archive') : __('common.delete')) ?>"><?= admin_icon('delete') ?></button>
                            </form>
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
