<?php
$title = __('slide.plural');
$allSlides = $allSlides ?? [];
$groups = $groups ?? [];
$pluginLabels = $pluginLabels ?? [];
$slideTypeDefinitions = array_values($slideTypeDefinitions ?? []);
$fallbackSlideType = [
    'slide_type' => 'image',
    'label' => enum_label('slide_types', 'image', 'image'),
    'description' => __('slide.type_descriptions.image'),
    'icon_url' => url('/assets/img/slides/slide_generic.png'),
    'icon_fallback_url' => url('/assets/img/slides/slide_generic.png'),
];
$slideTypeDefinitions = $slideTypeDefinitions ?: [$fallbackSlideType];
$firstSlideType = $slideTypeDefinitions[0] ?? $fallbackSlideType;
$firstSlideTypeCreateUrl = url('/admin/slides/create?slide_type=' . rawurlencode((string)$firstSlideType['slide_type']) . '&return_to=' . rawurlencode('/admin/slides'));
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
    <a class="button button--default" href="<?= e($firstSlideTypeCreateUrl) ?>" data-open-slide-type-dialog data-create-url="<?= e(url('/admin/slides/create')) ?>" data-return-to="/admin/slides" aria-haspopup="dialog"><?= admin_icon('add') ?><span><?= e(__('slide.new')) ?></span></a>
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
            <a class="button button--default" href="<?= e(url('/admin/playlists/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('channel.new')) ?></span></a>
        </div>
    <?php endif; ?>
    <?php foreach ($groups as $group): ?>
    <?php
        $channelAnchor = 'channel-' . (int)$group['channel_id'];
        $returnTo = '/admin/slides#' . $channelAnchor;
        $createUrl = '/admin/slides/create?channel_id=' . (int)$group['channel_id'] . '&return_to=' . rawurlencode($returnTo);
    ?>
    <details class="card slide-group" id="<?= e($channelAnchor) ?>">
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
                    data-action="<?= e(url('/admin/playlists/' . $group['channel_id'] . '/slides/add')) ?>"
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
                                    action="<?= e(url('/admin/playlists/' . $group['channel_id'] . '/slides/' . $slide['id'] . '/remove')) ?>"
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
                <div class="slide-library-toolbar">
                    <span class="slide-library-toolbar__meta" data-slide-library-count data-template="<?= e(__('slide.library_filter_count', ['visible' => '__VISIBLE__', 'total' => '__TOTAL__'])) ?>" aria-live="polite"></span>
                    <button type="button" class="button button--normal button--small" data-slide-library-reset hidden><?= e(__('slide.clear_filters')) ?></button>
                </div>
                <div class="table-scroll">
                    <table class="slide-library-table" data-slide-library-table>
                        <thead>
                            <tr>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-sort-key="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-sort-key="type" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.type')])) ?>"><?= e(__('common.type')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-sort-key="channels" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('slide.assigned_channel_names')])) ?>"><?= e(__('slide.assigned_channel_names')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-sort-key="duration" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.duration')])) ?>"><?= e(__('common.duration')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-sort-key="status" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.status')])) ?>"><?= e(__('common.status')) ?></button></th>
                                <th><?= e(__('common.actions')) ?></th>
                            </tr>
                            <tr class="slide-library-filter-row">
                                <th><input type="search" data-filter-key="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
                                <th>
                                    <select data-filter-key="type" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.type')])) ?>">
                                        <option value=""><?= e(__('slide.filter_all_types')) ?></option>
                                        <?php foreach ($slideTypeOptions as $typeLabel): ?>
                                            <option value="<?= e($typeLabel) ?>"><?= e($typeLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                                <th><input type="search" data-filter-key="channels" aria-label="<?= e(__('slide.filter_column', ['column' => __('slide.assigned_channel_names')])) ?>" placeholder="<?= e(__('slide.assigned_channel_names')) ?>"></th>
                                <th><input type="search" data-filter-key="duration" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.duration')])) ?>" placeholder="<?= e(__('common.duration')) ?>"></th>
                                <th>
                                    <select data-filter-key="status" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.status')])) ?>">
                                        <option value=""><?= e(__('slide.filter_all_statuses')) ?></option>
                                        <option value="active"><?= e(__('common.active')) ?></option>
                                        <option value="inactive"><?= e(__('common.inactive')) ?></option>
                                    </select>
                                </th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($allSlides as $slide): ?>
                            <?php
                            $typeLabel = $pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', (string)$slide['slide_type'], (string)$slide['slide_type']);
                            $channelLabel = $slide['channel_names'] ?: __('slide.no_channels');
                            $durationSort = $slide['duration_seconds'] ?? '';
                            $durationLabel = (string)($slide['duration_seconds'] ?? __('common.default'));
                            $statusValue = $slide['is_active'] ? 'active' : 'inactive';
                            $statusLabel = $slide['is_active'] ? __('common.active') : __('common.inactive');
                            ?>
                            <tr data-slide-library-row>
                                <td data-library-cell="name" data-sort-value="<?= e((string)$slide['name']) ?>" data-filter-value="<?= e((string)$slide['name']) ?>"><?= e($slide['name']) ?></td>
                                <td data-library-cell="type" data-sort-value="<?= e($typeLabel) ?>" data-filter-value="<?= e($typeLabel) ?>"><?= e($typeLabel) ?></td>
                                <td data-library-cell="channels" data-sort-value="<?= e($channelLabel) ?>" data-filter-value="<?= e($channelLabel) ?>"><?= e($channelLabel) ?></td>
                                <td data-library-cell="duration" data-sort-value="<?= e((string)$durationSort) ?>" data-filter-value="<?= e($durationLabel) ?>"><?= e($durationLabel) ?></td>
                                <td data-library-cell="status" data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
                                <td class="actions">
                                    <a class="button button--normal button--small" href="<?= e(url('/admin/slides/' . $slide['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                                    <form
                                        method="post"
                                        action="<?= e(url('/admin/slides/' . $slide['id'] . '/delete')) ?>"
                                        class="inline-form"
                                        data-confirm-submit
                                        data-confirm-title="<?= e(__('common.delete')) ?>"
                                        data-confirm-message="<?= e(__('slide.delete_everywhere_confirm', ['slide' => $slide['name'], 'count' => (int)($slide['channel_count'] ?? 0)])) ?>"
                                        data-confirm-accept="<?= e(__('common.delete')) ?>"
                                    >
                                        <?= csrf_field() ?>
                                        <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                                    </form>
                                    <a class="button button--normal button--small" target="_blank" rel="noopener noreferrer" href="<?= e(url('/preview-slide/' . $slide['id'])) ?>"><?= admin_icon('preview') ?><span><?= e(__('common.preview')) ?></span></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="muted slide-group__empty" data-slide-library-empty hidden><?= e(__('slide.library_filter_empty')) ?></p>
            <?php endif; ?>
        </div>
    </details>
</section>

<dialog class="admin-dialog slide-type-dialog" data-slide-type-dialog>
    <form method="dialog" class="admin-dialog__panel slide-type-dialog__panel">
        <div class="section-head">
            <div>
                <h2><?= e(__('slide.choose_type_title')) ?></h2>
                <p class="muted"><?= e(__('slide.choose_type_hint')) ?></p>
            </div>
            <button type="button" class="button button--normal button--small" data-slide-type-close><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></button>
        </div>
        <div class="slide-type-dialog__layout">
            <div class="slide-type-grid" data-slide-type-options>
                <?php foreach ($slideTypeDefinitions as $index => $slideType): ?>
                    <?php
                    $description = trim((string)($slideType['description'] ?? ''));
                    if ($description === '') {
                        $description = __('slide.type_description_unavailable');
                    }
                    ?>
                    <button
                        type="button"
                        class="slide-type-card"
                        data-slide-type-option
                        data-slide-type="<?= e((string)$slideType['slide_type']) ?>"
                        data-label="<?= e((string)$slideType['label']) ?>"
                        data-description="<?= e($description) ?>"
                        data-icon="<?= e((string)$slideType['icon_url']) ?>"
                        data-fallback-icon="<?= e((string)$slideType['icon_fallback_url']) ?>"
                        aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                    >
                        <span class="slide-type-card__icon">
                            <img src="<?= e((string)$slideType['icon_url']) ?>" data-fallback-icon="<?= e((string)$slideType['icon_fallback_url']) ?>" alt="">
                        </span>
                        <span class="slide-type-card__name"><?= e((string)$slideType['label']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <aside class="slide-type-detail" data-slide-type-detail>
                <img class="slide-type-detail__icon" src="<?= e((string)$firstSlideType['icon_url']) ?>" data-slide-type-detail-icon data-fallback-icon="<?= e((string)$firstSlideType['icon_fallback_url']) ?>" alt="">
                <div>
                    <h3 data-slide-type-detail-title><?= e((string)$firstSlideType['label']) ?></h3>
                    <p class="muted" data-slide-type-detail-description><?= e((string)(($firstSlideType['description'] ?? '') ?: __('slide.type_description_unavailable'))) ?></p>
                </div>
            </aside>
        </div>
        <div class="form-actions">
            <button type="button" class="button button--normal" data-slide-type-close><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></button>
            <a class="button button--default" href="<?= e($firstSlideTypeCreateUrl) ?>" data-slide-type-continue><?= admin_icon('add') ?><span><?= e(__('slide.create_selected_type')) ?></span></a>
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

    const slideTypeDialog = document.querySelector('[data-slide-type-dialog]');
    const openSlideTypeDialog = document.querySelector('[data-open-slide-type-dialog]');
    if (slideTypeDialog && openSlideTypeDialog && typeof slideTypeDialog.showModal === 'function') {
        const slideTypeOptions = Array.from(slideTypeDialog.querySelectorAll('[data-slide-type-option]'));
        const detailIcon = slideTypeDialog.querySelector('[data-slide-type-detail-icon]');
        const detailTitle = slideTypeDialog.querySelector('[data-slide-type-detail-title]');
        const detailDescription = slideTypeDialog.querySelector('[data-slide-type-detail-description]');
        const continueLink = slideTypeDialog.querySelector('[data-slide-type-continue]');
        const fallbackDescription = <?= json_encode(__('slide.type_description_unavailable'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let selectedSlideTypeOption = slideTypeOptions.find((option) => option.getAttribute('aria-pressed') === 'true') || slideTypeOptions[0] || null;

        function buildSlideTypeUrl(slideType) {
            const target = new URL(openSlideTypeDialog.dataset.createUrl || openSlideTypeDialog.href, window.location.href);
            target.searchParams.set('slide_type', slideType);
            target.searchParams.set('return_to', openSlideTypeDialog.dataset.returnTo || '/admin/slides');
            return target.toString();
        }

        function useFallbackIcon(event) {
            const image = event.currentTarget;
            if (image.dataset.fallbackApplied === '1' || !image.dataset.fallbackIcon) return;
            image.dataset.fallbackApplied = '1';
            image.src = image.dataset.fallbackIcon;
        }

        function selectSlideType(option) {
            if (!option) return;
            selectedSlideTypeOption = option;
            slideTypeOptions.forEach((item) => {
                item.setAttribute('aria-pressed', item === option ? 'true' : 'false');
            });

            const label = option.dataset.label || option.textContent.trim();
            const description = option.dataset.description || fallbackDescription;
            const icon = option.dataset.icon || option.dataset.fallbackIcon || '';
            const fallbackIcon = option.dataset.fallbackIcon || '';

            if (detailTitle) detailTitle.textContent = label;
            if (detailDescription) detailDescription.textContent = description;
            if (detailIcon && icon) {
                delete detailIcon.dataset.fallbackApplied;
                detailIcon.dataset.fallbackIcon = fallbackIcon;
                detailIcon.src = icon;
            }
            if (continueLink) {
                continueLink.href = buildSlideTypeUrl(option.dataset.slideType || 'image');
            }
        }

        slideTypeDialog.querySelectorAll('img[data-fallback-icon]').forEach((image) => {
            image.addEventListener('error', useFallbackIcon);
        });
        slideTypeOptions.forEach((option) => {
            option.addEventListener('click', () => selectSlideType(option));
            option.addEventListener('dblclick', () => {
                selectSlideType(option);
                if (continueLink) window.location.href = continueLink.href;
            });
        });
        openSlideTypeDialog.addEventListener('click', (event) => {
            event.preventDefault();
            selectSlideType(selectedSlideTypeOption);
            slideTypeDialog.showModal();
            selectedSlideTypeOption?.focus();
        });
        slideTypeDialog.querySelectorAll('[data-slide-type-close]').forEach((button) => {
            button.addEventListener('click', () => slideTypeDialog.close());
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
