<?php
$formId = 'display_group_create';
$returnTo = (string)($returnTo ?? '/admin/locations');
$title = __('display_groups.create_title');
$breadcrumbs = [
    ['label' => __('locations.plural'), 'url' => '/admin/locations'],
    ['label' => $location['name'], 'url' => '/admin/locations/' . $location['id'] . '/edit'],
    ['label' => $title],
];
require __DIR__ . '/../layouts/admin_header.php';
?>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
    <form method="post" action="<?= e(url('/admin/display-groups/create')) ?>" class="form-grid compact-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="location_id" value="<?= e((string)$location['id']) ?>">
        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
        <label><?= e(__('common.name')) ?>
            <input name="name" value="<?= e((string)old('name', '', $formId)) ?>" placeholder="<?= e(__('display_groups.name_placeholder')) ?>" required autofocus<?= field_attrs('name', $formId) ?>>
            <?= field_error_html('name', $formId) ?>
        </label>
        <label><?= e(__('common.sort_order')) ?>
            <input type="number" name="sort_order" value="<?= e((string)old('sort_order', '0', $formId)) ?>" min="0" placeholder="<?= e(__('display_groups.sort_order_placeholder')) ?>"<?= field_attrs('sort_order', $formId) ?>>
            <?= field_error_html('sort_order', $formId) ?>
        </label>
        <label class="full-width"><?= e(__('common.description')) ?>
            <textarea name="description" rows="3" placeholder="<?= e(__('display_groups.description_placeholder')) ?>"<?= field_attrs('description', $formId) ?>><?= e((string)old('description', '', $formId)) ?></textarea>
            <?= field_error_html('description', $formId) ?>
        </label>
        <label class="checkbox-row full-width"><input type="checkbox" name="sync_enabled" value="1" <?= old_checked('sync_enabled', 0, $formId) ?>> <?= e(__('display_groups.sync_reload_to_full_minute')) ?></label>
        <small class="field-note full-width"><?= e(__('display_groups.sync_reload_to_full_minute_help')) ?></small>
        <div class="form-actions full-width">
            <button type="submit" class="button button--default"><?= admin_icon('add') ?><span><?= e(__('common.create')) ?></span></button>
            <a class="button button--normal" href="<?= e(url($returnTo)) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
