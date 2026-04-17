<?php $title = __('dashboard.title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-head"><h1><?= e(__('dashboard.title')) ?></h1></div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<div class="stats-grid">
    <div class="card stat"><strong><?= e((string)$stats['displays']) ?></strong><span><?= e(__('display.plural')) ?></span></div>
    <div class="card stat"><strong><?= e((string)$stats['online_displays']) ?></strong><span><?= e(__('dashboard.online_now')) ?></span></div>
    <div class="card stat"><strong><?= e((string)$stats['channels']) ?></strong><span><?= e(__('channel.plural')) ?></span></div>
    <div class="card stat"><strong><?= e((string)$stats['slides']) ?></strong><span><?= e(__('slide.plural')) ?></span></div>
    <div class="card stat"><strong><?= e((string)$stats['media']) ?></strong><span><?= e(__('dashboard.media_assets')) ?></span></div>
    <div class="card stat"><strong><?= e((string)$stats['users']) ?></strong><span><?= e(__('users.title')) ?></span></div>
    <div class="card stat"><strong><?= e((string)$stats['plugins']) ?></strong><span><?= e(__('dashboard.enabled_plugins')) ?></span></div>
</div>
<div class="grid-2 dashboard-grid">
    <div class="card">
        <h2><?= e(__('dashboard.display_status')) ?></h2>
        <?php if (!$displayStatuses): ?>
            <p class="muted"><?= e(__('dashboard.no_displays')) ?></p>
        <?php else: ?>
            <div class="status-list">
                <?php foreach ($displayStatuses as $displayStatus): ?>
                    <div class="status-item">
                        <div class="status-main">
                            <span class="status-dot status-<?= e($displayStatus['status']) ?>"></span>
                            <div>
                                <strong><?= e($displayStatus['name']) ?></strong>
                                <div class="muted small"><?= e($displayStatus['slug']) ?></div>
                            </div>
                        </div>
                        <div class="status-meta">
                            <div><span class="muted"><?= e(__('dashboard.channel')) ?>:</span> <?= e($displayStatus['resolved_channel_name']) ?></div>
                            <div><span class="muted"><?= e(__('dashboard.seen')) ?>:</span>
                                <?php if ($displayStatus['last_seen_at']): ?>
                                    <?= e($displayStatus['last_seen_at']) ?>
                                    (<?= e(__('dashboard.min_ago', ['minutes' => (string)$displayStatus['minutes_since_seen']])) ?>)
                                <?php else: ?>
                                    <?= e(__('common.never')) ?>
                                <?php endif; ?>
                            </div>
                            <div><span class="muted"><?= e(__('dashboard.ip')) ?>:</span> <?= e($displayStatus['last_seen_ip'] ?: __('common.unknown')) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2><?= e(__('dashboard.recently_updated_slides')) ?></h2>
        <?php if (!$recentSlides): ?>
            <p class="muted"><?= e(__('dashboard.no_slides')) ?></p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($recentSlides as $slide): ?>
                    <li><strong><?= e($slide['name']) ?></strong> · <?= e(enum_label('slide_types', $slide['slide_type'], $slide['slide_type'])) ?> · <?= e($slide['channel_names'] ?: __('dashboard.unassigned')) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
