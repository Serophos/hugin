<?php $title = __('display.plural'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-head">
    <div>
        <h1><?= e(__('display.plural')) ?></h1>
        <p class="muted"><?= e(__('display.drag_hint')) ?></p>
    </div>
    <div class="actions">
        <a class="button button--normal" href="<?= e(url('/admin/locations')) ?>"><?= admin_icon('manage') ?><span><?= e(__('locations.manage')) ?></span></a>
        <a class="button button--default" href="<?= e(url('/admin/displays/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('display.new')) ?></span></a>
    </div>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<div class="card">
    <table>
        <thead>
        <tr>
            <th class="handle-col"></th>
            <th><?= e(__('common.name')) ?></th>
            <th><?= e(__('display.url_label')) ?></th>
            <th><?= e(__('locations.singular')) ?></th>
            <th><?= e(__('display_groups.singular')) ?></th>
            <th><?= e(__('common.effect')) ?></th>
            <th><?= e(__('display.channels_count')) ?></th>
            <th><?= e(__('common.status')) ?></th>
            <th></th>
        </tr>
        </thead>
        <tbody class="sortable-list" data-sort-endpoint="<?= e(url('/admin/sort/displays')) ?>">
        <?php foreach ($displays as $display): ?>
            <tr draggable="true" data-id="<?= e((string)$display['id']) ?>">
                <td class="handle">↕</td>
                <td><?= e($display['name']) ?></td>
                <td><a href="<?= e(url('/display/' . $display['slug'])) ?>" target="_blank"><?= e('/display/' . $display['slug']) ?></a></td>
                <td><?= e($display['location_name'] ?: __('locations.unassigned')) ?></td>
                <td><?= e($display['group_name'] ?: __('locations.unassigned')) ?></td>
                <td><?= e(enum_label('effects', $display['transition_effect'], $display['transition_effect'])) ?> · <?= e((string)$display['slide_duration_seconds']) ?>s</td>
                <td><?= e((string)$display['channel_count']) ?></td>
                <td><?= e($display['is_active'] ? __('common.active') : __('common.inactive')) ?></td>
                <td class="actions">
                    <a class="button button--normal button--small" href="<?= e(url('/admin/displays/' . $display['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                    <form method="post" action="<?= e(url('/admin/displays/' . $display['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm(<?= json_encode(__('display.delete_confirm', [], 'Delete display?')) ?>);">
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
