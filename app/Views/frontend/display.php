<?php
$heartbeatInterval = max(30, min(120, (int)floor(((int)app_core_setting('monitoring.online_threshold_seconds', 180)) / 2)));
$displayGroup = $displayGroup ?? null;
$syncReloadToFullMinute = !empty($displayGroup['sync_reload_to_full_minute']);
$startupSyncKey = 'hugin:slideshow-started:' . (string)($display['slug'] ?? '');
$templateFontFamilies = [];
foreach (($slides ?? []) as $slide) {
    foreach ((array)($slide['template_font_families'] ?? []) as $family) {
        if (is_string($family) && $family !== '') {
            $templateFontFamilies[$family] = true;
        }
    }
}
$loadedFontFamilies = array_values(array_unique(array_filter(array_merge([
    (string)($brandingSettings['default_font_heading'] ?? ''),
    (string)($brandingSettings['default_font_text'] ?? ''),
], array_keys($templateFontFamilies)))));
?>
<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($display['name']) ?> · <?= e(__('app.name', [], 'Hugin')) ?></title>
    <script>
        (() => {
            const startupKey = <?= json_encode($startupSyncKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const scheduledReloadKey = 'huginScheduledSyncReload';
            const maxScheduledReloadAgeMs = 120000;

            try {
                const navEntry = window.performance?.getEntriesByType?.('navigation')?.[0];
                const isReload = navEntry?.type === 'reload' || window.performance?.navigation?.type === 1;
                const hasStartedBefore = Boolean(startupKey && window.sessionStorage.getItem(startupKey));
                const rawScheduledReload = window.sessionStorage.getItem(scheduledReloadKey);
                const scheduledReload = rawScheduledReload ? JSON.parse(rawScheduledReload) : null;
                const scheduledReloadAgeMs = Date.now() - Number(scheduledReload?.at || 0);
                const hasFreshScheduledReload = scheduledReload?.reason === 'sync-group-config-reload'
                    && scheduledReloadAgeMs >= 0
                    && scheduledReloadAgeMs <= maxScheduledReloadAgeMs;

                if (isReload || hasStartedBefore || hasFreshScheduledReload) {
                    document.documentElement.classList.add('hugin-startup-loading-seen');
                }
            } catch (error) {}
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/display.css')) ?>">
    <?php foreach (($pluginAssets['css'] ?? []) as $cssAsset): ?>
        <link rel="stylesheet" href="<?= e($cssAsset) ?>">
    <?php endforeach; ?>
    <?php if ($loadedFontFamilies): ?>
        <?php $fonts = list_public_fonts(); ?>
        <?php if ($fonts): ?>
            <style>
                <?php foreach ($loadedFontFamilies as $family): ?>
                    <?php if (isset($fonts[$family])): ?>
                        @font-face {
                            font-family: '<?= e($family) ?>';
                            font-display: swap;
                            src: <?= $fonts[$family]['src'] ?>;
                        }
                    <?php endif; ?>
                <?php endforeach; ?>
                :root {
                    <?php if (!empty($brandingSettings['default_font_text'])): ?>
                        --hugin-font-text: '<?= e($brandingSettings['default_font_text']) ?>', sans-serif;
                    <?php endif; ?>
                    <?php if (!empty($brandingSettings['default_font_heading'])): ?>
                        --hugin-font-heading: '<?= e($brandingSettings['default_font_heading']) ?>', sans-serif;
                    <?php endif; ?>
                }
                .text-slide-content {
                    font-family: var(--hugin-font-text, inherit);
                }
                .text-slide-content h1,
                .text-slide-content h2,
                .text-slide-content h3,
                .text-slide-content h4,
                .text-slide-content h5,
                .text-slide-content h6 {
                    font-family: var(--hugin-font-heading, var(--hugin-font-text, inherit));
                }
            </style>
        <?php endif; ?>
    <?php endif; ?>
</head>
<body class="display-orientation-<?= e($orientation ?? ($display['orientation'] ?? 'landscape')) ?>">
<?php
$displayRoutePrefix = '/display/' . $display['slug'];
$isPreviewDisplay = false;
if (str_starts_with($display['slug'] ?? '', 'preview-slide-') && preg_match('#^preview-slide-(\d+)$#', $display['slug'], $slugMatch)) {
    $displayRoutePrefix = '/preview-slide/' . $slugMatch[1];
    $isPreviewDisplay = true;
}
?>
<div id="slideshow"
     class="slideshow <?= $isPreviewDisplay ? '' : 'is-startup-sync-pending ' ?>effect-<?= e($effect) ?> orientation-<?= e($orientation ?? ($display['orientation'] ?? 'landscape')) ?>"
     data-default-duration="<?= e((string)$duration) ?>"
     data-heartbeat-url="<?= e(url($displayRoutePrefix . '/heartbeat')) ?>"
     data-heartbeat-interval="<?= e((string)$heartbeatInterval) ?>"
     data-state-url="<?= e(url($displayRoutePrefix . '/state')) ?>"
     data-offline-manifest-url="<?= $isPreviewDisplay ? '' : e(url($displayRoutePrefix . '/offline-manifest')) ?>"
     data-service-worker-url="<?= $isPreviewDisplay ? '' : e(asset_url('/display-service-worker.js')) ?>"
     data-state-check-interval="60"
     data-state-signature="<?= e($stateSignature) ?>"
     data-server-time-ms="<?= e((string)($serverTimeMs ?? 0)) ?>"
     data-sync-reload-to-full-minute="<?= $syncReloadToFullMinute ? '1' : '0' ?>"
     data-display-group-id="<?= e((string)($displayGroup['id'] ?? '')) ?>"
     data-display-group-name="<?= e((string)($displayGroup['name'] ?? '')) ?>"
     data-display-group-sync-mode="<?= e((string)($displayGroup['sync_mode'] ?? 'independent')) ?>"
     data-startup-sync-key="<?= e($startupSyncKey) ?>">
    <div class="startup-loading" role="status" aria-live="polite">
        <div class="startup-loading__content">
            <img class="startup-loading__logo" src="<?= e(url('/assets/img/hugin-logo.webp')) ?>" alt="<?= e(__('frontend.loading_logo_alt')) ?>">
            <div class="startup-loading__copy">
                <h1><?= e(__('frontend.loading_title')) ?></h1>
                <p class="startup-loading__status"><?= e(__('frontend.loading_status')) ?></p>
            </div>
            <div class="startup-loading__bar" aria-hidden="true"></div>
            <p class="startup-loading__legal"><?= e(__('frontend.loading_legal')) ?></p>
        </div>
    </div>
    <?php foreach ($slides as $index => $slide): ?>
        <?php
        $offlinePolicy = 'skip';
        if (($slide['slide_type'] ?? '') === 'website') {
            $offlinePolicy = 'skip';
        } elseif (is_string($slide['plugin_rendered_html'] ?? null) && $slide['plugin_rendered_html'] !== '') {
            $offlinePolicy = 'try';
        } elseif (in_array((string)($slide['slide_type'] ?? ''), ['image', 'video', 'text', 'template'], true)) {
            $offlinePolicy = 'play';
        }
        ?>
        <section class="slide <?= $index === 0 ? 'is-active' : '' ?>" data-slide-id="<?= e((string)$slide['id']) ?>" data-slide-type="<?= e((string)$slide['slide_type']) ?>" data-offline-policy="<?= e($offlinePolicy) ?>" data-duration="<?= e((string)$slide['resolved_duration']) ?>">
            <?php if (($slide['title_position'] ?? 'bottom-left') !== 'hide'): ?>
                <div class="slide-label position-<?= e($slide['title_position'] ?? 'bottom-left') ?>"><?= e($slide['name']) ?></div>
            <?php endif; ?>

            <?php if (is_string($slide['plugin_rendered_html'] ?? null) && $slide['plugin_rendered_html'] !== ''): ?>
                <?= $slide['plugin_rendered_html'] ?>
            <?php elseif ($slide['slide_type'] === 'image'): ?>
                <img data-src="<?= e(url($slide['resolved_source_url'])) ?>" alt="<?= e($slide['name']) ?>" decoding="async">
            <?php elseif ($slide['slide_type'] === 'video'): ?>
                <video data-src="<?= e(url($slide['resolved_source_url'])) ?>" muted playsinline loop preload="metadata"></video>
            <?php elseif ($slide['slide_type'] === 'template'): ?>
                <?= $slide['template_rendered_html'] ?: '<div class="plugin-slide-error">' . e(__('templates.render_unavailable')) . '</div>' ?>
            <?php elseif ($slide['slide_type'] === 'text'): ?>
                <?php
                $textLayout = (string)($slide['resolved_text_box_layout'] ?? 'center');
                $textAnimation = (string)($slide['resolved_text_box_animation'] ?? 'none');
                $textBoxWidthPercent = (int)($slide['resolved_text_box_width_percent'] ?? 76);
                $textBoxBlur = !empty($slide['resolved_text_box_blur_enabled']) ? '4px' : '0px';
                $textAnimationDuration = (int)($slide['resolved_text_box_animation_duration_ms'] ?? 560);
                $textAnimationDelay = (int)($slide['resolved_text_box_animation_delay_ms'] ?? 0);
                $qrUrl = trim((string)($slide['resolved_qr_url'] ?? ''));
                $qrPosition = (string)($slide['resolved_qr_position'] ?? 'bottom-right');
                $qrAnimationEnabled = !empty($slide['resolved_qr_animation_enabled']);
                $qrSizePercent = (int)($slide['resolved_qr_size_percent'] ?? 15);
                $textSlideStyle = [
                    '--text-slide-bg' => (string)($slide['resolved_background_color'] ?? '#0f172a'),
                    '--text-slide-fg' => (string)($slide['resolved_text_color'] ?? 'rgba(248,250,252,1)'),
                    '--text-slide-overlay' => (string)($slide['resolved_overlay_color'] ?? 'rgba(15,23,42,0.68)'),
                    '--text-slide-qr-fg' => (string)($slide['resolved_qr_foreground_color'] ?? 'rgba(15,23,42,1)'),
                    '--text-slide-qr-bg' => (string)($slide['resolved_qr_background_color'] ?? 'rgba(255,255,255,1)'),
                    '--text-slide-card-width' => (string)$textBoxWidthPercent . 'vw',
                    '--text-slide-qr-size' => (string)$qrSizePercent . 'vw',
                    '--text-slide-card-blur' => $textBoxBlur,
                    '--text-card-animation-duration' => (string)$textAnimationDuration . 'ms',
                    '--text-card-animation-delay' => (string)$textAnimationDelay . 'ms',
                ];
                foreach ([
                    'top-left' => 'resolved_text_box_radius_top_left_rem',
                    'top-right' => 'resolved_text_box_radius_top_right_rem',
                    'bottom-right' => 'resolved_text_box_radius_bottom_right_rem',
                    'bottom-left' => 'resolved_text_box_radius_bottom_left_rem',
                ] as $corner => $key) {
                    if (($slide[$key] ?? null) !== null) {
                        $textSlideStyle['--text-slide-card-radius-' . $corner] = format_text_slide_radius_rem($slide[$key]) . 'rem';
                    }
                }
                foreach ([
                    'top-left' => 'resolved_qr_radius_top_left_rem',
                    'top-right' => 'resolved_qr_radius_top_right_rem',
                    'bottom-right' => 'resolved_qr_radius_bottom_right_rem',
                    'bottom-left' => 'resolved_qr_radius_bottom_left_rem',
                ] as $corner => $key) {
                    if (($slide[$key] ?? null) !== null) {
                        $textSlideStyle['--text-slide-qr-radius-' . $corner] = format_text_slide_radius_rem($slide[$key]) . 'rem';
                    }
                }
                $textSlideStyleAttr = implode('; ', array_map(static fn(string $key, string $value): string => $key . ': ' . $value, array_keys($textSlideStyle), $textSlideStyle)) . ';';
                ?>
                <div class="text-slide text-slide--layout-<?= e($textLayout) ?>" data-text-animation="<?= e($textAnimation) ?>" style="<?= e($textSlideStyleAttr) ?>">
                    <?php if (!empty($slide['text_background_url'])): ?>
                        <?php if (($slide['text_background_kind'] ?? '') === 'video'): ?>
                            <video class="text-slide-background text-slide-background--video" data-src="<?= e(url((string)$slide['text_background_url'])) ?>" muted playsinline loop preload="metadata" aria-hidden="true"></video>
                        <?php else: ?>
                            <div class="text-slide-background text-slide-background--image" data-bg-src="<?= e(url((string)$slide['text_background_url'])) ?>"></div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="text-slide-panel">
                        <div class="text-slide-card">
                            <div class="text-slide-content"><?= $slide['text_rendered_html'] ?: '<p>' . e(__('slide.text_empty_frontend')) . '</p>' ?></div>
                        </div>
                    </div>
                    <?php if ($qrUrl !== ''): ?>
                        <div class="text-slide-qr text-slide-qr--<?= e($qrPosition) ?> <?= $qrAnimationEnabled ? 'text-slide-qr--animated' : '' ?>" data-qr-url="<?= e($qrUrl) ?>" data-qr-foreground="<?= e($slide['resolved_qr_foreground_color'] ?? 'rgba(15,23,42,1)') ?>" data-qr-background="<?= e($slide['resolved_qr_background_color'] ?? 'rgba(255,255,255,1)') ?>" role="img" aria-label="<?= e(__('slide.qr_code_label')) ?>">
                            <div class="text-slide-qr__surface">
                                <canvas class="text-slide-qr__canvas" width="1" height="1"></canvas>
                                <div class="text-slide-qr__fallback"><?= e($qrUrl) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <iframe data-src="<?= e($slide['resolved_source_url']) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?= e($slide['name']) ?>"></iframe>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
<script src="<?= e(asset_url('/assets/js/hugin-qr.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/slideshow.js')) ?>"></script>
<?php foreach (($pluginAssets['js'] ?? []) as $jsAsset): ?>
    <script src="<?= e($jsAsset) ?>"></script>
<?php endforeach; ?>
</body>
</html>
