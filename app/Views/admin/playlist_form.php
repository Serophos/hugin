<?php
$formId = 'channel';
$title = $channel ? __('channel.edit_title') : __('channel.create_title');
$prefillAssignment = is_array($prefillAssignment ?? null) ? $prefillAssignment : null;
if (form_has_old($formId)) {
    $displayValues = old_array('assignment_display_id', [], $formId);
    $scheduleValues = old_array('assignment_schedule_id', [], $formId);
    $priorityValues = old_array('assignment_priority', [], $formId);
    $rowCount = max(1, count($displayValues), count($scheduleValues), count($priorityValues));
    $assignmentRows = [];
    for ($i = 0; $i < $rowCount; $i++) {
        $assignmentRows[] = [
            'display_id' => $displayValues[$i] ?? '',
            'schedule_id' => $scheduleValues[$i] ?? '',
            'priority' => $priorityValues[$i] ?? '',
        ];
    }
} else {
    $assignmentRows = $assignments ?: [];
    if ($prefillAssignment !== null) {
        $assignmentRows[] = $prefillAssignment;
    }
    if ($assignmentRows === []) {
        $assignmentRows = [['display_id' => '', 'schedule_id' => '', 'priority' => '']];
    }
}
require __DIR__ . '/../layouts/admin_header.php';
?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <form method="post" action="<?= e($channel ? url('/admin/playlists/' . $channel['id'] . '/edit') : url('/admin/playlists/create')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label><?= e(__('common.name')) ?>
            <input type="text" name="name" value="<?= e((string)old('name', $channel['name'] ?? '', $formId)) ?>" placeholder="<?= e(__('channel.name_placeholder')) ?>" required<?= field_attrs('name', $formId) ?>>
            <?= field_error_html('name', $formId) ?>
        </label>
        <label><?= e(__('channel.transition_effect')) ?>
            <select name="transition_effect"<?= field_attrs('transition_effect', $formId) ?>>
                <?php foreach (['inherit','fade','slide-left','slide-right','slide-up','slide-down','zoom','flip','blur','none'] as $fx): ?>
                    <option value="<?= e($fx) ?>" <?= old_selected('transition_effect', $fx, $channel['transition_effect'] ?? 'inherit', $formId) ?>><?= e(enum_label('effects', $fx, $fx)) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error_html('transition_effect', $formId) ?>
        </label>
        <label><?= e(__('channel.custom_slide_duration')) ?>
            <input type="number" min="1" name="slide_duration_seconds" value="<?= e((string)old('slide_duration_seconds', $channel['slide_duration_seconds'] ?? '', $formId)) ?>" placeholder="<?= e(__('channel.duration_placeholder')) ?>"<?= field_attrs('slide_duration_seconds', $formId) ?>>
            <?= field_error_html('slide_duration_seconds', $formId) ?>
        </label>
        <label class="full-width"><?= e(__('common.description')) ?>
            <textarea name="description" rows="4" placeholder="<?= e(__('channel.description_placeholder')) ?>"<?= field_attrs('description', $formId) ?>><?= e((string)old('description', $channel['description'] ?? '', $formId)) ?></textarea>
            <?= field_error_html('description', $formId) ?>
        </label>
        <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= old_checked('is_active', $channel['is_active'] ?? 1, $formId) ?>> <?= e(__('common.active')) ?></label>

        <div class="full-width plugin-settings-card">
            <h3><?= e(__('channel.display_schedule_assignments')) ?></h3>
            <p class="muted"><?= e(__('channel.assignment_optional_hint')) ?></p>
            <div id="assignment-list" class="assignment-list">
                <?php foreach ($assignmentRows as $index => $assignment): ?>
                    <?php
                    $assignmentRowClasses = ['assignment-row'];
                    if (!empty($assignment['is_prefill'])) {
                        $assignmentRowClasses[] = 'assignment-row--highlight';
                    }
                    ?>
                    <div class="<?= e(implode(' ', $assignmentRowClasses)) ?>">
                        <label><?= e(__('channel.display_monitor')) ?>
                            <select name="assignment_display_id[]"<?= field_attrs('assignment_display_id.' . $index, $formId) ?>>
                                <option value=""><?= e(__('channel.display_placeholder')) ?></option>
                                <?php foreach ($displays as $display): ?>
                                    <option value="<?= e((string)$display['id']) ?>" <?= selected($assignment['display_id'] ?? '', $display['id']) ?>><?= e($display['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error_html('assignment_display_id.' . $index, $formId) ?>
                        </label>
                        <label><?= e(__('schedule.singular')) ?>
                            <select name="assignment_schedule_id[]"<?= field_attrs('assignment_schedule_id.' . $index, $formId) ?>>
                                <option value=""><?= e(__('channel.schedule_placeholder')) ?></option>
                                <?php foreach ($schedules as $schedule): ?>
                                    <option value="<?= e((string)$schedule['id']) ?>" <?= selected($assignment['schedule_id'] ?? '', $schedule['id']) ?>><?= e($schedule['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error_html('assignment_schedule_id.' . $index, $formId) ?>
                        </label>
                        <label>
                            <span class="label-heading-with-help">
                                <?= e(__('channel.priority')) ?>
                                <span class="label-help-wrap">
                                    <button type="button" class="field-help-toggle" aria-label="<?= e(__('channel.priority_help_label')) ?>" aria-expanded="false" aria-controls="assignment-priority-help-<?= e((string)$index) ?>" data-help-toggle>?</button>
                                    <span id="assignment-priority-help-<?= e((string)$index) ?>" class="field-help-popover" hidden data-help-popover><?= e(__('channel.priority_help')) ?></span>
                                </span>
                            </span>
                            <input type="number" min="1" name="assignment_priority[]" value="<?= e((string)($assignment['priority'] ?? '')) ?>" placeholder="<?= e(__('channel.priority_placeholder')) ?>" title="<?= e(__('channel.priority_help')) ?>"<?= field_attrs('assignment_priority.' . $index, $formId, 'assignment-priority-help-' . $index) ?>>
                            <?= field_error_html('assignment_priority.' . $index, $formId) ?>
                        </label>
                        <button type="button" class="button button--normal assignment-remove"><?= admin_icon('remove') ?><span><?= e(__('common.remove')) ?></span></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button--normal" id="add-assignment"><?= admin_icon('add') ?><span><?= e(__('channel.add_assignment')) ?></span></button>
        </div>

        <div class="form-actions"><button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button><a class="button button--normal" href="<?= e(url('/admin/playlists')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a></div>
    </form>
</div>

<?php if ($channel): ?>
<?php
$slideTypeCreateUrl = url('/admin/slides/create?channel_id=' . rawurlencode((string)$channel['id']));
$slideTypeReturnTo = '/admin/playlists/' . $channel['id'] . '/edit';
?>
<div class="card">
    <h2><?= e(__('slide.playlist_slides', ['playlist' => $channel['name']])) ?></h2>
    <div class="form-actions playlist-slides-toolbar">
        <button type="button" class="button button--normal" id="add-existing-slide">
            <?= admin_icon('add') ?><span><?= e(__('slide.add_existing_to_playlist')) ?></span>
        </button>
        <a class="button button--default" href="<?= e($slideTypeCreateUrl . '&return_to=' . rawurlencode($slideTypeReturnTo)) ?>" data-open-slide-type-dialog data-create-url="<?= e($slideTypeCreateUrl) ?>" data-return-to="<?= e($slideTypeReturnTo) ?>" aria-haspopup="dialog">
            <?= admin_icon('add') ?><span><?= e(__('slide.create_in_playlist')) ?></span>
        </a>
    </div>

    <?php if ($slides === []): ?>
        <p class="muted playlist-empty-state"><?= e(__('slide.playlist_empty')) ?></p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="admin-table playlist-slides-table" data-admin-table>
                <thead>
                    <tr>
                        <th class="handle-col"></th>
                        <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
                        <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="type" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.type')])) ?>"><?= e(__('common.type')) ?></button></th>
                        <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="source" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.source')])) ?>"><?= e(__('common.source')) ?></button></th>
                        <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="duration" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.duration')])) ?>"><?= e(__('common.duration')) ?></button></th>
                        <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="status" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.status')])) ?>"><?= e(__('common.status')) ?></button></th>
                        <th><?= e(__('common.actions')) ?></th>
                    </tr>
                    <tr class="slide-library-filter-row">
                        <th></th>
                        <th><input type="search" data-admin-filter="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
                        <th><input type="search" data-admin-filter="type" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.type')])) ?>" placeholder="<?= e(__('common.type')) ?>"></th>
                        <th><input type="search" data-admin-filter="source" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.source')])) ?>" placeholder="<?= e(__('common.source')) ?>"></th>
                        <th><input type="search" data-admin-filter="duration" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.duration')])) ?>" placeholder="<?= e(__('common.duration')) ?>"></th>
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
                <tbody class="sortable-list" data-sort-endpoint="<?= e(url('/admin/sort/slides')) ?>" data-extra-name="channel_id" data-extra-value="<?= e((string)$channel['id']) ?>">
                <?php foreach ($slides as $slide): ?>
                    <?php
                    $typeLabel = $pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', $slide['slide_type'], $slide['slide_type']);
                    $sourceLabel = !empty($slide['media_name']) ? (string)$slide['media_name'] : (!empty($slide['source_url']) ? (string)$slide['source_url'] : '-');
                    $durationValue = $slide['duration_seconds'] ?? '';
                    $durationLabel = $durationValue !== '' && $durationValue !== null ? (string)$durationValue : __('common.default');
                    $statusValue = $slide['is_active'] ? 'active' : 'inactive';
                    $statusLabel = $slide['is_active'] ? __('common.active') : __('common.inactive');
                    ?>
                    <tr draggable="true" data-id="<?= e((string)$slide['id']) ?>" data-admin-row>
                        <td class="handle">↕</td>
                        <td data-admin-cell="name" data-sort-value="<?= e((string)$slide['name']) ?>" data-filter-value="<?= e((string)$slide['name']) ?>"><?= e($slide['name']) ?></td>
                        <td data-admin-cell="type" data-sort-value="<?= e($typeLabel) ?>" data-filter-value="<?= e($typeLabel) ?>"><?= e($typeLabel) ?></td>
                        <td class="truncate" data-admin-cell="source" data-sort-value="<?= e($sourceLabel) ?>" data-filter-value="<?= e($sourceLabel) ?>">
                            <?php if (!empty($slide['media_name'])): ?>
                                <?= e($slide['media_name']) ?>
                            <?php elseif (!empty($slide['source_url'])): ?>
                                <a href="<?= e($slide['source_url']) ?>" target="_blank"><?= e(__('slide.open_source')) ?></a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td data-admin-cell="duration" data-sort-value="<?= e((string)$durationValue) ?>" data-filter-value="<?= e($durationLabel) ?>"><?= e($durationLabel) ?></td>
                        <td data-admin-cell="status" data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
                        <td class="actions">
                            <a class="button button--normal button--small" href="<?= e(url('/admin/slides/' . $slide['id'] . '/edit?return_to=' . rawurlencode('/admin/playlists/' . $channel['id'] . '/edit'))) ?>">
                                <?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span>
                            </a>
                            <form method="post" action="<?= e(url('/admin/playlists/' . $channel['id'] . '/slides/' . $slide['id'] . '/remove')) ?>" class="inline-form" data-confirm-submit data-confirm-title="<?= e(__('common.remove')) ?>" data-confirm-message="<?= e(__('slide.remove_from_playlist_confirm', ['slide' => $slide['name'], 'playlist' => $channel['name']])) ?>" data-confirm-accept="<?= e(__('common.remove')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= e('/admin/playlists/' . $channel['id'] . '/edit') ?>">
                                <button type="submit" class="button button--danger button--small">
                                    <?= admin_icon('remove') ?><span><?= e(__('common.remove')) ?></span>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Slide Picker Dialog -->
<dialog class="admin-dialog slide-picker-dialog" data-slide-picker-dialog aria-labelledby="slide-picker-title" aria-describedby="slide-picker-description">
    <form method="post" class="admin-dialog__panel form-grid" data-slide-picker-form action="<?= e(url('/admin/playlists/' . $channel['id'] . '/slides/add')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" data-slide-picker-return-to value="<?= e('/admin/playlists/' . $channel['id'] . '/edit') ?>">
        <div class="section-head">
            <div>
                <h2 id="slide-picker-title" data-slide-picker-title><?= e(__('slide.add_existing_title', ['playlist' => $channel['name']])) ?></h2>
                <p id="slide-picker-description" class="muted"><?= e(__('slide.add_existing_hint')) ?></p>
            </div>
            <button type="button" class="button button--normal button--small" data-slide-picker-close>
                <?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span>
            </button>
        </div>
        <label class="full-width">
            <?= e(__('common.name')) ?>
            <input type="search" data-slide-picker-search placeholder="<?= e(__('slide.search_library_placeholder')) ?>">
        </label>
        <div class="slide-picker-list" data-slide-picker-list></div>
        <p class="muted" data-slide-picker-empty hidden><?= e(__('slide.add_existing_empty')) ?></p>
        <div class="form-actions">
            <button type="button" class="button button--normal" data-slide-picker-close>
                <?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span>
            </button>
            <button type="submit" class="button button--default" data-slide-picker-submit disabled>
                <?= admin_icon('add') ?><span><?= e(__('slide.add_selected_to_playlist')) ?></span>
            </button>
        </div>
    </form>
</dialog>

<?php require __DIR__ . '/partials/slide_type_dialog.php'; ?>

<?php endif; ?>

<script>
(() => {
    <?php if ($channel): ?>
    // Slide picker functionality
    const slideLibrary = <?= json_encode(array_map(function($slide) use ($pluginLabels) {
        return [
            'id' => (int)$slide['id'],
            'name' => (string)$slide['name'],
            'type' => $pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', (string)$slide['slide_type'], (string)$slide['slide_type']),
            'source' => !empty($slide['media_name']) ? $slide['media_name'] : (!empty($slide['source_url']) ? $slide['source_url'] : '—'),
            'channels' => (string)($slide['channel_names'] ?: __('slide.no_playlists')),
            'is_active' => (int)$slide['is_active'] === 1,
            'status' => (int)$slide['is_active'] === 1 ? __('common.active') : __('common.inactive'),
        ];
    }, $allSlides ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    const assignedSlideIds = new Set(<?= json_encode(array_map(fn($slide) => (int)$slide['id'], $slides ?? []), JSON_UNESCAPED_SLASHES) ?>);

    const pickerDialog = document.querySelector('[data-slide-picker-dialog]');
    if (!pickerDialog || typeof pickerDialog.showModal !== 'function') return;

    const pickerForm = pickerDialog.querySelector('[data-slide-picker-form]');
    const pickerSearch = pickerDialog.querySelector('[data-slide-picker-search]');
    const pickerList = pickerDialog.querySelector('[data-slide-picker-list]');
    const pickerEmpty = pickerDialog.querySelector('[data-slide-picker-empty]');
    const pickerSubmit = pickerDialog.querySelector('[data-slide-picker-submit]');
    let currentAvailableSlides = [];
    let pickerOpener = null;

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

    document.getElementById('add-existing-slide')?.addEventListener('click', (event) => {
        pickerOpener = event.currentTarget instanceof HTMLElement ? event.currentTarget : null;
        currentAvailableSlides = slideLibrary.filter((slide) => !assignedSlideIds.has(slide.id));
        if (pickerSearch) pickerSearch.value = '';
        renderPickerList();
        pickerDialog.showModal();
        pickerSearch?.focus();
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
    pickerDialog.addEventListener('close', () => {
        pickerOpener?.focus?.({ preventScroll: true });
        pickerOpener = null;
    });

    <?php endif; ?>
})();

(() => {
    const displays = <?= json_encode(array_values($displays), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const schedules = <?= json_encode(array_values($schedules), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const displayPlaceholder = <?= json_encode(__('channel.display_placeholder'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const schedulePlaceholder = <?= json_encode(__('channel.schedule_placeholder'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const labels = {
        display: <?= json_encode(__('channel.display_monitor'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        schedule: <?= json_encode(__('schedule.singular'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        priority: <?= json_encode(__('channel.priority'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        priorityPlaceholder: <?= json_encode(__('channel.priority_placeholder'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        priorityHelp: <?= json_encode(__('channel.priority_help'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        priorityHelpLabel: <?= json_encode(__('channel.priority_help_label'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        remove: <?= json_encode(__('common.remove'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };

    const list = document.getElementById('assignment-list');
    const addButton = document.getElementById('add-assignment');
    let generatedHelpId = 0;

    function buildSelect(name, placeholder, items, selectedValue) {
        const select = document.createElement('select');
        select.name = name;
        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = placeholder;
        select.appendChild(blank);
        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            if (String(item.id) === String(selectedValue || '')) option.selected = true;
            select.appendChild(option);
        });
        return select;
    }

    function buildLabel(text, control) {
        const label = document.createElement('label');
        label.append(document.createTextNode(text), control);
        return label;
    }

    function addRow(values = {}) {
        const row = document.createElement('div');
        row.className = 'assignment-row';
        row.appendChild(buildLabel(labels.display, buildSelect('assignment_display_id[]', displayPlaceholder, displays, values.display_id)));
        row.appendChild(buildLabel(labels.schedule, buildSelect('assignment_schedule_id[]', schedulePlaceholder, schedules, values.schedule_id)));

        const priorityInput = document.createElement('input');
        priorityInput.type = 'number';
        priorityInput.min = '1';
        priorityInput.name = 'assignment_priority[]';
        priorityInput.value = values.priority || '';
        priorityInput.placeholder = labels.priorityPlaceholder;
        priorityInput.title = labels.priorityHelp;
        const helpId = `assignment-priority-help-generated-${++generatedHelpId}`;
        priorityInput.setAttribute('aria-describedby', helpId);
        const priorityLabel = document.createElement('label');
        const priorityHeading = document.createElement('span');
        priorityHeading.className = 'label-heading-with-help';
        priorityHeading.append(document.createTextNode(labels.priority), buildHelpControl(helpId));
        priorityLabel.append(priorityHeading, priorityInput);
        row.appendChild(priorityLabel);

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'button button--normal assignment-remove';
        removeButton.textContent = labels.remove;
        row.appendChild(removeButton);
        list.appendChild(row);
    }

    function buildHelpControl(helpId) {
        const wrap = document.createElement('span');
        wrap.className = 'label-help-wrap';

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'field-help-toggle';
        toggle.setAttribute('aria-label', labels.priorityHelpLabel);
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-controls', helpId);
        toggle.dataset.helpToggle = '';
        toggle.textContent = '?';

        const popover = document.createElement('span');
        popover.id = helpId;
        popover.className = 'field-help-popover';
        popover.hidden = true;
        popover.dataset.helpPopover = '';
        popover.textContent = labels.priorityHelp;

        wrap.append(toggle, popover);
        return wrap;
    }

    function closeHelpPopovers(exceptToggle = null) {
        list.querySelectorAll('[data-help-toggle]').forEach((toggle) => {
            if (toggle === exceptToggle) return;
            toggle.setAttribute('aria-expanded', 'false');
            const popover = toggle.parentElement?.querySelector('[data-help-popover]');
            if (popover) popover.hidden = true;
        });
    }

    addButton.addEventListener('click', () => addRow());
    list.addEventListener('click', (event) => {
        const helpToggle = event.target.closest('[data-help-toggle]');
        if (helpToggle && list.contains(helpToggle)) {
            event.preventDefault();
            const popover = helpToggle.parentElement?.querySelector('[data-help-popover]');
            if (!popover) return;
            const willOpen = popover.hidden;
            closeHelpPopovers(helpToggle);
            popover.hidden = !willOpen;
            helpToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            return;
        }

        const removeButton = event.target.closest('.assignment-remove');
        if (!removeButton || !list.contains(removeButton)) return;
        const rows = list.querySelectorAll('.assignment-row');
        if (rows.length <= 1) {
            rows[0].querySelectorAll('select, input').forEach((input) => { input.value = ''; });
            return;
        }
        removeButton.closest('.assignment-row').remove();
    });
    document.addEventListener('click', (event) => {
        if (!list.contains(event.target)) closeHelpPopovers();
    });

    const highlightedAssignment = document.querySelector('.assignment-row--highlight');
    if (highlightedAssignment) {
        window.setTimeout(() => highlightedAssignment.scrollIntoView({block: 'center', behavior: 'smooth'}), 80);
    }
})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
