<?php
$profilePicture = current_user_picture_url();
$profileInitial = strtoupper(substr(current_user_name(), 0, 1));
$profileUsername = (string)($adminUser['username'] ?? '');
$profileTitle = trim((string)($adminUser['title'] ?? ''));
$profileDepartment = trim((string)($adminUser['department'] ?? ''));
$profileMeta = array_values(array_filter([$profileTitle, $profileDepartment], static fn (string $value): bool => $value !== ''));
?>
<div class="nav-item dropdown admin-user-menu">
    <button type="button" class="nav-link dropdown-toggle admin-user-menu__trigger" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e(current_user_name()) ?>">
        <span class="admin-user-menu__avatar" aria-hidden="true">
            <?php if ($profilePicture !== ''): ?><img src="<?= e($profilePicture) ?>" alt="" referrerpolicy="no-referrer"><?php else: ?><?= e($profileInitial) ?><?php endif; ?>
        </span>
        <span class="admin-user-menu__trigger-name"><?= e(current_user_name()) ?></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end admin-user-menu__menu">
        <div class="admin-user-menu__profile">
            <span class="admin-user-menu__avatar admin-user-menu__avatar--large" aria-hidden="true">
                <?php if ($profilePicture !== ''): ?><img src="<?= e($profilePicture) ?>" alt="" referrerpolicy="no-referrer"><?php else: ?><?= e($profileInitial) ?><?php endif; ?>
            </span>
            <strong><?= e(current_user_name()) ?></strong>
            <span class="admin-user-menu__username">@<?= e($profileUsername) ?></span>
            <?php if ($profileMeta): ?><small><?= e(implode(' · ', $profileMeta)) ?></small><?php endif; ?>
        </div>
        <div class="admin-user-menu__preferences">
            <span class="admin-user-menu__section-label"><?= e(__('common.language', [], 'Language')) ?></span>
            <form method="post" action="<?= e(url('/admin/account/locale')) ?>" class="admin-user-menu__segments">
                <?= csrf_field() ?>
                <?php foreach (app_available_locales() as $locale => $label): ?>
                    <button type="submit" name="locale" value="<?= e((string)$locale) ?>" class="<?= current_locale() === $locale ? 'active' : '' ?>" aria-pressed="<?= current_locale() === $locale ? 'true' : 'false' ?>"><?= e((string)$label) ?></button>
                <?php endforeach; ?>
            </form>
            <span class="admin-user-menu__section-label"><?= e(__('common.appearance', [], 'Appearance')) ?></span>
            <div class="admin-user-menu__segments admin-user-menu__theme-segments">
                <button type="button" data-admin-theme-value="light" data-bs-theme-value="light" aria-pressed="false">☀ <?= e(__('common.theme_light', [], 'Light')) ?></button>
                <button type="button" data-admin-theme-value="dark" data-bs-theme-value="dark" aria-pressed="false">☾ <?= e(__('common.theme_dark', [], 'Dark')) ?></button>
                <button type="button" data-admin-theme-value="auto" data-bs-theme-value="auto" aria-pressed="false">◐ <?= e(__('common.theme_auto', [], 'System')) ?></button>
            </div>
        </div>
        <div class="admin-user-menu__actions">
            <a class="btn btn-light" href="<?= e(url('/admin/account')) ?>"><?= admin_icon('users') ?><span><?= e(__('common.my_account', [], 'My account')) ?></span></a>
            <form method="post" action="<?= e(url('/admin/logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-light"><?= admin_icon('logout') ?><span><?= e(__('common.logout')) ?></span></button>
            </form>
        </div>
    </div>
</div>
