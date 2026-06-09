<?php
$title = __('templates.plural');
$templates = $templates ?? [];
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-actions">
    <a class="button button--default" href="<?= e(url('/admin/slide-templates/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('templates.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <?php if ($templates === []): ?>
        <p class="muted"><?= e(__('templates.none')) ?></p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="admin-table">
                <thead><tr><th><?= e(__('common.name')) ?></th><th><?= e(__('common.description')) ?></th><th><?= e(__('templates.used_by')) ?></th><th><?= e(__('common.status')) ?></th><th><?= e(__('common.actions')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($templates as $template): ?>
                    <tr>
                        <td><?= e((string)$template['name']) ?></td>
                        <td><?= e((string)($template['description'] ?? '')) ?></td>
                        <td><?= e((string)(int)($template['slide_count'] ?? 0)) ?></td>
                        <td><?= (int)($template['is_active'] ?? 0) === 1 ? e(__('common.active')) : e(__('common.inactive')) ?></td>
                        <td class="actions">
                            <a class="button button--normal button--small" href="<?= e(url('/admin/slide-templates/' . $template['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                            <form method="post" action="<?= e(url('/admin/slide-templates/' . $template['id'] . '/delete')) ?>" class="inline-form" data-confirm-submit data-confirm-title="<?= e(__('common.delete')) ?>" data-confirm-message="<?= e(__('templates.delete_confirm', ['template' => $template['name']])) ?>" data-confirm-accept="<?= e(__('common.delete')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e((int)($template['slide_count'] ?? 0) > 0 ? __('templates.archive') : __('common.delete')) ?></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
