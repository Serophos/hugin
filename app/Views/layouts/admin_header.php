<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? __('app.admin')) ?></title>
    <link rel="icon" type="image/webp" href="<?= e(url('/assets/img/hugin-logo-mini.webp')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/admin.css')) ?>">
</head>
<body>
<header class="topbar">
    <div class="brand">
        <img src="<?= e(url('/assets/img/hugin-logo-mini.webp')) ?>">
        <strong><?= e(__('app.name', [], 'Hugin')) ?></strong>
        <?php if (current_user()): ?>
            <span class="user-pill"><?= e(current_user_name()) ?></span>
        <?php endif; ?>
    </div>
    <?php if (current_user()): ?>
        <?php
            $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
            $navItems = [
                ['path' => '/admin', 'label' => __('nav.dashboard'), 'icon' => 'dashboard'],
                ['path' => '/admin/locations', 'label' => __('nav.locations'), 'icon' => 'locations', 'admin' => true],
                ['path' => '/admin/displays', 'label' => __('nav.displays'), 'icon' => 'displays', 'admin' => true],
                ['path' => '/admin/channels', 'label' => __('nav.channels'), 'icon' => 'channels'],
                ['path' => '/admin/schedules', 'label' => __('nav.schedules'), 'icon' => 'schedules'],
                ['path' => '/admin/slides', 'label' => __('nav.slides'), 'icon' => 'slides'],
                ['path' => '/admin/media', 'label' => __('nav.media'), 'icon' => 'media'],
                ['path' => '/admin/plugins', 'label' => __('nav.plugins'), 'icon' => 'plugins', 'admin' => true],
                ['path' => '/admin/users', 'label' => __('nav.users'), 'icon' => 'users', 'admin' => true],
                ['path' => '/admin/about', 'label' => __('nav.about'), 'icon' => 'about'],
            ];
        ?>
        <nav class="main-nav">
            <?php foreach ($navItems as $item): ?>
                <?php if (!empty($item['admin']) && !is_admin()) {
                    continue;
                }
                $active = $item['path'] === '/admin'
                    ? $currentPath === '/admin' || $currentPath === '/admin/'
                    : substr($currentPath, 0, strlen($item['path'])) === $item['path'];
                ?>
                <a href="<?= e(url($item['path'])) ?>" class="<?= $active ? 'active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?> >
                    <span class="nav-icon"><?= admin_icon($item['icon']) ?></span>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('/admin/logout')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="link-button"><?= e(__('common.logout')) ?></button>
            </form>
        </nav>
    <?php endif; ?>
</header>
<main class="container">
