<?php $title = __('dashboard.title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-head"><h1><?= e(__('dashboard.title')) ?></h1></div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

<section class="dashboard-section">
    <div class="section-head dashboard-section-head">
        <h2><?= e(__('dashboard.monitoring_health')) ?></h2>
    </div>
    <div class="stats-grid dashboard-health-grid">
        <div class="card stat dashboard-stat dashboard-stat--online"><strong><?= e((string)$healthTotals['online']) ?></strong><span><?= e(__('dashboard.online_now')) ?></span></div>
        <div class="card stat dashboard-stat dashboard-stat--stale"><strong><?= e((string)$healthTotals['stale']) ?></strong><span><?= e(__('dashboard.stale_displays')) ?></span></div>
        <div class="card stat dashboard-stat dashboard-stat--offline"><strong><?= e((string)$healthTotals['offline']) ?></strong><span><?= e(__('dashboard.offline_displays_count')) ?></span></div>
        <div class="card stat dashboard-stat dashboard-stat--neutral"><strong><?= e((string)$healthTotals['never_seen']) ?></strong><span><?= e(__('dashboard.never_seen_displays')) ?></span></div>
        <div class="card stat dashboard-stat dashboard-stat--inactive"><strong><?= e((string)$healthTotals['inactive']) ?></strong><span><?= e(__('dashboard.inactive_displays')) ?></span></div>
    </div>
</section>

<section class="card dashboard-display-panel">
        <div class="section-head dashboard-section-head">
            <h2><?= e(__('dashboard.display_status')) ?></h2>
        </div>
        <?php if (!$onlineDisplays && !$offlineDisplays): ?>
            <p class="muted"><?= e(__('dashboard.no_displays')) ?></p>
        <?php else: ?>
            <div class="dashboard-display-columns">
                <section class="dashboard-display-group" aria-labelledby="dashboard-online-displays">
                    <div class="dashboard-display-group__head">
                        <h3 id="dashboard-online-displays"><?= e(__('dashboard.online_displays')) ?></h3>
                        <span class="status-chip"><span class="status-dot status-online"></span><?= e((string)count($onlineDisplays)) ?></span>
                    </div>
                    <?php if (!$onlineDisplays): ?>
                        <p class="muted dashboard-empty-line"><?= e(__('dashboard.no_online_displays')) ?></p>
                    <?php else: ?>
                        <div class="dashboard-display-list">
                            <?php foreach ($onlineDisplays as $display): ?>
                                <article class="dashboard-display-row" title="<?= e($display['detail_label']) ?>">
                                    <div class="dashboard-display-row__name">
                                        <span class="status-dot status-<?= e($display['status']) ?>"></span>
                                        <span class="dashboard-display-row__identity">
                                            <strong><?= e($display['name']) ?></strong>
                                            <span><?= e($display['slug']) ?></span>
                                        </span>
                                    </div>
                                    <div class="dashboard-display-row__cell"><span><?= e(__('dashboard.channel')) ?></span><strong><?= e($display['channel_label']) ?></strong></div>
                                    <div class="dashboard-display-row__cell"><span><?= e(__('dashboard.seen')) ?></span><strong><?= e($display['last_seen_label']) ?></strong></div>
                                    <div class="dashboard-display-row__cell"><span><?= e(__('dashboard.ip')) ?></span><strong><?= e($display['ip_label']) ?></strong></div>
                                    <div class="dashboard-display-row__cell"><span><?= e(__('dashboard.client')) ?></span><strong><?= e($display['client_label']) ?></strong></div>
                                    <div class="dashboard-display-row__cell"><span><?= e(__('common.screen_resolution')) ?></span><strong><?= e($display['screen_label']) ?></strong></div>
                                    <div class="dashboard-display-row__actions">
                                        <a class="button button--normal button--small button--icon-only" href="<?= e(url($display['preview_url'])) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(__('common.preview') . ' ' . $display['name']) ?>" title="<?= e(__('common.preview')) ?>"><?= admin_icon('preview') ?></a>
                                        <?php if (is_admin()): ?>
                                            <a class="button button--normal button--small button--icon-only" href="<?= e(url($display['edit_url'])) ?>" aria-label="<?= e(__('common.edit') . ' ' . $display['name']) ?>" title="<?= e(__('common.edit')) ?>"><?= admin_icon('edit') ?></a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="dashboard-display-group" aria-labelledby="dashboard-offline-displays">
                    <div class="dashboard-display-group__head">
                        <h3 id="dashboard-offline-displays"><?= e(__('dashboard.offline_displays')) ?></h3>
                        <span class="status-chip"><span class="status-dot status-offline"></span><?= e((string)count($offlineDisplays)) ?></span>
                    </div>
                    <?php if (!$offlineDisplays): ?>
                        <p class="muted dashboard-empty-line"><?= e(__('dashboard.no_offline_displays')) ?></p>
                    <?php else: ?>
                        <div class="dashboard-display-list">
                            <?php foreach ($offlineDisplays as $display): ?>
                                <article class="dashboard-display-row" title="<?= e($display['detail_label']) ?>">
                                    <div class="dashboard-display-row__name">
                                        <span class="status-dot status-<?= e($display['status']) ?>"></span>
                                        <span class="dashboard-display-row__identity">
                                            <strong><?= e($display['name']) ?></strong>
                                            <span><?= e($display['slug']) ?></span>
                                        </span>
                                    </div>
                                    <div class="dashboard-display-row__cell"><span><?= e(__('common.status')) ?></span><strong><?= e($display['status_label']) ?></strong></div>
                                    <div class="dashboard-display-row__cell"><span><?= e(__('dashboard.channel')) ?></span><strong><?= e($display['channel_label']) ?></strong></div>
                                    <div class="dashboard-display-row__cell"><span><?= e(__('dashboard.seen')) ?></span><strong><?= e($display['last_seen_label']) ?></strong></div>
                                    <div class="dashboard-display-row__cell"><span><?= e(__('dashboard.ip')) ?></span><strong><?= e($display['ip_label']) ?></strong></div>
                                    <div class="dashboard-display-row__cell"><span><?= e(__('dashboard.client')) ?></span><strong><?= e($display['client_label']) ?></strong></div>
                                    <div class="dashboard-display-row__actions">
                                        <a class="button button--normal button--small button--icon-only" href="<?= e(url($display['preview_url'])) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(__('common.preview') . ' ' . $display['name']) ?>" title="<?= e(__('common.preview')) ?>"><?= admin_icon('preview') ?></a>
                                        <?php if (is_admin()): ?>
                                            <a class="button button--normal button--small button--icon-only" href="<?= e(url($display['edit_url'])) ?>" aria-label="<?= e(__('common.edit') . ' ' . $display['name']) ?>" title="<?= e(__('common.edit')) ?>"><?= admin_icon('edit') ?></a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        <?php endif; ?>
</section>

<div class="dashboard-bottom-grid">
<section class="dashboard-section">
    <div class="section-head dashboard-section-head">
        <h2><?= e(__('dashboard.content_inventory')) ?></h2>
    </div>
    <div class="stats-grid dashboard-inventory-grid">
        <div class="card stat dashboard-stat dashboard-stat--display"><strong><?= e((string)$stats['displays']) ?></strong><span><?= e(__('display.plural')) ?></span></div>
        <div class="card stat dashboard-stat dashboard-stat--playlist"><strong><?= e((string)$stats['channels']) ?></strong><span><?= e(__('channel.plural')) ?></span></div>
        <div class="card stat dashboard-stat dashboard-stat--schedule"><strong><?= e((string)$stats['schedules']) ?></strong><span><?= e(__('schedule.plural')) ?></span></div>
        <div class="card stat dashboard-stat dashboard-stat--slide"><strong><?= e((string)$stats['slides']) ?></strong><span><?= e(__('slide.plural')) ?></span></div>
        <div class="card stat dashboard-stat dashboard-stat--media"><strong><?= e((string)$stats['media']) ?></strong><span><?= e(__('dashboard.media_assets')) ?></span></div>
        <div class="card stat dashboard-stat dashboard-stat--user"><strong><?= e((string)$stats['users']) ?></strong><span><?= e(__('users.title')) ?></span></div>
        <div class="card stat dashboard-stat dashboard-stat--plugin"><strong><?= e((string)$stats['plugins']) ?></strong><span><?= e(__('dashboard.enabled_plugins')) ?></span></div>
    </div>
</section>

    <section class="card dashboard-recent-panel">
        <h2><?= e(__('dashboard.recently_updated_slides')) ?></h2>
        <?php if (!$recentSlides): ?>
            <p class="muted"><?= e(__('dashboard.no_slides')) ?></p>
        <?php else: ?>
            <ul class="dashboard-recent-list">
                <?php foreach ($recentSlides as $slide): ?>
                    <li title="<?= e($slide['name']) ?>">
                        <strong><?= e($slide['name']) ?></strong>
                        <span><?= e(enum_label('slide_types', $slide['slide_type'], $slide['slide_type'])) ?></span>
                        <span><?= e($slide['channel_names'] ?: __('dashboard.unassigned')) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
