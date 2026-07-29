<?php
$title = __('dashboard.title');
$breadcrumbs = [['label' => $title]];
require __DIR__ . '/../layouts/admin_header.php';
$displayTotal = array_sum($healthTotals);
$monitoringInfoBoxes = [
    ['status' => 'online', 'label' => __('dashboard.online_now'), 'icon' => 'displays', 'class' => 'text-bg-success'],
    ['status' => 'stale', 'label' => __('dashboard.stale_displays'), 'icon' => 'history', 'class' => 'text-bg-warning'],
    ['status' => 'offline', 'label' => __('dashboard.offline_displays_count'), 'icon' => 'cancel', 'class' => 'text-bg-danger'],
    ['status' => 'never_seen', 'label' => __('dashboard.never_seen_displays'), 'icon' => 'preview', 'class' => 'text-bg-primary'],
    ['status' => 'inactive', 'label' => __('dashboard.inactive_displays'), 'icon' => 'remove', 'class' => 'text-bg-secondary'],
];
?>
<?php if ($flash): ?><div class="alert alert-success success"><?= e($flash) ?></div><?php endif; ?>

<section class="dashboard-section">
    <div class="section-head dashboard-section-head">
        <h2><?= e(__('dashboard.monitoring_health')) ?></h2>
    </div>
    <div class="row g-3">
        <?php foreach ($monitoringInfoBoxes as $monitoringInfoBox): ?>
            <?php
            $monitoringCount = (int)($healthTotals[$monitoringInfoBox['status']] ?? 0);
            $monitoringPercent = $displayTotal > 0 ? (int)round(($monitoringCount / $displayTotal) * 100) : 0;
            ?>
        <div class="col-12 col-sm-6 col-xl">
            <div class="info-box <?= e($monitoringInfoBox['class']) ?> bg-gradient">
                <span class="info-box-icon"><?= admin_icon($monitoringInfoBox['icon']) ?></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?= e($monitoringInfoBox['label']) ?></span>
                    <span class="info-box-number"><?= e((string)$monitoringCount) ?></span>
                    <div class="progress" role="progressbar" aria-label="<?= e($monitoringInfoBox['label']) ?>" aria-valuenow="<?= e((string)$monitoringPercent) ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: <?= e((string)$monitoringPercent) ?>%"></div>
                    </div>
                    <span class="progress-description"><?= e(__('dashboard.percent_of_displays', ['percent' => $monitoringPercent], ':percent% of displays')) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card dashboard-display-panel">
        <div class="section-head dashboard-section-head">
            <h2><?= e(__('dashboard.display_status')) ?></h2>
        </div>
        <?php if (!$onlineDisplays && !$offlineDisplays): ?>
            <p class="text-body-secondary muted"><?= e(__('dashboard.no_displays')) ?></p>
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
                                        <div class="btn-group btn-group-sm admin-action-group" role="group" aria-label="<?= e(__('common.actions') . ' ' . $display['name']) ?>">
                                        <a class="btn btn-primary btn-sm admin-icon-button" href="<?= e(url($display['preview_url'])) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(__('common.preview') . ' ' . $display['name']) ?>" title="<?= e(__('common.preview')) ?>"><?= admin_icon('preview') ?></a>
                                        <?php if (is_admin()): ?>
                                            <a class="btn btn-outline-secondary btn-sm admin-icon-button" href="<?= e(url($display['edit_url'])) ?>" aria-label="<?= e(__('common.edit') . ' ' . $display['name']) ?>" title="<?= e(__('common.edit')) ?>"><?= admin_icon('edit') ?></a>
                                        <?php endif; ?>
                                        </div>
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
                                        <div class="btn-group btn-group-sm admin-action-group" role="group" aria-label="<?= e(__('common.actions') . ' ' . $display['name']) ?>">
                                        <a class="btn btn-primary btn-sm admin-icon-button" href="<?= e(url($display['preview_url'])) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(__('common.preview') . ' ' . $display['name']) ?>" title="<?= e(__('common.preview')) ?>"><?= admin_icon('preview') ?></a>
                                        <?php if (is_admin()): ?>
                                            <a class="btn btn-outline-secondary btn-sm admin-icon-button" href="<?= e(url($display['edit_url'])) ?>" aria-label="<?= e(__('common.edit') . ' ' . $display['name']) ?>" title="<?= e(__('common.edit')) ?>"><?= admin_icon('edit') ?></a>
                                        <?php endif; ?>
                                        </div>
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
    <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="info-box text-bg-success bg-gradient">
                <span class="info-box-icon"><?= admin_icon('displays') ?></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?= e(__('display.plural')) ?></span>
                    <span class="info-box-number"><?= e((string)$stats['displays']) ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="info-box text-bg-primary bg-gradient">
                <span class="info-box-icon"><?= admin_icon('playlists') ?></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?= e(__('channel.plural')) ?></span>
                    <span class="info-box-number"><?= e((string)$stats['channels']) ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="info-box text-bg-warning bg-gradient">
                <span class="info-box-icon"><?= admin_icon('schedules') ?></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?= e(__('schedule.plural')) ?></span>
                    <span class="info-box-number"><?= e((string)$stats['schedules']) ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="info-box text-bg-info bg-gradient">
                <span class="info-box-icon"><?= admin_icon('slides') ?></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?= e(__('slide.plural')) ?></span>
                    <span class="info-box-number"><?= e((string)$stats['slides']) ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="info-box text-bg-danger bg-gradient">
                <span class="info-box-icon"><?= admin_icon('media') ?></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?= e(__('dashboard.media_assets')) ?></span>
                    <span class="info-box-number"><?= e((string)$stats['media']) ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="info-box text-bg-secondary bg-gradient">
                <span class="info-box-icon"><?= admin_icon('users') ?></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?= e(__('users.title')) ?></span>
                    <span class="info-box-number"><?= e((string)$stats['users']) ?></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="info-box text-bg-dark bg-gradient">
                <span class="info-box-icon"><?= admin_icon('plugins') ?></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?= e(__('dashboard.enabled_plugins')) ?></span>
                    <span class="info-box-number"><?= e((string)$stats['plugins']) ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

    <section class="card dashboard-recent-panel">
        <h2><?= e(__('dashboard.recently_updated_slides')) ?></h2>
        <?php if (!$recentSlides): ?>
            <p class="text-body-secondary muted"><?= e(__('dashboard.no_slides')) ?></p>
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
