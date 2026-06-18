<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? __('app.admin')) ?></title>
    <link rel="icon" type="image/webp" href="<?= e(url('/assets/img/hugin-logo-mini.webp')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/admin.css')) ?>">
    <?php
    $pluginCssLinks = [];
    if (!empty($pluginCss)) {
        $rawPluginCssLinks = is_array($pluginCss) ? $pluginCss : [$pluginCss];
        foreach ($rawPluginCssLinks as $cssHref) {
            $cssHref = trim((string)$cssHref);
            if ($cssHref !== '' && !in_array($cssHref, $pluginCssLinks, true)) {
                $pluginCssLinks[] = $cssHref;
            }
        }
    }
    ?>
    <?php foreach ($pluginCssLinks as $cssHref): ?>
        <link rel="stylesheet" href="<?= e($cssHref) ?>">
    <?php endforeach; ?>
    <script src="<?= e(asset_url('/assets/js/admin-color-picker.js')) ?>"></script>
</head>
<?php
$adminUser = current_user();
$adminShellActive = (bool)$adminUser;
$currentPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$currentPath = rtrim(is_string($currentPath) ? $currentPath : '/', '/') ?: '/';
$isActiveAdminPath = static function (array $paths) use ($currentPath): bool {
    foreach ($paths as $path) {
        $path = rtrim((string)$path, '/') ?: '/';
        if ($path === '/admin') {
            if ($currentPath === '/admin') {
                return true;
            }
            continue;
        }
        if ($currentPath === $path || str_starts_with($currentPath, $path . '/')) {
            return true;
        }
    }
    return false;
};
$adminNavItems = [
    ['label' => __('nav.dashboard'), 'url' => '/admin', 'icon' => 'dashboard', 'active' => ['/admin']],
    ['label' => __('nav.locations'), 'url' => '/admin/locations', 'icon' => 'locations', 'active' => ['/admin/locations'], 'admin' => true],
    ['label' => __('nav.displays'), 'url' => '/admin/displays', 'icon' => 'displays', 'active' => ['/admin/displays'], 'admin' => true],
    ['label' => __('nav.playlists'), 'url' => '/admin/playlists', 'icon' => 'playlists', 'active' => ['/admin/playlists', '/admin/channels']],
    ['label' => __('nav.schedules'), 'url' => '/admin/schedules', 'icon' => 'schedules', 'active' => ['/admin/schedules']],
    ['label' => __('nav.slides'), 'url' => '/admin/slides', 'icon' => 'slides', 'active' => ['/admin/slides']],
    ['label' => __('nav.slide_templates'), 'url' => '/admin/slide-templates', 'icon' => 'templates', 'active' => ['/admin/slide-templates']],
    ['label' => __('nav.media'), 'url' => '/admin/media', 'icon' => 'media', 'active' => ['/admin/media']],
    ['label' => __('nav.plugins'), 'url' => '/admin/plugins', 'icon' => 'plugins', 'active' => ['/admin/plugins'], 'admin' => true],
    ['label' => __('nav.users'), 'url' => '/admin/users', 'icon' => 'users', 'active' => ['/admin/users'], 'admin' => true],
    ['label' => __('nav.settings'), 'url' => '/admin/settings', 'icon' => 'settings', 'active' => ['/admin/settings'], 'admin' => true],
    ['label' => __('nav.accessibility', [], 'Accessibility'), 'url' => '/admin/accessibility', 'icon' => 'about', 'active' => ['/admin/accessibility']],
    ['label' => __('nav.about'), 'url' => '/admin/about', 'icon' => 'about', 'active' => ['/admin/about']],
];
$bodyClasses = trim(($adminShellActive ? 'admin-shell-body' : 'admin-guest-body') . ' ' . admin_accessibility_body_classes());
?>
<body class="<?= e($bodyClasses) ?>">
<a class="skip-link" href="#admin-main-content"><?= e(__('accessibility.skip_to_content', [], 'Skip to main content')) ?></a>
<?php if ($adminShellActive): ?>
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar">
        <a class="admin-brand" href="<?= e(url('/admin')) ?>">
            <img src="<?= e(url('/assets/img/hugin-logo-mini.webp')) ?>" alt="">
            <strong><?= e(__('app.name', [], 'Hugin')) ?></strong>
        </a>
        <div class="admin-sidebar-user">
            <span class="admin-sidebar-user__avatar" aria-hidden="true"><?= e(strtoupper(substr(current_user_name(), 0, 1))) ?></span>
            <span>
                <strong><?= e(current_user_name()) ?></strong>
                <small><?= e(current_user_role_label()) ?></small>
            </span>
        </div>
        <nav class="admin-nav" aria-label="<?= e(__('app.admin')) ?>">
            <?php foreach ($adminNavItems as $item): ?>
                <?php if (!empty($item['admin']) && !is_admin()) { continue; } ?>
                <?php $isActive = $isActiveAdminPath($item['active']); ?>
                <a class="admin-nav__item<?= $isActive ? ' is-active' : '' ?>" href="<?= e(url($item['url'])) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                    <?= admin_icon($item['icon']) ?>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('/admin/logout')) ?>" class="admin-nav-form">
                <?= csrf_field() ?>
                <button type="submit" class="admin-nav__item admin-nav__button">
                    <?= admin_icon('logout') ?>
                    <span><?= e(__('common.logout')) ?></span>
                </button>
            </form>
        </nav>
    </aside>
    <div class="admin-sidebar-overlay" data-admin-sidebar-close></div>
    <div class="admin-main">
        <header class="admin-topbar">
            <button type="button" class="admin-menu-toggle" data-admin-sidebar-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="<?= e(__('nav.admin', [], 'Admin')) ?>">
                <?= admin_icon('menu') ?>
            </button>
            <div>
                <h1 class="admin-topbar__title"><?= e($title ?? __('app.admin')) ?></h1>
            </div>
            <span class="user-pill"><?= e(current_user_name()) ?></span>
        </header>
        <main id="admin-main-content" class="container" tabindex="-1">
            <?php if (current_user_needs_password_change()): ?>
                <div class="alert warning">
                    <?= e(__('auth.password_change_required_warning')) ?>
                    <a href="<?= e(url('/admin/account/password')) ?>"><?= e(__('auth.password_change_required_action')) ?></a>
                </div>
            <?php endif; ?>
<?php else: ?>
<header class="topbar topbar--guest">
    <div class="brand">
        <img src="<?= e(url('/assets/img/hugin-logo-mini.webp')) ?>" alt="">
        <strong><?= e(__('app.name', [], 'Hugin')) ?></strong>
    </div>
</header>
<main id="admin-main-content" class="container" tabindex="-1">
<?php endif; ?>
