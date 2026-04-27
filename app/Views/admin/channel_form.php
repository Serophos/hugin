<?php
$title = $channel ? __('channel.edit_title') : __('channel.create_title');
$assignmentRows = $assignments ?: [['display_id' => '', 'schedule_id' => '', 'priority' => '']];
require __DIR__ . '/../layouts/admin_header.php';
?>
<h1><?= e($title) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <form method="post" action="<?= e($channel ? url('/admin/channels/' . $channel['id'] . '/edit') : url('/admin/channels/create')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label><?= e(__('common.name')) ?><input type="text" name="name" value="<?= e($channel['name'] ?? '') ?>" required></label>
        <label><?= e(__('channel.transition_effect')) ?>
            <select name="transition_effect">
                <?php foreach (['inherit','fade','slide-left','slide-right','slide-up','slide-down','zoom','flip','blur','none'] as $fx): ?>
                    <option value="<?= e($fx) ?>" <?= selected($channel['transition_effect'] ?? 'inherit', $fx) ?>><?= e(enum_label('effects', $fx, $fx)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(__('channel.custom_slide_duration')) ?><input type="number" min="1" name="slide_duration_seconds" value="<?= e((string)($channel['slide_duration_seconds'] ?? '')) ?>"></label>
        <label class="full-width"><?= e(__('common.description')) ?><textarea name="description" rows="4"><?= e($channel['description'] ?? '') ?></textarea></label>
        <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= checked($channel['is_active'] ?? 1) ?>> <?= e(__('common.active')) ?></label>

        <div class="full-width plugin-settings-card">
            <h3><?= e(__('channel.display_schedule_assignments')) ?></h3>
            <div id="assignment-list" class="assignment-list">
                <?php foreach ($assignmentRows as $assignment): ?>
                    <div class="assignment-row">
                        <label><?= e(__('channel.display_monitor')) ?>
                            <select name="assignment_display_id[]">
                                <option value=""><?= e(__('channel.display_placeholder')) ?></option>
                                <?php foreach ($displays as $display): ?>
                                    <option value="<?= e((string)$display['id']) ?>" <?= selected($assignment['display_id'] ?? '', $display['id']) ?>><?= e($display['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><?= e(__('schedule.singular')) ?>
                            <select name="assignment_schedule_id[]">
                                <option value=""><?= e(__('channel.schedule_placeholder')) ?></option>
                                <?php foreach ($schedules as $schedule): ?>
                                    <option value="<?= e((string)$schedule['id']) ?>" <?= selected($assignment['schedule_id'] ?? '', $schedule['id']) ?>><?= e($schedule['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><?= e(__('channel.priority')) ?>
                            <input type="number" min="1" name="assignment_priority[]" value="<?= e((string)($assignment['priority'] ?? '')) ?>">
                        </label>
                        <button type="button" class="button button--normal assignment-remove"><?= admin_icon('remove') ?><span><?= e(__('common.remove')) ?></span></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button--normal" id="add-assignment"><?= admin_icon('add') ?><span><?= e(__('channel.add_assignment')) ?></span></button>
        </div>

        <div class="form-actions"><button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button><a class="button button--normal" href="<?= e(url('/admin/channels')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a></div>
    </form>
</div>
<script>
(() => {
    const displays = <?= json_encode(array_values($displays), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const schedules = <?= json_encode(array_values($schedules), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const displayPlaceholder = <?= json_encode(__('channel.display_placeholder'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const schedulePlaceholder = <?= json_encode(__('channel.schedule_placeholder'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const labels = {
        display: <?= json_encode(__('channel.display_monitor'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        schedule: <?= json_encode(__('schedule.singular'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        priority: <?= json_encode(__('channel.priority'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        remove: <?= json_encode(__('common.remove'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };

    const list = document.getElementById('assignment-list');
    const addButton = document.getElementById('add-assignment');

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
        row.appendChild(buildLabel(labels.priority, priorityInput));

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'button button--normal assignment-remove';
        removeButton.textContent = labels.remove;
        row.appendChild(removeButton);
        list.appendChild(row);
    }

    addButton.addEventListener('click', () => addRow());
    list.addEventListener('click', (event) => {
        const removeButton = event.target.closest('.assignment-remove');
        if (!removeButton || !list.contains(removeButton)) return;
        const rows = list.querySelectorAll('.assignment-row');
        if (rows.length <= 1) {
            rows[0].querySelectorAll('select, input').forEach((input) => { input.value = ''; });
            return;
        }
        removeButton.closest('.assignment-row').remove();
    });
})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
