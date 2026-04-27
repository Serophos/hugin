<?php
$title = __('slide.plural');
$allSlides = $allSlides ?? [];
$groups = $groups ?? [];
$renderSourceCell = static function (array $slide, array $pluginLabels): void {
    if (isset($pluginLabels[$slide['slide_type']])) {
        echo e(__('slide.plugin_configuration'));
        return;
    }

    if (!empty($slide['media_name'])) {
        echo e($slide['media_name']);
        return;
    }

    if (!empty($slide['source_url'])) {
        ?><a href="<?= e($slide['source_url']) ?>" target="_blank"><?= e(__('slide.open_source')) ?></a><?php
        return;
    }

    echo '—';
};

require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-head">
    <div><h1><?= e(__('slide.plural')) ?></h1><p class="muted"><?= e(__('slide.overview_hint')) ?></p></div>
    <a class="button button--default" href="<?= e(url('/admin/slides/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('slide.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

<div class="slide-groups">
    <details class="card slide-group">
        <summary>
            <span class="slide-group__title">
                <span class="slide-group__chevron" aria-hidden="true"></span>
                <span>
                    <h2><?= e(__('slide.all_slides')) ?></h2>
                    <small><?= e(__('slide.slide_count', ['count' => count($allSlides)])) ?></small>
                </span>
            </span>
            <span class="slide-group__hint"><?= e(__('slide.all_slides_hint')) ?></span>
        </summary>
        <div class="slide-group__body">
            <?php if ($allSlides === []): ?>
                <p class="muted slide-group__empty"><?= e(__('slide.no_slides')) ?></p>
            <?php else: ?>
                <table>
                    <thead><tr><th><?= e(__('common.name')) ?></th><th><?= e(__('common.type')) ?></th><th><?= e(__('slide.assigned_channel_names')) ?></th><th><?= e(__('common.source')) ?></th><th><?= e(__('common.duration')) ?></th><th><?= e(__('common.status')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($allSlides as $slide): ?>
                        <tr>
                            <td><?= e($slide['name']) ?></td>
                            <td><?= e($pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', $slide['slide_type'], $slide['slide_type'])) ?></td>
                            <td><?= e($slide['channel_names'] ?: __('slide.no_channels')) ?></td>
                            <td class="truncate"><?php $renderSourceCell($slide, $pluginLabels); ?></td>
                            <td><?= e((string)($slide['duration_seconds'] ?? __('common.default'))) ?></td>
                            <td><?= e($slide['is_active'] ? __('common.active') : __('common.inactive')) ?></td>
                            <td class="actions">
                                <a class="button button--normal button--small" href="<?= e(url('/admin/slides/' . $slide['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                                <form method="post" action="<?= e(url('/admin/slides/' . $slide['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm(<?= json_encode(__('slide.delete_confirm')) ?>);">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </details>

    <?php foreach ($groups as $group): ?>
    <details class="card slide-group">
        <summary>
            <span class="slide-group__title">
                <span class="slide-group__chevron" aria-hidden="true"></span>
                <span>
                    <h2><?= e($group['channel_name']) ?></h2>
                    <small><?= e(__('slide.slide_count', ['count' => count($group['slides'])])) ?></small>
                </span>
            </span>
        </summary>
        <div class="slide-group__body">
            <table>
                <thead><tr><th class="handle-col"></th><th><?= e(__('common.name')) ?></th><th><?= e(__('common.type')) ?></th><th><?= e(__('common.source')) ?></th><th><?= e(__('common.duration')) ?></th><th><?= e(__('common.status')) ?></th><th></th></tr></thead>
                <tbody class="sortable-list" data-sort-endpoint="<?= e(url('/admin/sort/slides')) ?>" data-extra-name="channel_id" data-extra-value="<?= e((string)$group['channel_id']) ?>">
                <?php foreach ($group['slides'] as $slide): ?>
                    <tr draggable="true" data-id="<?= e((string)$slide['id']) ?>">
                        <td class="handle">↕</td>
                        <td><?= e($slide['name']) ?></td>
                        <td><?= e($pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', $slide['slide_type'], $slide['slide_type'])) ?></td>
                        <td class="truncate"><?php $renderSourceCell($slide, $pluginLabels); ?></td>
                        <td><?= e((string)($slide['duration_seconds'] ?? __('common.default'))) ?></td>
                        <td><?= e($slide['is_active'] ? __('common.active') : __('common.inactive')) ?></td>
                        <td class="actions">
                            <a class="button button--normal button--small" href="<?= e(url('/admin/slides/' . $slide['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                            <form method="post" action="<?= e(url('/admin/channels/' . $group['channel_id'] . '/slides/' . $slide['id'] . '/remove')) ?>" class="inline-form" onsubmit="return confirm(<?= json_encode(__('slide.remove_confirm')) ?>);">
                                <?= csrf_field() ?>
                                <button type="submit" class="button button--danger button--small"><?= admin_icon('remove') ?><span><?= e(__('common.remove')) ?></span></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </details>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
