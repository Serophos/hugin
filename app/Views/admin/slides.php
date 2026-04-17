<?php $title = __('slide.plural'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-head">
    <div><h1><?= e(__('slide.plural')) ?></h1><p class="muted"><?= e(__('slide.overview_hint')) ?></p></div>
    <a class="button" href="<?= e(url('/admin/slides/create')) ?>"><?= e(__('slide.new')) ?></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php foreach ($groups as $group): ?>
<div class="card">
    <div class="section-head"><div><h2><?= e($group['channel_name']) ?></h2></div></div>
    <table>
        <thead><tr><th class="handle-col"></th><th><?= e(__('common.name')) ?></th><th><?= e(__('common.type')) ?></th><th><?= e(__('common.source')) ?></th><th><?= e(__('common.duration')) ?></th><th><?= e(__('common.status')) ?></th><th></th></tr></thead>
        <tbody class="sortable-list" data-sort-endpoint="<?= e(url('/admin/sort/slides')) ?>" data-extra-name="channel_id" data-extra-value="<?= e((string)$group['channel_id']) ?>">
        <?php foreach ($group['slides'] as $slide): ?>
            <tr draggable="true" data-id="<?= e((string)$slide['id']) ?>">
                <td class="handle">↕</td>
                <td><?= e($slide['name']) ?></td>
                <td><?= e($pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', $slide['slide_type'], $slide['slide_type'])) ?></td>
                <td class="truncate"><?php if (isset($pluginLabels[$slide['slide_type']])): ?><?= e(__('slide.plugin_configuration')) ?><?php elseif (!empty($slide['media_name'])): ?><?= e($slide['media_name']) ?><?php elseif (!empty($slide['source_url'])): ?><a href="<?= e($slide['source_url']) ?>" target="_blank"><?= e(__('slide.open_source')) ?></a><?php else: ?>—<?php endif; ?></td>
                <td><?= e((string)($slide['duration_seconds'] ?? __('common.default'))) ?></td>
                <td><?= e($slide['is_active'] ? __('common.active') : __('common.inactive')) ?></td>
                <td class="actions">
                    <a href="<?= e(url('/admin/slides/' . $slide['id'] . '/edit')) ?>"><?= e(__('common.edit')) ?></a>
                    <form method="post" action="<?= e(url('/admin/channels/' . $group['channel_id'] . '/slides/' . $slide['id'] . '/remove')) ?>" class="inline-form" onsubmit="return confirm(<?= json_encode(__('slide.remove_confirm')) ?>);">
                        <?= csrf_field() ?>
                        <button type="submit" class="link-button danger"><?= e(__('common.remove')) ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
