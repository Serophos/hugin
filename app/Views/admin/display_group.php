<?php $title = $group['name']; require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-head">
    <div>
        <h1><?= e($group['name']) ?></h1>
        <p class="muted"><?= e($group['location_name']) ?> &middot; <?= e(__('display_groups.arrangement_hint')) ?></p>
    </div>
    <div class="actions">
        <a class="button secondary" href="<?= e(url('/admin/locations/' . $group['location_id'] . '/edit')) ?>"><?= e(__('locations.plural')) ?></a>
        <form method="post" action="<?= e(url('/admin/display-groups/' . $group['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm(<?= e(json_encode(__('display_groups.delete_confirm'))) ?>);">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= e('/admin/locations/' . $group['location_id'] . '/edit') ?>">
            <button type="submit" class="button danger small"><?= e(__('common.delete')) ?></button>
        </form>
    </div>
</div>

<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

<div class="group-layout">
    <section class="group-layout-main card">
        <div class="section-head">
            <div>
                <h2><?= e(__('display_groups.arrangement')) ?></h2>
                <p class="muted"><?= e(__('display_groups.arrangement_help')) ?></p>
            </div>
            <div class="form-actions">
                <button
                    type="button"
                    data-save-layout
                    data-saving-label="<?= e(__('display_groups.layout_saving')) ?>"
                    data-unsaved-label="<?= e(__('display_groups.layout_unsaved')) ?>"
                ><?= e(__('display_groups.save_layout')) ?></button>
                <span class="layout-save-state muted" data-layout-message></span>
            </div>
        </div>

        <div class="display-layout-board" data-group-layout data-save-endpoint="<?= e(url('/admin/display-groups/' . $group['id'] . '/layout')) ?>">
            <div class="display-layout-canvas" data-layout-canvas>
                <?php if ($displays === []): ?>
                    <div class="empty-layout-message"><?= e(__('display_groups.no_displays')) ?></div>
                <?php endif; ?>

                <?php foreach ($displays as $display): ?>
                    <?php
                    $tileStyle = sprintf(
                        'left:%dpx;top:%dpx;width:%dpx;height:%dpx;',
                        (int)$display['layout_x'],
                        (int)$display['layout_y'],
                        (int)$display['layout_width'],
                        (int)$display['layout_height']
                    );
                    ?>
                    <article
                        class="display-tile display-tile--<?= e($display['orientation']) ?>"
                        data-display-tile
                        data-display-id="<?= e((string)$display['id']) ?>"
                        data-rotation="<?= e((string)$display['layout_rotation_degrees']) ?>"
                        style="<?= e($tileStyle) ?>"
                    >
                        <div class="display-tile__top">
                            <span class="orientation-mark orientation-mark--<?= e($display['orientation']) ?>"></span>
                            <span class="status-dot status-<?= e($display['monitoring_status']) ?>"></span>
                        </div>
                        <strong><?= e($display['name']) ?></strong>
                        <small><?= e($display['resolution_label']) ?></small>
                        <small><?= e($display['monitoring_label']) ?><?php if ($display['minutes_since_seen'] !== null): ?> &middot; <?= e(__('dashboard.min_ago', ['minutes' => $display['minutes_since_seen']])) ?><?php endif; ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <aside class="group-layout-side">
        <div class="card">
            <h2><?= e(__('display_groups.group_settings')) ?></h2>
            <form method="post" action="<?= e(url('/admin/display-groups/' . $group['id'] . '/edit')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="location_id" value="<?= e((string)$group['location_id']) ?>">
                <input type="hidden" name="return_to" value="<?= e('/admin/display-groups/' . $group['id']) ?>">
                <label><?= e(__('common.name')) ?>
                    <input name="name" value="<?= e($group['name']) ?>" required>
                </label>
                <label><?= e(__('common.description')) ?>
                    <textarea name="description" rows="3"><?= e($group['description'] ?? '') ?></textarea>
                </label>
                <label><?= e(__('common.sort_order')) ?>
                    <input type="number" name="sort_order" value="<?= e((string)$group['sort_order']) ?>" min="0">
                </label>
                <button type="submit"><?= e(__('common.save')) ?></button>
            </form>
        </div>

        <div class="card">
            <h2><?= e(__('display_groups.displays_in_group')) ?></h2>
            <?php if ($displays === []): ?>
                <p class="muted"><?= e(__('display_groups.no_displays')) ?></p>
            <?php else: ?>
                <div class="display-action-list">
                    <?php foreach ($displays as $display): ?>
                        <div class="display-action-row">
                            <span>
                                <strong><?= e($display['name']) ?></strong>
                                <small><?= e($display['monitoring_label']) ?></small>
                            </span>
                            <form method="post" action="<?= e(url('/admin/display-groups/bulk')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="target_group_id" value="">
                                <input type="hidden" name="return_to" value="<?= e('/admin/display-groups/' . $group['id']) ?>">
                                <input type="hidden" name="display_ids[]" value="<?= e((string)$display['id']) ?>">
                                <button type="submit" class="button secondary small"><?= e(__('display_groups.move_to_unassigned')) ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2><?= e(__('locations.unassigned')) ?></h2>
            <?php if ($unassignedDisplays === []): ?>
                <p class="muted"><?= e(__('locations.unassigned_empty')) ?></p>
            <?php else: ?>
                <div class="display-action-list">
                    <?php foreach ($unassignedDisplays as $display): ?>
                        <div class="display-action-row">
                            <span>
                                <strong><?= e($display['name']) ?></strong>
                                <small><?= e(enum_label('orientations', $display['orientation'], $display['orientation'])) ?> &middot; <?= e($display['monitoring_label']) ?></small>
                            </span>
                            <form method="post" action="<?= e(url('/admin/display-groups/bulk')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="target_group_id" value="<?= e((string)$group['id']) ?>">
                                <input type="hidden" name="return_to" value="<?= e('/admin/display-groups/' . $group['id']) ?>">
                                <input type="hidden" name="display_ids[]" value="<?= e((string)$display['id']) ?>">
                                <button type="submit" class="button small"><?= e(__('display_groups.add_to_group')) ?></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
