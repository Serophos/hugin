<?php $heartbeatInterval = max(30, min(120, (int)floor(((int)app_config('monitoring.online_threshold_seconds', 180)) / 2))); ?>
<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($display['name']) ?> · <?= e(__('app.name', [], 'Hugin')) ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/display.css')) ?>">
    <?php foreach (($pluginAssets['css'] ?? []) as $cssAsset): ?>
        <link rel="stylesheet" href="<?= e($cssAsset) ?>">
    <?php endforeach; ?>
</head>
<body class="display-orientation-<?= e($orientation ?? ($display['orientation'] ?? 'landscape')) ?>">
<div id="slideshow"
     class="slideshow effect-<?= e($effect) ?> orientation-<?= e($orientation ?? ($display['orientation'] ?? 'landscape')) ?>"
     data-default-duration="<?= e((string)$duration) ?>"
     data-heartbeat-url="<?= e(url('/display/' . $display['slug'] . '/heartbeat')) ?>"
     data-heartbeat-interval="<?= e((string)$heartbeatInterval) ?>"
     data-state-url="<?= e(url('/display/' . $display['slug'] . '/state')) ?>"
     data-state-signature="<?= e($stateSignature) ?>">
    <?php foreach ($slides as $index => $slide): ?>
        <section class="slide <?= $index === 0 ? 'is-active' : '' ?>" data-duration="<?= e((string)$slide['resolved_duration']) ?>">
            <?php if (($slide['title_position'] ?? 'bottom-left') !== 'hide'): ?>
                <div class="slide-label position-<?= e($slide['title_position'] ?? 'bottom-left') ?>"><?= e($slide['name']) ?></div>
            <?php endif; ?>

            <?php if (is_string($slide['plugin_rendered_html'] ?? null) && $slide['plugin_rendered_html'] !== ''): ?>
                <?= $slide['plugin_rendered_html'] ?>
            <?php elseif ($slide['slide_type'] === 'image'): ?>
                <img src="<?= e(url($slide['resolved_source_url'])) ?>" alt="<?= e($slide['name']) ?>">
            <?php elseif ($slide['slide_type'] === 'video'): ?>
                <video src="<?= e(url($slide['resolved_source_url'])) ?>" autoplay muted playsinline preload="auto"></video>
            <?php elseif ($slide['slide_type'] === 'text'): ?>
                <div class="text-slide" style="--text-slide-bg: <?= e($slide['resolved_background_color'] ?? '#0f172a') ?>; --text-slide-fg: <?= e($slide['resolved_text_color'] ?? '#f8fafc') ?>; --text-slide-overlay: <?= e($slide['resolved_overlay_color'] ?? 'rgba(15,23,42,0.68)') ?>;">
                    <?php if (!empty($slide['text_background_url'])): ?>
                        <div class="text-slide-background" style="background-image: url('<?= e(url((string)$slide['text_background_url'])) ?>');"></div>
                    <?php endif; ?>
                    <div class="text-slide-panel">
                        <div class="text-slide-content"><?= $slide['text_rendered_html'] ?: '<p>' . e(__('slide.text_empty_frontend')) . '</p>' ?></div>
                    </div>
                </div>
            <?php else: ?>
                <iframe src="<?= e($slide['resolved_source_url']) ?>" loading="eager" referrerpolicy="no-referrer-when-downgrade"></iframe>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
<script src="<?= e(url('/assets/js/slideshow.js')) ?>"></script>
<?php foreach (($pluginAssets['js'] ?? []) as $jsAsset): ?>
    <script src="<?= e($jsAsset) ?>"></script>
<?php endforeach; ?>
</body>
</html>
