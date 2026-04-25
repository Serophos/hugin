<?php $title = __('plugins.title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-head">
    <div>
        <h1><?= e(__('plugins.title')) ?></h1>
        <p class="muted"><?= e(__('plugins.intro')) ?></p>
    </div>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<div class="card">
    <?php if (!$plugins): ?>
        <p class="muted"><?= e(__('plugins.none_discovered')) ?></p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th><?= e(__('common.name')) ?></th>
                <th><?= e(__('slide.slide_type')) ?></th>
                <th><?= e(__('common.version', [], 'Version')) ?></th>
                <th><?= e(__('plugins.api')) ?></th>
                <th><?= e(__('plugins.description')) ?></th>
                <th><?= e(__('plugins.status')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($plugins as $plugin): ?>
                <tr>
                    <td><strong><?= e($plugin['display_name']) ?></strong><div class="muted small mono"><?= e($plugin['plugin_name']) ?></div></td>
                    <td><?= e($plugin['slide_type']) ?></td>
                    <td><?= e($plugin['version']) ?></td>
                    <td><?= e($plugin['api_version']) ?></td>
                    <td><?= e($plugin['description']) ?></td>
                    <td><?= e($plugin['is_enabled'] ? __('common.enabled') : __('common.disabled')) ?></td>
                    <td class="actions">
                        <a class="button secondary" href="<?= e(url('/admin/plugins/' . $plugin['plugin_name'] . '/settings')) ?>"><?= e(__('plugins.configure')) ?></a>
                        <form method="post" action="<?= e(url('/admin/plugins/' . $plugin['plugin_name'] . '/toggle')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="enable" value="<?= $plugin['is_enabled'] ? '0' : '1' ?>">
                            <button type="submit" class="button secondary"><?= e($plugin['is_enabled'] ? __('plugins.disable') : __('plugins.enable')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
