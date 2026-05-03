<?php
$formId = 'schedule';
$title = $schedule ? __('schedule.edit_title') : __('schedule.create_title');
if (form_has_old($formId)) {
    $weekdayValues = old_array('rule_weekday', [], $formId);
    $startValues = old_array('rule_start_time', [], $formId);
    $endValues = old_array('rule_end_time', [], $formId);
    $rowCount = max(1, count($weekdayValues), count($startValues), count($endValues));
    $ruleRows = [];
    for ($i = 0; $i < $rowCount; $i++) {
        $ruleRows[] = [
            'weekday' => $weekdayValues[$i] ?? '',
            'start_time' => $startValues[$i] ?? '',
            'end_time' => $endValues[$i] ?? '',
        ];
    }
} else {
    $ruleRows = $rules ?: [['weekday' => '', 'start_time' => '', 'end_time' => '']];
}
require __DIR__ . '/../layouts/admin_header.php';
?>
<h1><?= e($title) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <form method="post" action="<?= e($schedule ? url('/admin/schedules/' . $schedule['id'] . '/edit') : url('/admin/schedules/create')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label><?= e(__('common.name')) ?>
            <input type="text" name="name" value="<?= e((string)old('name', $schedule['name'] ?? '', $formId)) ?>" placeholder="<?= e(__('schedule.name_placeholder')) ?>" required<?= field_attrs('name', $formId) ?>>
            <?= field_error_html('name', $formId) ?>
        </label>
        <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= old_checked('is_active', $schedule['is_active'] ?? 1, $formId) ?>> <?= e(__('common.active')) ?></label>

        <div class="full-width plugin-settings-card">
            <h3><?= e(__('schedule.rules')) ?></h3>
            <div id="rule-list" class="rule-list">
                <?php foreach ($ruleRows as $index => $rule): ?>
                    <div class="rule-row">
                        <label><?= e(__('schedule.weekday')) ?>
                            <select name="rule_weekday[]"<?= field_attrs('rule_weekday.' . $index, $formId) ?>>
                                <option value=""><?= e(__('schedule.weekday_placeholder')) ?></option>
                                <?php foreach (range(1, 7) as $day): ?>
                                    <option value="<?= e((string)$day) ?>" <?= selected($rule['weekday'] ?? '', $day) ?>><?= e(__('days.' . $day)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= field_error_html('rule_weekday.' . $index, $formId) ?>
                        </label>
                        <label><?= e(__('schedule.start_time')) ?>
                            <input type="time" name="rule_start_time[]" value="<?= e(substr((string)($rule['start_time'] ?? ''), 0, 5)) ?>" title="<?= e(__('schedule.time_help')) ?>"<?= field_attrs('rule_start_time.' . $index, $formId) ?>>
                            <?= field_error_html('rule_start_time.' . $index, $formId) ?>
                        </label>
                        <label><?= e(__('schedule.end_time')) ?>
                            <input type="time" name="rule_end_time[]" value="<?= e(substr((string)($rule['end_time'] ?? ''), 0, 5)) ?>" title="<?= e(__('schedule.time_help')) ?>"<?= field_attrs('rule_end_time.' . $index, $formId) ?>>
                            <?= field_error_html('rule_end_time.' . $index, $formId) ?>
                        </label>
                        <button type="button" class="button button--normal rule-remove"><?= admin_icon('remove') ?><span><?= e(__('common.remove')) ?></span></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button--normal" id="add-rule"><?= admin_icon('add') ?><span><?= e(__('schedule.add_rule')) ?></span></button>
        </div>

        <div class="form-actions"><button type="submit" class="button button--default"><?= admin_icon('save') ?><span><?= e(__('common.save')) ?></span></button><a class="button button--normal" href="<?= e(url('/admin/schedules')) ?>"><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></a></div>
    </form>
</div>
<script>
(() => {
    const dayLabels = <?= json_encode(array_map(static fn ($day) => ['id' => $day, 'name' => __('days.' . $day)], range(1, 7)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const labels = {
        weekday: <?= json_encode(__('schedule.weekday'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        weekdayPlaceholder: <?= json_encode(__('schedule.weekday_placeholder'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        start: <?= json_encode(__('schedule.start_time'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        end: <?= json_encode(__('schedule.end_time'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        timeHelp: <?= json_encode(__('schedule.time_help'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        remove: <?= json_encode(__('common.remove'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };

    const list = document.getElementById('rule-list');
    const addButton = document.getElementById('add-rule');

    function buildLabel(text, control) {
        const label = document.createElement('label');
        label.append(document.createTextNode(text), control);
        return label;
    }

    function buildDaySelect() {
        const select = document.createElement('select');
        select.name = 'rule_weekday[]';
        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = labels.weekdayPlaceholder;
        select.appendChild(blank);
        dayLabels.forEach((day) => {
            const option = document.createElement('option');
            option.value = day.id;
            option.textContent = day.name;
            select.appendChild(option);
        });
        return select;
    }

    function addRow() {
        const row = document.createElement('div');
        row.className = 'rule-row';
        row.appendChild(buildLabel(labels.weekday, buildDaySelect()));

        const startInput = document.createElement('input');
        startInput.type = 'time';
        startInput.name = 'rule_start_time[]';
        startInput.title = labels.timeHelp;
        row.appendChild(buildLabel(labels.start, startInput));

        const endInput = document.createElement('input');
        endInput.type = 'time';
        endInput.name = 'rule_end_time[]';
        endInput.title = labels.timeHelp;
        row.appendChild(buildLabel(labels.end, endInput));

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'button button--normal rule-remove';
        removeButton.textContent = labels.remove;
        row.appendChild(removeButton);
        list.appendChild(row);
    }

    addButton.addEventListener('click', addRow);
    list.addEventListener('click', (event) => {
        const removeButton = event.target.closest('.rule-remove');
        if (!removeButton || !list.contains(removeButton)) return;
        const rows = list.querySelectorAll('.rule-row');
        if (rows.length <= 1) {
            rows[0].querySelectorAll('select, input').forEach((input) => { input.value = ''; });
            return;
        }
        removeButton.closest('.rule-row').remove();
    });
})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
