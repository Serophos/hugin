<?php $title = __('locations.plural'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-head">
    <div>
        <h1><?= e(__('locations.plural')) ?></h1>
        <p class="muted"><?= e(__('locations.overview_hint')) ?></p>
    </div>
    <a class="button button--normal" href="<?= e(url('/admin/displays')) ?>"><?= admin_icon('back') ?><span><?= e(__('display.plural')) ?></span></a>
</div>

<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="organization-layout">
    <section class="organization-main">
        <div class="card">
            <div class="section-head">
                <div>
                    <h2><?= e(__('locations.configured')) ?></h2>
                    <p class="muted"><?= e(__('locations.configured_hint')) ?></p>
                </div>
            </div>

            <?php if ($locations === []): ?>
                <p class="muted"><?= e(__('locations.none')) ?></p>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead>
                        <tr>
                            <th><?= e(__('common.name')) ?></th>
                            <th><?= e(__('display_groups.plural')) ?></th>
                            <th><?= e(__('display.plural')) ?></th>
                            <th><?= e(__('common.actions')) ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($locations as $location): ?>
                            <tr>
                                <td><strong><?= e($location['name']) ?></strong></td>
                                <td><?= e((string)$location['group_count']) ?></td>
                                <td><?= e((string)$location['display_count']) ?></td>
                                <td class="actions">
                                    <a class="button button--normal button--small" href="<?= e(url('/admin/locations/' . $location['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                                    <form method="post" action="<?= e(url('/admin/locations/' . $location['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm(<?= e(json_encode(__('locations.delete_confirm'))) ?>);">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <aside class="organization-side">
        <div class="card">
            <h2><?= e(__('locations.new')) ?></h2>
            <form method="post" action="<?= e(url('/admin/locations/create')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label><?= e(__('common.name')) ?>
                    <input name="name" required>
                </label>
                <label><?= e(__('locations.address')) ?>
                    <input name="address">
                </label>
                <label><?= e(__('common.description')) ?>
                    <textarea name="description" rows="3"></textarea>
                </label>
                <label><?= e(__('common.sort_order')) ?>
                    <input type="number" name="sort_order" value="0" min="0">
                </label>
                <button type="submit" class="button button--default"><?= admin_icon('add') ?><span><?= e(__('common.create')) ?></span></button>
            </form>
        </div>

        <div class="card">
            <h2><?= e(__('locations.unassigned')) ?></h2>
            <p class="muted"><?= e(__('locations.unassigned_hint')) ?></p>
            <?php if ($unassignedDisplays === []): ?>
                <p class="muted"><?= e(__('locations.unassigned_empty')) ?></p>
            <?php else: ?>
                <div class="unassigned-list">
                    <?php foreach ($unassignedDisplays as $display): ?>
                        <a href="<?= e(url('/admin/displays/' . $display['id'] . '/edit')) ?>" class="unassigned-display">
                            <span class="status-dot status-<?= e($display['monitoring_status']) ?>"></span>
                            <span>
                                <strong><?= e($display['name']) ?></strong>
                                <small><?= e(enum_label('orientations', $display['orientation'], $display['orientation'])) ?> &middot; <?= e($display['monitoring_label']) ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
