<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? __('app.admin')) ?></title>
    <script src="<?= e(asset_url('/assets/js/admin-theme.js')) ?>"></script>
    <link rel="icon" type="image/webp" href="<?= e(url('/assets/img/hugin-logo-mini.webp')) ?>">
    <script>
        (() => {
            if (!('serviceWorker' in navigator) || typeof navigator.serviceWorker.getRegistrations !== 'function') return;
            navigator.serviceWorker.getRegistrations().then(registrations => {
                const rootScope = `${window.location.origin}/`;
                registrations.forEach(registration => {
                    const worker = registration.active || registration.waiting || registration.installing;
                    let scriptPath = '';
                    try {
                        scriptPath = worker?.scriptURL ? new URL(worker.scriptURL).pathname : '';
                    } catch (error) {}
                    if (registration.scope === rootScope && scriptPath === '/display-service-worker.js') {
                        registration.unregister();
                    }
                });
            }).catch(() => {});
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/vendor/adminlte/dist/css/adminlte.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/vendor/adminlte/tabulator/dist/css/tabulator_bootstrap5.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/admin-user-menu.css')) ?>">
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
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/admin-theme-overrides.css')) ?>">
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
$rawBreadcrumbs = isset($breadcrumbs) && is_array($breadcrumbs) ? $breadcrumbs : [];
$adminBreadcrumbs = [];
foreach ($rawBreadcrumbs as $breadcrumb) {
    if (!is_array($breadcrumb)) {
        continue;
    }
    $label = trim((string)($breadcrumb['label'] ?? ''));
    if ($label === '') {
        continue;
    }
    $adminBreadcrumbs[] = [
        'label' => $label,
        'url' => isset($breadcrumb['url']) ? trim((string)$breadcrumb['url']) : '',
    ];
}
if ($adminBreadcrumbs === []) {
    $adminBreadcrumbs[] = ['label' => (string)($title ?? __('app.admin')), 'url' => ''];
}
$adminBreadcrumbLast = array_key_last($adminBreadcrumbs);
$bodyClasses = trim(($adminShellActive ? 'layout-fixed sidebar-expand-lg bg-body-tertiary admin-shell-body' : 'login-page bg-body-secondary admin-guest-body') . ' ' . admin_accessibility_body_classes());
?>
<body class="<?= e($bodyClasses) ?>">
<a class="skip-link" href="#admin-main-content"><?= e(__('accessibility.skip_to_content', [], 'Skip to main content')) ?></a>
<?php if ($adminShellActive): ?>
<div class="app-wrapper admin-shell">
    <nav class="app-header navbar navbar-expand bg-body admin-topbar">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <button type="button" class="nav-link btn btn-link admin-menu-toggle" data-lte-toggle="sidebar" aria-controls="admin-sidebar" aria-label="<?= e(__('nav.admin', [], 'Admin')) ?>">
                        <?= admin_icon('menu') ?>
                    </button>
                </li>
            </ul>
            <div class="admin-topbar__heading">
                <nav class="admin-breadcrumb" aria-label="<?= e(__('nav.breadcrumbs', [], 'Breadcrumbs')) ?>">
                    <ol class="breadcrumb mb-0 admin-breadcrumb__list">
                        <?php foreach ($adminBreadcrumbs as $index => $breadcrumb): ?>
                            <?php
                            $isLastBreadcrumb = $index === $adminBreadcrumbLast;
                            $breadcrumbLabel = (string)$breadcrumb['label'];
                            $breadcrumbUrl = (string)($breadcrumb['url'] ?? '');
                            ?>
                            <li class="breadcrumb-item admin-breadcrumb__item<?= $isLastBreadcrumb ? ' active is-current' : '' ?>">
                                <?php if (!$isLastBreadcrumb && $breadcrumbUrl !== ''): ?>
                                    <a class="admin-breadcrumb__link" href="<?= e(url($breadcrumbUrl)) ?>"><?= e($breadcrumbLabel) ?></a>
                                <?php elseif ($isLastBreadcrumb): ?>
                                    <h1 class="h5 mb-0 admin-topbar__title admin-breadcrumb__current" aria-current="page"><?= e($breadcrumbLabel) ?></h1>
                                <?php else: ?>
                                    <span class="admin-breadcrumb__text"><?= e($breadcrumbLabel) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            </div>
            <?php require __DIR__ . '/../admin/partials/user_menu.php'; ?>
        </div>
    </nav>
    <aside class="app-sidebar bg-body shadow admin-sidebar" id="admin-sidebar">
        <a class="sidebar-brand admin-brand" href="<?= e(url('/admin')) ?>">
            <img class="brand-image opacity-75 shadow" src="<?= e(url('/assets/img/hugin-logo-mini.webp')) ?>" alt="">
            <strong class="brand-text fw-light"><?= e(__('app.name', [], 'Hugin')) ?></strong>
        </a>
        <div class="sidebar-wrapper">
        <nav aria-label="<?= e(__('app.admin')) ?>">
          <ul class="nav sidebar-menu flex-column admin-nav" data-lte-toggle="treeview" role="menu">
            <?php foreach ($adminNavItems as $item): ?>
                <?php if (!empty($item['admin']) && !is_admin()) { continue; } ?>
                <?php $isActive = $isActiveAdminPath($item['active']); ?>
                <li class="nav-item">
                <a class="nav-link admin-nav__item<?= $isActive ? ' active is-active' : '' ?>" href="<?= e(url($item['url'])) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                    <?= admin_icon($item['icon']) ?>
                    <p><?= e($item['label']) ?></p>
                </a>
                </li>
            <?php endforeach; ?>
          </ul>
        </nav>
        </div>
    </aside>
    <main id="admin-main-content" class="app-main admin-main" tabindex="-1">
      <div class="app-content">
       <div class="container-fluid py-3">
            <?php if (current_user_needs_password_change()): ?>
                <div class="alert alert-warning warning">
                    <?= e(__('auth.password_change_required_warning')) ?>
                    <a href="<?= e(url('/admin/account/password')) ?>"><?= e(__('auth.password_change_required_action')) ?></a>
                </div>
            <?php endif; ?>
<?php else: ?>
<main id="admin-main-content" class="login-box" tabindex="-1">
<?php endif; ?>
