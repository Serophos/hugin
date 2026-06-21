<?php
$title = __('channel.plural');
$defaultScheduleId = (int)($defaultScheduleId ?? 0);
$playlistAddTargets = [];
foreach ($groups as $group) {
    $display = $group['display'];
    if (!empty($display['is_unused'])) {
        continue;
    }

    $displayId = (int)$display['id'];
    $query = http_build_query([
        'prefill_display_id' => $displayId,
        'prefill_schedule_id' => $defaultScheduleId,
    ]);
    $target = [
        'displayId' => $displayId,
        'displayName' => (string)$display['name'],
        'createUrl' => url('/admin/playlists/create?' . $query),
        'emptyMessage' => __('channel.add_existing_playlist_empty'),
        'channels' => [],
    ];

    foreach ($group['available_channels'] ?? [] as $channel) {
        $channelQuery = http_build_query([
            'prefill_display_id' => $displayId,
            'prefill_schedule_id' => $defaultScheduleId,
        ]);
        $slideCount = (int)($channel['slide_count'] ?? 0);
        $statusLabel = !empty($channel['is_active']) ? __('common.active') : __('common.inactive');
        $meta = __('slide.slide_count', ['count' => $slideCount]) . ' · ' . $statusLabel;
        $target['channels'][] = [
            'id' => (int)$channel['channel_id'],
            'name' => (string)$channel['channel_name'],
            'meta' => $meta,
            'searchText' => trim((string)$channel['channel_name'] . ' ' . $meta),
            'url' => url('/admin/playlists/' . (int)$channel['channel_id'] . '/edit?' . $channelQuery),
        ];
    }

    $playlistAddTargets[(string)$displayId] = $target;
}
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-actions">
    <a class="button button--default" href="<?= e(url('/admin/playlists/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('channel.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php foreach ($groups as $group): ?>
<?php
$display = $group['display'];
$isUnused = !empty($display['is_unused']);
$displaySyncEnabled = !$isUnused && !empty($display['group_sync_enabled']);
$previewPath = $isUnused ? '' : '/display/' . $display['slug'];
$createPlaylistPath = '';
if (!$isUnused) {
    $createPlaylistPath = '/admin/playlists/create?' . http_build_query([
        'prefill_display_id' => (int)$display['id'],
        'prefill_schedule_id' => $defaultScheduleId,
    ]);
}
?>
<details class="card playlist-display-group">
    <summary>
        <span class="playlist-display-group__summary">
            <span class="playlist-display-group__chevron" aria-hidden="true"></span>
            <span class="playlist-display-group__icon" aria-hidden="true">
                <?php if (!$isUnused && !empty($display['icon_url'])): ?>
                    <img src="<?= e($display['icon_url']) ?>" alt="" loading="lazy">
                    <?php if ($displaySyncEnabled): ?>
                        <span class="display-sync-indicator display-sync-indicator--playlist"><?= admin_icon('reload') ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="playlist-display-group__icon-placeholder">-</span>
                <?php endif; ?>
            </span>
            <?php if ($displaySyncEnabled): ?>
                <span class="sr-only"><?= e(__('display_groups.sync_enabled_indicator')) ?></span>
            <?php endif; ?>
            <span class="playlist-display-group__copy">
                <span class="playlist-display-group__name"><?= e($display['name']) ?></span>
                <span class="playlist-display-group__meta">
                    <span><strong><?= e(__('locations.singular')) ?>:</strong> <?= e($display['location_name']) ?></span>
                    <span><strong><?= e(__('display_groups.singular')) ?>:</strong> <?= e($display['group_name']) ?></span>
                </span>
            </span>
        </span>
        <?php if (!$isUnused): ?>
            <span class="playlist-display-group__actions">
                <a class="button button--normal button--small" href="<?= e(url($previewPath)) ?>" target="_blank" rel="noopener noreferrer"><?= admin_icon('preview') ?><span><?= e(__('common.preview')) ?></span></a>
                <button type="button" class="button button--normal button--small" data-playlist-add-open data-display-id="<?= e((string)$display['id']) ?>" aria-label="<?= e(__('channel.add_existing_playlist')) ?>"><?= admin_icon('add') ?><span><?= e(__('channel.add_existing_playlist_short')) ?></span></button>
                <a class="button button--default button--small" href="<?= e(url($createPlaylistPath)) ?>" aria-label="<?= e(__('channel.add_new_playlist')) ?>"><?= admin_icon('add') ?><span><?= e(__('channel.add_new_playlist_short')) ?></span></a>
            </span>
        <?php endif; ?>
    </summary>
    <div class="playlist-display-group__body">
        <div class="table-scroll">
            <table class="admin-table admin-table--playlists" data-admin-table>
                <thead>
                <tr>
                    <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
                    <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="schedule" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('schedule.singular')])) ?>"><?= e(__('schedule.singular')) ?></button></th>
                    <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="priority" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('channel.priority')])) ?>"><?= e(__('channel.priority')) ?></button></th>
                    <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="effect" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.effect')])) ?>"><?= e(__('common.effect')) ?></button></th>
                    <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="slides" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('channel.slides_count')])) ?>"><?= e(__('channel.slides_count')) ?></button></th>
                    <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="status" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.status')])) ?>"><?= e(__('common.status')) ?></button></th>
                    <th><?= e(__('common.actions')) ?></th>
                </tr>
                <tr class="slide-library-filter-row">
                    <th><input type="search" data-admin-filter="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
                    <th><input type="search" data-admin-filter="schedule" aria-label="<?= e(__('slide.filter_column', ['column' => __('schedule.singular')])) ?>" placeholder="<?= e(__('schedule.singular')) ?>"></th>
                    <th><input type="search" data-admin-filter="priority" aria-label="<?= e(__('slide.filter_column', ['column' => __('channel.priority')])) ?>" placeholder="<?= e(__('channel.priority')) ?>"></th>
                    <th><input type="search" data-admin-filter="effect" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.effect')])) ?>" placeholder="<?= e(__('common.effect')) ?>"></th>
                    <th><input type="search" data-admin-filter="slides" aria-label="<?= e(__('slide.filter_column', ['column' => __('channel.slides_count')])) ?>" placeholder="<?= e(__('channel.slides_count')) ?>"></th>
                    <th>
                        <select data-admin-filter="status" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.status')])) ?>">
                            <option value=""><?= e(__('slide.filter_all_statuses')) ?></option>
                            <option value="active"><?= e(__('common.active')) ?></option>
                            <option value="inactive"><?= e(__('common.inactive')) ?></option>
                        </select>
                    </th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
        <?php foreach ($group['channels'] as $channel): ?>
            <?php
            $scheduleLabel = $channel['schedule_name'] ?: __('common.none');
            $priorityLabel = $channel['priority'] !== null ? (string)$channel['priority'] : __('common.none');
            $prioritySort = $channel['priority'] !== null ? (string)$channel['priority'] : '';
            $effectLabel = enum_label('effects', $channel['transition_effect'], $channel['transition_effect']);
            $statusValue = $channel['is_active'] ? 'active' : 'inactive';
            $statusLabel = $channel['is_active'] ? __('common.active') : __('common.inactive');
            ?>
            <tr data-admin-row>
                <td data-admin-cell="name" data-sort-value="<?= e((string)$channel['channel_name']) ?>" data-filter-value="<?= e((string)$channel['channel_name']) ?>"><?= e($channel['channel_name']) ?></td>
                <td data-admin-cell="schedule" data-sort-value="<?= e($scheduleLabel) ?>" data-filter-value="<?= e($scheduleLabel) ?>"><?= e($scheduleLabel) ?></td>
                <td data-admin-cell="priority" data-sort-value="<?= e($prioritySort) ?>" data-filter-value="<?= e($priorityLabel) ?>"><?= e($priorityLabel) ?></td>
                <td data-admin-cell="effect" data-sort-value="<?= e($effectLabel) ?>" data-filter-value="<?= e($effectLabel) ?>"><?= e($effectLabel) ?></td>
                <td data-admin-cell="slides" data-sort-value="<?= e((string)$channel['slide_count']) ?>" data-filter-value="<?= e((string)$channel['slide_count']) ?>"><?= e((string)$channel['slide_count']) ?></td>
                <td data-admin-cell="status" data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
                <td class="actions">
                    <a class="button button--normal button--small" href="<?= e(url('/admin/playlists/' . $channel['channel_id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                    <form method="post" action="<?= e(url('/admin/playlists/' . $channel['channel_id'] . '/delete')) ?>" class="inline-form" data-dialog-submit data-dialog-title="<?= e(__('common.delete')) ?>" data-dialog-message="<?= e(__('channel.delete_confirm')) ?>" data-dialog-icon="trash" data-dialog-buttons="cancel,delete" data-dialog-accept="<?= e(__('common.delete')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</details>
<?php endforeach; ?>

<?php if ($playlistAddTargets !== []): ?>
<dialog class="admin-dialog playlist-add-dialog" data-playlist-add-dialog aria-labelledby="playlist-add-title" aria-describedby="playlist-add-description">
    <form method="dialog" class="admin-dialog__panel playlist-add-dialog__panel">
        <div class="section-head">
            <div>
                <h2 id="playlist-add-title" data-playlist-add-title><?= e(__('channel.add_existing_playlist_title')) ?></h2>
                <p id="playlist-add-description" class="muted" data-playlist-add-display></p>
            </div>
            <button type="button" class="button button--normal button--small" data-playlist-add-close><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></button>
        </div>
        <label class="full-width"><?= e(__('channel.add_existing_playlist_filter')) ?>
            <input type="search" data-playlist-add-search placeholder="<?= e(__('channel.add_existing_playlist_filter_placeholder')) ?>">
        </label>
        <div class="playlist-add-list" data-playlist-add-list></div>
        <p class="muted playlist-add-empty" data-playlist-add-empty hidden></p>
        <div class="form-actions">
            <button type="button" class="button button--normal" data-playlist-add-close><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></button>
            <a class="button button--default" href="<?= e(url('/admin/playlists/create')) ?>" data-playlist-add-new><?= admin_icon('add') ?><span><?= e(__('channel.add_new_playlist')) ?></span></a>
        </div>
    </form>
</dialog>
<script>
(() => {
    const targets = <?= json_encode($playlistAddTargets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const dialog = document.querySelector('[data-playlist-add-dialog]');
    if (!dialog) return;

    const displayLabel = dialog.querySelector('[data-playlist-add-display]');
    const searchInput = dialog.querySelector('[data-playlist-add-search]');
    const list = dialog.querySelector('[data-playlist-add-list]');
    const empty = dialog.querySelector('[data-playlist-add-empty]');
    const addNew = dialog.querySelector('[data-playlist-add-new]');
    let activeTarget = null;
    let opener = null;

    function renderList() {
        if (!activeTarget) return;
        const query = (searchInput?.value || '').trim().toLowerCase();
        const channels = (activeTarget.channels || []).filter((channel) => {
            return !query || String(channel.searchText || '').toLowerCase().includes(query);
        });

        list.innerHTML = '';
        empty.hidden = channels.length > 0;
        empty.textContent = activeTarget.emptyMessage || '';

        channels.forEach((channel) => {
            const item = document.createElement('a');
            item.className = 'playlist-add-item';
            item.href = channel.url;

            const name = document.createElement('strong');
            name.textContent = channel.name;
            const meta = document.createElement('small');
            meta.textContent = channel.meta;

            item.append(name, meta);
            list.appendChild(item);
        });
    }

    document.querySelectorAll('[data-playlist-add-open]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            activeTarget = targets[String(button.dataset.displayId || '')];
            if (!activeTarget) return;
            opener = button;

            displayLabel.textContent = activeTarget.displayName || '';
            addNew.href = activeTarget.createUrl || addNew.href;
            if (searchInput) searchInput.value = '';
            renderList();

            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }
            window.setTimeout(() => searchInput?.focus({preventScroll: true}), 0);
        });
    });

    document.querySelectorAll('.playlist-display-group__actions a, .playlist-display-group__actions button').forEach((control) => {
        control.addEventListener('click', (event) => event.stopPropagation());
    });

    searchInput?.addEventListener('input', renderList);
    dialog.querySelectorAll('[data-playlist-add-close]').forEach((button) => {
        button.addEventListener('click', () => {
            if (typeof dialog.close === 'function' && dialog.open) {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
                opener?.focus?.({ preventScroll: true });
                opener = null;
            }
        });
    });
    dialog.addEventListener('close', () => {
        opener?.focus?.({ preventScroll: true });
        opener = null;
    });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
