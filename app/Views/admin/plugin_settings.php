<?php $title = __('plugins.settings_title', ['plugin' => $plugin->getDisplayName()]); require __DIR__ . '/../layouts/admin_header.php'; ?>
<h1><?= e($title) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <div class="section-head">
        <div>
            <h2><?= e($plugin->getDisplayName()) ?></h2>
            <p class="muted"><?= e($plugin->getDescription()) ?></p>
        </div>
        <span class="muted small mono"><?= e($plugin->getName()) ?></span>
    </div>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/plugins/' . $plugin->getName() . '/settings')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <?php if (trim((string)$formHtml) !== ''): ?>
            <?= $formHtml ?>
        <?php else: ?>
            <p class="muted"><?= e(__('plugins.no_global_settings')) ?></p>
        <?php endif; ?>
        <div class="form-actions">
            <button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button>
            <a class="button button--normal" href="<?= e(url('/admin/plugins')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
