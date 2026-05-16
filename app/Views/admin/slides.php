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
$slideTypeOptions = [];
foreach ($allSlides as $slide) {
    $typeLabel = $pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', (string)$slide['slide_type'], (string)$slide['slide_type']);
    $slideTypeOptions[$typeLabel] = $typeLabel;
}
natcasesort($slideTypeOptions);

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
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-sort-key="source" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.source')])) ?>"><?= e(__('common.source')) ?></button></th>
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
                                <th><input type="search" data-filter-key="source" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.source')])) ?>" placeholder="<?= e(__('common.source')) ?>"></th>
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
                            $sourceText = $sourceLabel($slide, $pluginLabels);
                            $sourceFilterText = trim($sourceText . ' ' . (!empty($slide['source_url']) ? __('slide.open_source') : ''));
                            $durationSort = $slide['duration_seconds'] ?? '';
                            $durationLabel = (string)($slide['duration_seconds'] ?? __('common.default'));
                            $statusValue = $slide['is_active'] ? 'active' : 'inactive';
                            $statusLabel = $slide['is_active'] ? __('common.active') : __('common.inactive');
                            ?>
                            <tr data-slide-library-row>
                                <td data-library-cell="name" data-sort-value="<?= e((string)$slide['name']) ?>" data-filter-value="<?= e((string)$slide['name']) ?>"><?= e($slide['name']) ?></td>
                                <td data-library-cell="type" data-sort-value="<?= e($typeLabel) ?>" data-filter-value="<?= e($typeLabel) ?>"><?= e($typeLabel) ?></td>
                                <td data-library-cell="channels" data-sort-value="<?= e($channelLabel) ?>" data-filter-value="<?= e($channelLabel) ?>"><?= e($channelLabel) ?></td>
                                <td class="truncate" data-library-cell="source" data-sort-value="<?= e($sourceText) ?>" data-filter-value="<?= e($sourceFilterText) ?>"><?php $renderSourceCell($slide, $pluginLabels); ?></td>
                                <td data-library-cell="duration" data-sort-value="<?= e((string)$durationSort) ?>" data-filter-value="<?= e($durationLabel) ?>"><?= e($durationLabel) ?></td>
                                <td data-library-cell="status" data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
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
                </div>
                <p class="muted slide-group__empty" data-slide-library-empty hidden><?= e(__('slide.library_filter_empty')) ?></p>
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
    const libraryTable = document.querySelector('[data-slide-library-table]');
    if (libraryTable) {
        const tbody = libraryTable.tBodies[0];
        const rows = Array.from(tbody?.querySelectorAll('[data-slide-library-row]') || []);
        const sortButtons = Array.from(libraryTable.querySelectorAll('[data-sort-key]'));
        const filterControls = Array.from(libraryTable.querySelectorAll('[data-filter-key]'));
        const resetFilters = document.querySelector('[data-slide-library-reset]');
        const count = document.querySelector('[data-slide-library-count]');
        const empty = document.querySelector('[data-slide-library-empty]');
        const total = rows.length;
        const sortState = { key: '', direction: 'asc' };
        const normalize = (value) => String(value || '').trim().toLocaleLowerCase();

        rows.forEach((row, index) => {
            row.dataset.originalIndex = String(index);
        });

        function cellFor(row, key) {
            return row.querySelector(`[data-library-cell="${key}"]`);
        }

        function valueFor(row, key, valueType) {
            const cell = cellFor(row, key);
            if (!cell) return '';
            return cell.dataset[valueType] || cell.textContent || '';
        }

        function compareRows(a, b) {
            if (!sortState.key) {
                return Number(a.dataset.originalIndex || 0) - Number(b.dataset.originalIndex || 0);
            }

            const sortButton = sortButtons.find((button) => button.dataset.sortKey === sortState.key);
            const sortType = sortButton?.dataset.sortType || 'text';
            const direction = sortState.direction === 'desc' ? -1 : 1;
            const aValue = valueFor(a, sortState.key, 'sortValue');
            const bValue = valueFor(b, sortState.key, 'sortValue');
            const aEmpty = String(aValue).trim() === '';
            const bEmpty = String(bValue).trim() === '';
            if (aEmpty && bEmpty) return Number(a.dataset.originalIndex || 0) - Number(b.dataset.originalIndex || 0);
            if (aEmpty) return 1;
            if (bEmpty) return -1;

            let result;
            if (sortType === 'number') {
                result = Number.parseFloat(String(aValue).replace(',', '.')) - Number.parseFloat(String(bValue).replace(',', '.'));
            } else {
                result = String(aValue).localeCompare(String(bValue), undefined, { sensitivity: 'base', numeric: true });
            }

            if (result === 0) {
                return Number(a.dataset.originalIndex || 0) - Number(b.dataset.originalIndex || 0);
            }
            return result * direction;
        }

        function rowMatchesFilters(row) {
            return filterControls.every((control) => {
                const query = normalize(control.value);
                if (!query) return true;
                const cellValue = normalize(valueFor(row, control.dataset.filterKey || '', 'filterValue'));
                if (control.tagName === 'SELECT') {
                    return cellValue === query;
                }
                return cellValue.includes(query);
            });
        }

        function updateSortHeaders() {
            sortButtons.forEach((button) => {
                const isActive = button.dataset.sortKey === sortState.key;
                const th = button.closest('th');
                if (th) {
                    th.setAttribute('aria-sort', isActive ? (sortState.direction === 'asc' ? 'ascending' : 'descending') : 'none');
                }
                button.dataset.sortDirection = isActive ? sortState.direction : '';
            });
        }

        function updateCount(visible) {
            if (!count) return;
            const template = count.dataset.template || '__VISIBLE__ / __TOTAL__';
            count.textContent = template.replace('__VISIBLE__', String(visible)).replace('__TOTAL__', String(total));
        }

        function updateLibraryTable() {
            rows.slice().sort(compareRows).forEach((row) => tbody.appendChild(row));
            let visible = 0;
            rows.forEach((row) => {
                const matches = rowMatchesFilters(row);
                row.hidden = !matches;
                if (matches) visible += 1;
            });
            if (empty) empty.hidden = visible > 0;
            if (resetFilters) resetFilters.hidden = !filterControls.some((control) => normalize(control.value) !== '');
            updateCount(visible);
            updateSortHeaders();
        }

        sortButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const nextKey = button.dataset.sortKey || '';
                if (sortState.key === nextKey) {
                    sortState.direction = sortState.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    sortState.key = nextKey;
                    sortState.direction = 'asc';
                }
                updateLibraryTable();
            });
        });

        filterControls.forEach((control) => {
            control.addEventListener('input', updateLibraryTable);
            control.addEventListener('change', updateLibraryTable);
        });

        resetFilters?.addEventListener('click', () => {
            filterControls.forEach((control) => {
                control.value = '';
            });
            updateLibraryTable();
            filterControls[0]?.focus();
        });

        updateLibraryTable();
    }

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
