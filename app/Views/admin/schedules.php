<?php
$title = __('schedule.plural');
$formatTime = static fn ($value): string => substr((string)$value, 0, 5);
$ruleSummary = static function (array $rules) use ($formatTime): string {
    if (!$rules) {
        return __('schedule.fulltime_label');
    }

    $parts = [];
    foreach ($rules as $rule) {
        $parts[] = __('days.' . (int)$rule['weekday']) . ' ' . $formatTime($rule['start_time']) . '-' . $formatTime($rule['end_time']);
    }
    return implode(', ', $parts);
};
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-head">
    <div><h1><?= e(__('schedule.plural')) ?></h1><p class="muted"><?= e(__('schedule.overview_hint')) ?></p></div>
    <a class="button button--default" href="<?= e(url('/admin/schedules/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('schedule.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <table class="schedule-table">
        <thead><tr><th><?= e(__('common.name')) ?></th><th><?= e(__('common.type')) ?></th><th class="schedule-rules-col"><?= e(__('schedule.rules')) ?></th><th><?= e(__('schedule.assignment_count')) ?></th><th><?= e(__('common.status')) ?></th><th class="schedule-actions-col"></th></tr></thead>
        <tbody>
        <?php foreach ($schedules as $schedule): ?>
            <?php $summary = $ruleSummary($rulesBySchedule[(int)$schedule['id']] ?? []); ?>
            <tr>
                <td><strong><?= e($schedule['name']) ?></strong><?php if (!empty($schedule['is_system'])): ?><br><span class="muted small"><?= e(__('schedule.system_label')) ?></span><?php endif; ?></td>
                <td><?= e(__($schedule['type'] === 'fulltime' ? 'schedule.type_fulltime' : 'schedule.type_weekly')) ?></td>
                <td class="schedule-rules-cell" title="<?= e($summary) ?>"><?= e($summary) ?></td>
                <td><?= e((string)$schedule['assignment_count']) ?></td>
                <td><?= e($schedule['is_active'] ? __('common.active') : __('common.inactive')) ?></td>
                <td class="actions">
                    <?php if (empty($schedule['is_system'])): ?>
                        <a class="button button--normal button--small" href="<?= e(url('/admin/schedules/' . $schedule['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                        <form method="post" action="<?= e(url('/admin/schedules/' . $schedule['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm(<?= json_encode(__('schedule.delete_confirm')) ?>);">
                            <?= csrf_field() ?>
                            <button type="submit" class="button button--danger button--small button--icon-only" title="<?= e(__('common.delete')) ?>" aria-label="<?= e(__('common.delete')) ?>"><?= admin_icon('delete') ?></button>
                        </form>
                    <?php else: ?>
                        <span class="muted small"><?= e(__('schedule.protected_label')) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
