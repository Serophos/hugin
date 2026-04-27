<?php $title = __('channel.plural'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-head">
    <div><h1><?= e(__('channel.plural')) ?></h1><p class="muted"><?= e(__('channel.overview_hint')) ?></p></div>
    <a class="button button--default" href="<?= e(url('/admin/channels/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('channel.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php foreach ($groups as $group): ?>
<div class="card">
    <div class="section-head"><div><h2><?= e($group['display']['name']) ?></h2><p class="muted"><a href="<?= e(url('/display/' . $group['display']['slug'])) ?>" target="_blank"><?= e('/display/' . $group['display']['slug']) ?></a></p></div></div>
    <table>
        <thead><tr><th class="handle-col"></th><th><?= e(__('common.name')) ?></th><th><?= e(__('schedule.singular')) ?></th><th><?= e(__('channel.priority')) ?></th><th><?= e(__('common.effect')) ?></th><th><?= e(__('channel.slides_count')) ?></th><th><?= e(__('common.status')) ?></th><th></th></tr></thead>
        <tbody class="sortable-list" data-sort-endpoint="<?= e(url('/admin/sort/channels')) ?>" data-extra-name="display_id" data-extra-value="<?= e((string)$group['display']['id']) ?>">
        <?php foreach ($group['channels'] as $channel): ?>
            <tr draggable="true" data-id="<?= e((string)$channel['assignment_id']) ?>">
                <td class="handle">↕</td>
                <td><?= e($channel['channel_name']) ?></td>
                <td><?= e($channel['schedule_name']) ?></td>
                <td><?= e((string)$channel['priority']) ?></td>
                <td><?= e(enum_label('effects', $channel['transition_effect'], $channel['transition_effect'])) ?></td>
                <td><?= e((string)$channel['slide_count']) ?></td>
                <td><?= e($channel['is_active'] ? __('common.active') : __('common.inactive')) ?></td>
                <td class="actions">
                    <a class="button button--normal button--small" href="<?= e(url('/admin/channels/' . $channel['channel_id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                    <form method="post" action="<?= e(url('/admin/channels/' . $channel['channel_id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm(<?= json_encode(__('channel.delete_confirm')) ?>);">
                        <?= csrf_field() ?>
                        <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
