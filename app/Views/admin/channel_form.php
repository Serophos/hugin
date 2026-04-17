<?php $title = $channel ? __('channel.edit_title') : __('channel.create_title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
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

        <div class="full-width">
            <h3><?= e(__('channel.assigned_displays')) ?></h3>
            <div class="checkbox-grid">
                <?php foreach ($displays as $display): $assigned = isset($assignments[$display['id']]); ?>
                    <label class="checkbox-row"><input type="checkbox" name="display_ids[]" value="<?= e((string)$display['id']) ?>" <?= $assigned ? 'checked' : '' ?>> <?= e($display['name']) ?></label>
                    <label class="checkbox-row sub-option"><input type="checkbox" name="default_display_ids[]" value="<?= e((string)$display['id']) ?>" <?= !empty($assignments[$display['id']]['is_default']) ? 'checked' : '' ?>> <?= e(__('channel.default_for_display')) ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="full-width">
            <h3><?= e(__('channel.schedules_per_display')) ?></h3>
            <p class="muted"><?= e(__('channel.schedule_help')) ?></p>
            <div id="schedule-list">
                <?php $rows = $schedules ?: [['display_id' => '', 'weekday' => '', 'start_time' => '', 'end_time' => '']]; ?>
                <?php foreach ($rows as $schedule): ?>
                    <div class="schedule-row">
                        <select name="schedule_display_id[]">
                            <option value=""><?= e(__('channel.display_placeholder')) ?></option>
                            <?php foreach ($displays as $display): ?>
                                <option value="<?= e((string)$display['id']) ?>" <?= selected($schedule['display_id'] ?? '', $display['id']) ?>><?= e($display['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="schedule_weekday[]">
                            <option value=""><?= e(__('channel.day_placeholder')) ?></option>
                            <?php foreach (range(0, 6) as $i): ?>
                                <option value="<?= e((string)$i) ?>" <?= selected($schedule['weekday'] ?? '', $i) ?>><?= e(__('days.' . $i)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="time" name="schedule_start[]" value="<?= e(substr((string)($schedule['start_time'] ?? ''), 0, 5)) ?>">
                        <input type="time" name="schedule_end[]" value="<?= e(substr((string)($schedule['end_time'] ?? ''), 0, 5)) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button secondary" onclick="addScheduleRow()"><?= e(__('channel.add_schedule')) ?></button>
        </div>

        <div class="form-actions"><button type="submit"><?= e(__('common.save')) ?></button><a class="button secondary" href="<?= e(url('/admin/channels')) ?>"><?= e(__('common.cancel')) ?></a></div>
    </form>
</div>
<script>
const huginDisplays = <?= json_encode(array_values($displays), JSON_UNESCAPED_SLASHES) ?>;
const huginDayLabels = <?= json_encode(array_map(static fn($i) => __('days.' . $i), range(0, 6)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const huginDisplayPlaceholder = <?= json_encode(__('channel.display_placeholder')) ?>;
const huginDayPlaceholder = <?= json_encode(__('channel.day_placeholder')) ?>;
function addScheduleRow() {
    const container = document.getElementById('schedule-list');
    const row = document.createElement('div');
    const displayOptions = [`<option value="">${huginDisplayPlaceholder}</option>`].concat(huginDisplays.map(d => `<option value="${d.id}">${d.name}</option>`)).join('');
    const dayOptions = huginDayLabels.map((day, i) => `<option value="${i}">${day}</option>`).join('');
    row.className = 'schedule-row';
    row.innerHTML = `<select name="schedule_display_id[]">${displayOptions}</select><select name="schedule_weekday[]"><option value="">${huginDayPlaceholder}</option>${dayOptions}</select><input type="time" name="schedule_start[]"><input type="time" name="schedule_end[]">`;
    container.appendChild(row);
}
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
