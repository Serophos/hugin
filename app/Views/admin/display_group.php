<?php
$groupEditForm = 'display_group_edit';
$primaryDisplayId = (int)($group['primary_display_id'] ?? 0);
$primaryDisplayLabel = __('display_groups.primary_display');
$title = $group['name'];
$breadcrumbs = [
    ['label' => __('locations.plural'), 'url' => '/admin/locations'],
    ['label' => $group['location_name'], 'url' => '/admin/locations/' . $group['location_id'] . '/edit'],
    ['label' => __('display_groups.plural')],
    ['label' => $group['name']],
];
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-actions">
    <form method="post" action="<?= e(url('/admin/display-groups/' . $group['id'] . '/delete')) ?>" class="inline-form" data-dialog-submit data-dialog-title="<?= e(__('common.delete')) ?>" data-dialog-message="<?= e(__('display_groups.delete_confirm')) ?>" data-dialog-icon="trash" data-dialog-buttons="cancel,delete" data-dialog-accept="<?= e(__('common.delete')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= e('/admin/locations/' . $group['location_id'] . '/edit') ?>">
        <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
    </form>
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
        </div>

        <div class="display-layout-toolbar" role="group" aria-label="<?= e(__('display_groups.arrangement')) ?>">
            <label class="display-layout-snap-toggle" data-toolbar-tooltip="<?= e(__('display_groups.snap_to_grid')) ?>">
                <input type="checkbox" data-layout-snap-toggle checked>
                <span class="display-layout-snap-toggle__icon" aria-hidden="true"></span>
                <span class="sr-only"><?= e(__('display_groups.snap_to_grid')) ?></span>
            </label>
            <div class="display-layout-toolbar__actions">
                <span class="layout-save-state muted" data-layout-message aria-live="polite"></span>
                <button
                    type="button"
                    class="button button--normal button--small button--icon-only display-layout-toolbar__button"
                    data-open-unassigned-display-dialog
                    data-toolbar-tooltip="<?= e(__('display_groups.add_display')) ?>"
                    aria-haspopup="dialog"
                    aria-controls="unassigned-display-dialog"
                    aria-label="<?= e(__('display_groups.add_display')) ?>"
                ><?= admin_icon('add') ?><span class="sr-only"><?= e(__('display_groups.add_display')) ?></span></button>
                <button
                    type="button"
                    class="button button--normal button--small button--icon-only display-layout-toolbar__button"
                    data-remove-selected-display
                    data-toolbar-tooltip="<?= e(__('display_groups.remove_selected_display')) ?>"
                    aria-label="<?= e(__('display_groups.remove_selected_display')) ?>"
                    disabled
                ><?= admin_icon('remove') ?><span class="sr-only"><?= e(__('display_groups.remove_selected_display')) ?></span></button>
                <button
                    type="button"
                    class="button button--normal button--small button--icon-only display-layout-toolbar__button display-layout-toolbar__button--primary"
                    data-toggle-primary-display
                    data-toolbar-tooltip="<?= e(__('display_groups.primary_display')) ?>"
                    data-primary-label="<?= e(__('display_groups.primary_display')) ?>"
                    data-primary-set-label="<?= e(__('display_groups.set_primary_display')) ?>"
                    data-primary-clear-label="<?= e(__('display_groups.clear_primary_display')) ?>"
                    aria-label="<?= e(__('display_groups.primary_display')) ?>"
                    aria-pressed="false"
                    disabled
                ><?= admin_icon('primary-display') ?><span class="sr-only"><?= e(__('display_groups.primary_display')) ?></span></button>
                <button
                    type="button"
                    class="button button--default button--small button--icon-only display-layout-toolbar__button"
                    data-save-layout
                    data-toolbar-tooltip="<?= e(__('display_groups.save_layout')) ?>"
                    data-saving-label="<?= e(__('display_groups.layout_saving')) ?>"
                    data-unsaved-label="<?= e(__('display_groups.layout_unsaved')) ?>"
                    data-saved-label="<?= e(__('display_groups.layout_saved')) ?>"
                    data-save-failed-label="<?= e(__('display_groups.layout_save_failed')) ?>"
                    aria-label="<?= e(__('display_groups.save_layout')) ?>"
                ><?= admin_icon('save') ?><span class="sr-only"><?= e(__('display_groups.save_layout')) ?></span></button>
            </div>
        </div>

        <div class="display-layout-board" data-group-layout data-save-endpoint="<?= e(url('/admin/display-groups/' . $group['id'] . '/layout')) ?>">
            <div class="display-layout-canvas" data-layout-canvas>
                <p id="display-layout-keyboard-help" class="sr-only"><?= e(__('display_groups.keyboard_instructions')) ?></p>
                <div class="empty-layout-message" data-layout-empty <?= $displays === [] ? '' : 'hidden' ?>><?= e(__('display_groups.no_displays')) ?></div>

                <?php foreach ($displays as $display): ?>
                    <?php
                    $tileStyle = sprintf(
                        'left:%dpx;top:%dpx;width:%dpx;height:%dpx;',
                        (int)$display['layout_x'],
                        (int)$display['layout_y'],
                        (int)$display['layout_width'],
                        (int)$display['layout_height']
                    );
                    $displaySyncEnabled = !empty($display['group_sync_enabled']);
                    $isPrimaryDisplay = $primaryDisplayId > 0 && (int)$display['id'] === $primaryDisplayId;
                    $syncLabel = __('display_groups.sync_enabled_indicator');
                    $tileTitleParts = array_filter([
                        (string)$display['name'],
                        (string)($display['resolution_label'] ?? ''),
                        (string)($display['monitoring_label'] ?? ''),
                        $isPrimaryDisplay ? $primaryDisplayLabel : '',
                        $displaySyncEnabled ? $syncLabel : '',
                    ], static fn(string $part): bool => trim($part) !== '');
                    $tileTitle = implode(' - ', $tileTitleParts);
                    $iconUrl = (string)($display['display_icon_url'] ?? '');
                    ?>
                    <article
                        class="display-tile display-tile--<?= e($display['orientation']) ?><?= $isPrimaryDisplay ? ' is-primary' : '' ?>"
                        data-display-tile
                        data-display-id="<?= e((string)$display['id']) ?>"
                        data-is-primary-display="<?= $isPrimaryDisplay ? '1' : '0' ?>"
                        data-rotation="<?= e((string)$display['layout_rotation_degrees']) ?>"
                        aria-label="<?= e($tileTitle) ?>"
                        aria-describedby="display-layout-keyboard-help"
                        tabindex="0"
                        title="<?= e($tileTitle) ?>"
                        style="<?= e($tileStyle) ?>"
                    >
                        <?php if ($iconUrl !== '' || $displaySyncEnabled): ?>
                            <span class="display-tile__icon-frame">
                                <?php if ($iconUrl !== ''): ?>
                                    <img class="display-tile__icon" src="<?= e($iconUrl) ?>" alt="" draggable="false">
                                <?php endif; ?>
                                <?php if ($displaySyncEnabled): ?>
                                    <span class="display-sync-indicator display-sync-indicator--tile" data-sync-tooltip="<?= e($syncLabel) ?>" title="<?= e($syncLabel) ?>" aria-hidden="true"><?= admin_icon('history') ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <span class="display-tile__label"><?= e($display['name']) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <dialog id="unassigned-display-dialog" class="admin-dialog display-model-dialog display-group-add-dialog" data-unassigned-display-dialog aria-labelledby="unassigned-display-dialog-title" aria-describedby="unassigned-display-dialog-description">
        <form method="post" action="<?= e(url('/admin/display-groups/bulk')) ?>" class="admin-dialog__panel display-model-dialog__panel" data-unassigned-display-form>
            <?= csrf_field() ?>
            <input type="hidden" name="target_group_id" value="<?= e((string)$group['id']) ?>">
            <input type="hidden" name="return_to" value="<?= e('/admin/display-groups/' . $group['id']) ?>">
            <input type="hidden" name="display_ids[]" value="" data-unassigned-display-input>
            <div class="section-head display-model-dialog__head">
                <div>
                    <h2 id="unassigned-display-dialog-title"><?= e(__('display_groups.add_display_title')) ?></h2>
                    <p id="unassigned-display-dialog-description" class="muted"><?= e(__('display_groups.add_display_hint')) ?></p>
                </div>
            </div>
            <div class="display-model-dialog__scroll">
                <div class="display-model-grid" data-unassigned-display-options role="radiogroup" aria-labelledby="unassigned-display-dialog-title">
                    <?php foreach ($unassignedDisplays as $index => $display): ?>
                        <?php
                        $iconUrl = (string)($display['display_icon_url'] ?? '');
                        $displayMeta = trim(enum_label('orientations', $display['orientation'], $display['orientation']) . ' - ' . (string)$display['monitoring_label']);
                        ?>
                        <button
                            type="button"
                            class="display-model-card"
                            data-unassigned-display-option
                            data-display-id="<?= e((string)$display['id']) ?>"
                            aria-checked="<?= $index === 0 ? 'true' : 'false' ?>"
                            aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                            role="radio"
                            tabindex="<?= $index === 0 ? '0' : '-1' ?>"
                            title="<?= e((string)$display['name']) ?>"
                        >
                            <span class="display-model-card__image">
                                <?php if ($iconUrl !== ''): ?>
                                    <img src="<?= e($iconUrl) ?>" alt="" draggable="false">
                                <?php endif; ?>
                            </span>
                            <span class="display-model-card__name"><?= e((string)$display['name']) ?></span>
                            <span class="display-group-add-dialog__meta"><?= e($displayMeta) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="display-model-empty muted" data-unassigned-display-empty hidden><?= e(__('display_groups.no_unassigned_displays')) ?></p>
            <div class="form-actions display-model-dialog__actions">
                <button type="button" class="button button--normal" data-unassigned-display-close><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></button>
                <button type="submit" class="button button--default" data-unassigned-display-submit><?= admin_icon('add') ?><span><?= e(__('display_groups.add_selected_display')) ?></span></button>
            </div>
        </form>
    </dialog>

    <aside class="group-layout-side">
        <div class="card">
            <h2><?= e(__('display_groups.group_settings')) ?></h2>
            <form method="post" action="<?= e(url('/admin/display-groups/' . $group['id'] . '/edit')) ?>" class="form-grid" data-group-settings-form>
                <?= csrf_field() ?>
                <input type="hidden" name="location_id" value="<?= e((string)$group['location_id']) ?>">
                <input type="hidden" name="return_to" value="<?= e('/admin/display-groups/' . $group['id']) ?>">
                <input type="hidden" name="primary_display_id" value="<?= $primaryDisplayId > 0 ? e((string)$primaryDisplayId) : '' ?>" data-primary-display-input>
                <span data-removed-display-inputs hidden></span>
                <label><?= e(__('common.name')) ?>
                    <input name="name" value="<?= e((string)old('name', $group['name'], $groupEditForm)) ?>" placeholder="<?= e(__('display_groups.name_placeholder')) ?>" required<?= field_attrs('name', $groupEditForm) ?>>
                    <?= field_error_html('name', $groupEditForm) ?>
                </label>
                <label><?= e(__('common.description')) ?>
                    <textarea name="description" rows="3" placeholder="<?= e(__('display_groups.description_placeholder')) ?>"<?= field_attrs('description', $groupEditForm) ?>><?= e((string)old('description', $group['description'] ?? '', $groupEditForm)) ?></textarea>
                    <?= field_error_html('description', $groupEditForm) ?>
                </label>
                <label><?= e(__('common.sort_order')) ?>
                    <input type="number" name="sort_order" value="<?= e((string)old('sort_order', $group['sort_order'], $groupEditForm)) ?>" min="0" placeholder="<?= e(__('display_groups.sort_order_placeholder')) ?>"<?= field_attrs('sort_order', $groupEditForm) ?>>
                    <?= field_error_html('sort_order', $groupEditForm) ?>
                </label>
                <label class="checkbox-row"><input type="checkbox" name="sync_enabled" value="1" <?= old_checked('sync_enabled', $group['sync_enabled'] ?? 0, $groupEditForm) ?>> <?= e(__('display_groups.sync_reload_to_full_minute')) ?></label>
                <small class="field-note"><?= e(__('display_groups.sync_reload_to_full_minute_help')) ?></small>
                <button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
            </form>
        </div>

        <div class="card">
            <h2><?= e(__('display_groups.displays_in_group')) ?></h2>
            <?php if ($displays === []): ?>
                <p class="muted"><?= e(__('display_groups.no_displays')) ?></p>
            <?php else: ?>
                <p class="muted" data-group-display-empty hidden><?= e(__('display_groups.no_displays')) ?></p>
                <div class="display-action-list" data-group-display-list>
                    <?php foreach ($displays as $display): ?>
                        <div class="display-action-row" data-group-display-row data-display-id="<?= e((string)$display['id']) ?>">
                            <span>
                                <strong><?= e($display['name']) ?></strong>
                                <small><?= e($display['monitoring_label']) ?></small>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
