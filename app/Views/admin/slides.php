<?php
$title = __('slide.plural');
$allSlides = $allSlides ?? [];
$groups = $groups ?? [];
$pluginLabels = $pluginLabels ?? [];
$renderSourceCell = static function (array $slide, array $pluginLabels): void {
    if (isset($pluginLabels[$slide['slide_type']])) {
        echo e(__('slide.plugin_configuration'));
        return;
    }

    if (!empty($slide['media_name'])) {
        echo e($slide['media_name']);
        return;
    }

    if (!empty($slide['source_url'])) {
        ?><a href="<?= e($slide['source_url']) ?>" target="_blank"><?= e(__('slide.open_source')) ?></a><?php
        return;
    }

    echo '—';
};
$sourceLabel = static function (array $slide, array $pluginLabels): string {
    if (isset($pluginLabels[$slide['slide_type']])) {
        return __('slide.plugin_configuration');
    }
    if (!empty($slide['media_name'])) {
        return (string)$slide['media_name'];
    }
    if (!empty($slide['source_url'])) {
        return (string)$slide['source_url'];
    }
    return '—';
};
$slidePickerItems = array_map(static function (array $slide) use ($pluginLabels, $sourceLabel): array {
    return [
        'id' => (int)$slide['id'],
        'name' => (string)$slide['name'],
        'type' => $pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', (string)$slide['slide_type'], (string)$slide['slide_type']),
        'source' => $sourceLabel($slide, $pluginLabels),
        'channels' => (string)($slide['channel_names'] ?: __('slide.no_channels')),
        'is_active' => (int)$slide['is_active'] === 1,
        'status' => (int)$slide['is_active'] === 1 ? __('common.active') : __('common.inactive'),
    ];
}, $allSlides);

require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-head">
    <div><h1><?= e(__('slide.plural')) ?></h1><p class="muted"><?= e(__('slide.overview_hint')) ?></p></div>
    <a class="button button--default" href="<?= e(url('/admin/slides/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('slide.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

<section class="slide-workspace-section">
    <div class="section-head">
        <div>
            <h2><?= e(__('slide.channel_playlists')) ?></h2>
            <p class="muted"><?= e(__('slide.channel_playlists_hint')) ?></p>
        </div>
    </div>

    <div class="slide-groups">
    <?php if ($groups === []): ?>
        <div class="card">
            <p class="muted"><?= e(__('slide.no_channels_configured')) ?></p>
            <a class="button button--default" href="<?= e(url('/admin/channels/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('channel.new')) ?></span></a>
        </div>
    <?php endif; ?>
    <?php foreach ($groups as $group): ?>
    <?php
        $channelAnchor = 'channel-' . (int)$group['channel_id'];
        $returnTo = '/admin/slides#' . $channelAnchor;
        $createUrl = '/admin/slides/create?channel_id=' . (int)$group['channel_id'] . '&return_to=' . rawurlencode($returnTo);
    ?>
    <details class="card slide-group" id="<?= e($channelAnchor) ?>" open>
        <summary>
            <span class="slide-group__title">
                <span class="slide-group__chevron" aria-hidden="true"></span>
                <span>
                    <h2><?= e($group['channel_name']) ?></h2>
                    <small><?= e(__('slide.slide_count', ['count' => count($group['slides'])])) ?></small>
                    <?php if (isset($group['is_active']) && (int)$group['is_active'] !== 1): ?>
                        <small><?= e(__('common.inactive')) ?></small>
                    <?php endif; ?>
                </span>
            </span>
        </summary>
        <div class="slide-group__body">
            <div class="slide-group__toolbar">
                <button
                    type="button"
                    class="button button--normal button--small"
                    data-open-slide-picker
                    data-channel-id="<?= e((string)$group['channel_id']) ?>"
                    data-channel-name="<?= e($group['channel_name']) ?>"
                    data-action="<?= e(url('/admin/channels/' . $group['channel_id'] . '/slides/add')) ?>"
                    data-return-to="<?= e($returnTo) ?>"
                    data-assigned-slide-ids="<?= e(json_encode(array_values(array_unique($group['slide_ids'] ?? [])), JSON_UNESCAPED_SLASHES)) ?>"
                ><?= admin_icon('add') ?><span><?= e(__('slide.add_existing_to_channel')) ?></span></button>
                <a class="button button--default button--small" href="<?= e(url($createUrl)) ?>"><?= admin_icon('add') ?><span><?= e(__('slide.create_in_channel')) ?></span></a>
            </div>
            <?php if ($group['slides'] === []): ?>
                <p class="muted slide-group__empty"><?= e(__('slide.channel_empty')) ?></p>
            <?php else: ?>
                <table>
                    <thead><tr><th class="handle-col"></th><th><?= e(__('common.name')) ?></th><th><?= e(__('common.type')) ?></th><th><?= e(__('common.source')) ?></th><th><?= e(__('common.duration')) ?></th><th><?= e(__('common.status')) ?></th><th></th></tr></thead>
                    <tbody class="sortable-list" data-sort-endpoint="<?= e(url('/admin/sort/slides')) ?>" data-extra-name="channel_id" data-extra-value="<?= e((string)$group['channel_id']) ?>">
                    <?php foreach ($group['slides'] as $slide): ?>
                        <tr draggable="true" data-id="<?= e((string)$slide['id']) ?>">
                            <td class="handle">↕</td>
                            <td><?= e($slide['name']) ?></td>
                            <td><?= e($pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', $slide['slide_type'], $slide['slide_type'])) ?></td>
                            <td class="truncate"><?php $renderSourceCell($slide, $pluginLabels); ?></td>
                            <td><?= e((string)($slide['duration_seconds'] ?? __('common.default'))) ?></td>
                            <td><?= e($slide['is_active'] ? __('common.active') : __('common.inactive')) ?></td>
                            <td class="actions">
                                <a class="button button--normal button--small" href="<?= e(url('/admin/slides/' . $slide['id'] . '/edit?return_to=' . rawurlencode($returnTo))) ?>"><?= admin_icon('edit') ?><span><?= e(__('slide.edit_content')) ?></span></a>
                                <form
                                    method="post"
                                    action="<?= e(url('/admin/channels/' . $group['channel_id'] . '/slides/' . $slide['id'] . '/remove')) ?>"
                                    class="inline-form"
                                    data-confirm-submit
                                    data-confirm-title="<?= e(__('slide.remove_from_channel')) ?>"
                                    data-confirm-message="<?= e(__('slide.remove_from_channel_confirm', ['slide' => $slide['name'], 'channel' => $group['channel_name']])) ?>"
                                    data-confirm-accept="<?= e(__('slide.remove_from_channel')) ?>"
                                >
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                                    <button type="submit" class="button button--danger button--small"><?= admin_icon('remove') ?><span><?= e(__('slide.remove_from_channel')) ?></span></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </details>
    <?php endforeach; ?>
    </div>
</section>

<section class="slide-workspace-section">
    <div class="section-head">
        <div>
            <h2><?= e(__('slide.library')) ?></h2>
            <p class="muted"><?= e(__('slide.library_hint')) ?></p>
        </div>
    </div>
    <details class="card slide-group" open>
        <summary>
            <span class="slide-group__title">
                <span class="slide-group__chevron" aria-hidden="true"></span>
                <span>
                    <h2><?= e(__('slide.all_slides')) ?></h2>
                    <small><?= e(__('slide.unique_slide_count', ['count' => count($allSlides)])) ?></small>
                </span>
            </span>
            <span class="slide-group__hint"><?= e(__('slide.all_slides_hint')) ?></span>
        </summary>
        <div class="slide-group__body">
            <?php if ($allSlides === []): ?>
                <p class="muted slide-group__empty"><?= e(__('slide.no_slides')) ?></p>
            <?php else: ?>
                <table>
                    <thead><tr><th><?= e(__('common.name')) ?></th><th><?= e(__('common.type')) ?></th><th><?= e(__('slide.assigned_channel_names')) ?></th><th><?= e(__('common.source')) ?></th><th><?= e(__('common.duration')) ?></th><th><?= e(__('common.status')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($allSlides as $slide): ?>
                        <tr>
                            <td><?= e($slide['name']) ?></td>
                            <td><?= e($pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', $slide['slide_type'], $slide['slide_type'])) ?></td>
                            <td><?= e($slide['channel_names'] ?: __('slide.no_channels')) ?></td>
                            <td class="truncate"><?php $renderSourceCell($slide, $pluginLabels); ?></td>
                            <td><?= e((string)($slide['duration_seconds'] ?? __('common.default'))) ?></td>
                            <td><?= e($slide['is_active'] ? __('common.active') : __('common.inactive')) ?></td>
                            <td class="actions">
                                <a class="button button--normal button--small" href="<?= e(url('/admin/slides/' . $slide['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('slide.edit_content')) ?></span></a>
                                <form
                                    method="post"
                                    action="<?= e(url('/admin/slides/' . $slide['id'] . '/delete')) ?>"
                                    class="inline-form"
                                    data-confirm-submit
                                    data-confirm-title="<?= e(__('slide.delete_everywhere')) ?>"
                                    data-confirm-message="<?= e(__('slide.delete_everywhere_confirm', ['slide' => $slide['name'], 'count' => (int)($slide['channel_count'] ?? 0)])) ?>"
                                    data-confirm-accept="<?= e(__('slide.delete_everywhere')) ?>"
                                >
                                    <?= csrf_field() ?>
                                    <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('slide.delete_everywhere')) ?></span></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </details>
</section>

<dialog class="admin-dialog" data-confirm-dialog>
    <form method="dialog" class="admin-dialog__panel">
        <h2 data-confirm-dialog-title></h2>
        <p class="muted" data-confirm-dialog-message></p>
        <div class="form-actions">
            <button type="button" class="button button--normal" data-confirm-cancel><?= admin_icon('cancel') ?><span><?= e(__('common.no')) ?></span></button>
            <button type="button" class="button button--danger" data-confirm-accept><?= admin_icon('delete') ?><span><?= e(__('common.yes')) ?></span></button>
        </div>
    </form>
</dialog>

<dialog class="admin-dialog slide-picker-dialog" data-slide-picker-dialog>
    <form method="post" class="admin-dialog__panel form-grid" data-slide-picker-form>
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" data-slide-picker-return-to>
        <div class="section-head">
            <div>
                <h2 data-slide-picker-title></h2>
                <p class="muted"><?= e(__('slide.add_existing_hint')) ?></p>
            </div>
            <button type="button" class="button button--normal button--small" data-slide-picker-close><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></button>
        </div>
        <label class="full-width"><?= e(__('common.name')) ?>
            <input type="search" data-slide-picker-search placeholder="<?= e(__('slide.search_library_placeholder')) ?>">
        </label>
        <div class="slide-picker-list" data-slide-picker-list></div>
        <p class="muted" data-slide-picker-empty hidden><?= e(__('slide.add_existing_empty')) ?></p>
        <div class="form-actions">
            <button type="button" class="button button--normal" data-slide-picker-close><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></button>
            <button type="submit" class="button button--default" data-slide-picker-submit><?= admin_icon('add') ?><span><?= e(__('slide.add_selected_to_channel')) ?></span></button>
        </div>
    </form>
</dialog>

<script>
(() => {
    const slideLibrary = <?= json_encode($slidePickerItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    const confirmDialog = document.querySelector('[data-confirm-dialog]');
    let pendingConfirmForm = null;
    if (confirmDialog && typeof confirmDialog.showModal === 'function') {
        const title = confirmDialog.querySelector('[data-confirm-dialog-title]');
        const message = confirmDialog.querySelector('[data-confirm-dialog-message]');
        const accept = confirmDialog.querySelector('[data-confirm-accept]');
        const acceptLabel = accept?.querySelector('span');
        const cancelButtons = confirmDialog.querySelectorAll('[data-confirm-cancel]');

        document.querySelectorAll('[data-confirm-submit]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === '1') return;
                event.preventDefault();
                pendingConfirmForm = form;
                if (title) title.textContent = form.dataset.confirmTitle || '';
                if (message) message.textContent = form.dataset.confirmMessage || '';
                if (acceptLabel) acceptLabel.textContent = form.dataset.confirmAccept || <?= json_encode(__('common.yes'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                confirmDialog.showModal();
            });
        });

        cancelButtons.forEach((button) => {
            button.addEventListener('click', () => {
                pendingConfirmForm = null;
                confirmDialog.close();
            });
        });
        accept?.addEventListener('click', () => {
            if (!pendingConfirmForm) return;
            pendingConfirmForm.dataset.confirmed = '1';
            pendingConfirmForm.submit();
        });
    } else {
        document.querySelectorAll('[data-confirm-submit]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!window.confirm(form.dataset.confirmMessage || '')) {
                    event.preventDefault();
                }
            });
        });
    }

    const pickerDialog = document.querySelector('[data-slide-picker-dialog]');
    if (!pickerDialog || typeof pickerDialog.showModal !== 'function') return;

    const pickerForm = pickerDialog.querySelector('[data-slide-picker-form]');
    const pickerTitle = pickerDialog.querySelector('[data-slide-picker-title]');
    const pickerReturnTo = pickerDialog.querySelector('[data-slide-picker-return-to]');
    const pickerSearch = pickerDialog.querySelector('[data-slide-picker-search]');
    const pickerList = pickerDialog.querySelector('[data-slide-picker-list]');
    const pickerEmpty = pickerDialog.querySelector('[data-slide-picker-empty]');
    const pickerSubmit = pickerDialog.querySelector('[data-slide-picker-submit]');
    if (!pickerForm || !pickerReturnTo || !pickerList || !pickerEmpty) return;
    let currentAvailableSlides = [];

    function updatePickerSubmit() {
        if (!pickerSubmit) return;
        pickerSubmit.disabled = !pickerList.querySelector('input[type="checkbox"]:checked');
    }

    function renderPickerList() {
        const query = (pickerSearch?.value || '').trim().toLowerCase();
        const visibleSlides = currentAvailableSlides.filter((slide) => {
            const haystack = `${slide.name} ${slide.type} ${slide.source} ${slide.channels}`.toLowerCase();
            return haystack.includes(query);
        });

        pickerList.innerHTML = '';
        pickerEmpty.hidden = visibleSlides.length > 0;
        updatePickerSubmit();
        visibleSlides.forEach((slide) => {
            const label = document.createElement('label');
            label.className = 'slide-picker-item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'slide_ids[]';
            checkbox.value = String(slide.id);

            const copy = document.createElement('span');
            copy.className = 'slide-picker-item__copy';
            const title = document.createElement('strong');
            title.textContent = slide.name;
            const meta = document.createElement('small');
            meta.textContent = `${slide.type} · ${slide.channels} · ${slide.status}`;
            const source = document.createElement('small');
            source.textContent = slide.source;
            copy.append(title, meta, source);

            label.append(checkbox, copy);
            pickerList.appendChild(label);
        });
        updatePickerSubmit();
    }

    document.querySelectorAll('[data-open-slide-picker]').forEach((button) => {
        button.addEventListener('click', () => {
            let assignedSlideIds = [];
            try {
                assignedSlideIds = JSON.parse(button.dataset.assignedSlideIds || '[]');
            } catch {
                assignedSlideIds = [];
            }
            const assigned = new Set(assignedSlideIds.map(String));
            currentAvailableSlides = slideLibrary.filter((slide) => !assigned.has(String(slide.id)));
            pickerForm.action = button.dataset.action || '';
            pickerReturnTo.value = button.dataset.returnTo || '/admin/slides';
            if (pickerTitle) pickerTitle.textContent = <?= json_encode(__('slide.add_existing_title'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>.replace(':channel', button.dataset.channelName || '');
            if (pickerSearch) pickerSearch.value = '';
            renderPickerList();
            pickerDialog.showModal();
            pickerSearch?.focus();
        });
    });

    pickerSearch?.addEventListener('input', renderPickerList);
    pickerList.addEventListener('change', updatePickerSubmit);
    pickerForm.addEventListener('submit', (event) => {
        if (!pickerList.querySelector('input[type="checkbox"]:checked')) {
            event.preventDefault();
            updatePickerSubmit();
        }
    });
    pickerDialog.querySelectorAll('[data-slide-picker-close]').forEach((button) => {
        button.addEventListener('click', () => pickerDialog.close());
    });
})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
