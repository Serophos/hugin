<?php
$pluginName = $plugin->getName();
$pluginCss = [plugin_asset_url($pluginName, 'assets/' . $pluginName . '.css')];
$pluginAdminCssPath = dirname(__DIR__, 3) . '/plugins/' . $pluginName . '/assets/' . $pluginName . '-admin.css';
if (is_file($pluginAdminCssPath)) {
    $pluginCss[] = plugin_asset_url($pluginName, 'assets/' . $pluginName . '-admin.css');
}
$title = __('plugins.settings_title', ['plugin' => $plugin->getDisplayName()]);
$breadcrumbs = [
    ['label' => __('plugins.title'), 'url' => '/admin/plugins'],
    ['label' => $plugin->getDisplayName()],
];
require __DIR__ . '/../layouts/admin_header.php';
?>
<?php if ($error): ?><div class="alert alert-danger error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/plugins/' . $plugin->getName() . '/settings')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <?php if (trim((string)$formHtml) !== ''): ?>
        <?= $formHtml ?>
    <?php else: ?>
        <p class="text-body-secondary muted"><?= e(__('plugins.no_global_settings')) ?></p>
    <?php endif; ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
        <a class="btn btn-secondary button button--normal" href="<?= e(url('/admin/plugins')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
    </div>
</form>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
