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
            <span class="user-pill"><?= e(current_user_name()) ?> · <?= e(current_user_role_label()) ?></span>
        <?php endif; ?>
    </div>
    <?php if (current_user()): ?>
        <nav class="main-nav">
            <a href="<?= e(url('/admin')) ?>"><?= e(__('nav.dashboard')) ?></a>
            <a href="<?= e(url('/admin/displays')) ?>"><?= e(__('nav.displays')) ?></a>
            <a href="<?= e(url('/admin/channels')) ?>"><?= e(__('nav.channels')) ?></a>
            <a href="<?= e(url('/admin/schedules')) ?>"><?= e(__('nav.schedules')) ?></a>
            <a href="<?= e(url('/admin/slides')) ?>"><?= e(__('nav.slides')) ?></a>
            <a href="<?= e(url('/admin/media')) ?>"><?= e(__('nav.media')) ?></a>
            <?php if (is_admin()): ?>
                <a href="<?= e(url('/admin/plugins')) ?>"><?= e(__('nav.plugins')) ?></a>
                <a href="<?= e(url('/admin/users')) ?>"><?= e(__('nav.users')) ?></a>
            <?php endif; ?>
            <a href="<?= e(url('/admin/about')) ?>"><?= e(__('nav.about')) ?></a>
            <form method="post" action="<?= e(url('/admin/logout')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="link-button"><?= e(__('common.logout')) ?></button>
            </form>
        </nav>
    <?php endif; ?>
</header>
<main class="container">
