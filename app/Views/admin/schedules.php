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
<div class="page-actions">
    <a class="button button--default" href="<?= e(url('/admin/schedules/create')) ?>"><?= admin_icon('add') ?><span><?= e(__('schedule.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <div class="table-scroll">
    <table class="admin-table schedule-table" data-admin-table>
        <thead>
        <tr>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="type" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.type')])) ?>"><?= e(__('common.type')) ?></button></th>
            <th class="schedule-rules-col" aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="rules" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('schedule.rules')])) ?>"><?= e(__('schedule.rules')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="assignments" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('schedule.assignment_count')])) ?>"><?= e(__('schedule.assignment_count')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="status" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.status')])) ?>"><?= e(__('common.status')) ?></button></th>
            <th class="schedule-actions-col"><?= e(__('common.actions')) ?></th>
        </tr>
        <tr class="slide-library-filter-row">
            <th><input type="search" data-admin-filter="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
            <th><input type="search" data-admin-filter="type" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.type')])) ?>" placeholder="<?= e(__('common.type')) ?>"></th>
            <th><input type="search" data-admin-filter="rules" aria-label="<?= e(__('slide.filter_column', ['column' => __('schedule.rules')])) ?>" placeholder="<?= e(__('schedule.rules')) ?>"></th>
            <th><input type="search" data-admin-filter="assignments" aria-label="<?= e(__('slide.filter_column', ['column' => __('schedule.assignment_count')])) ?>" placeholder="<?= e(__('schedule.assignment_count')) ?>"></th>
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
        <?php foreach ($schedules as $schedule): ?>
            <?php
            $summary = $ruleSummary($rulesBySchedule[(int)$schedule['id']] ?? []);
            $typeLabel = __($schedule['type'] === 'fulltime' ? 'schedule.type_fulltime' : 'schedule.type_weekly');
            $statusValue = $schedule['is_active'] ? 'active' : 'inactive';
            $statusLabel = $schedule['is_active'] ? __('common.active') : __('common.inactive');
            $nameFilter = trim((string)$schedule['name'] . ' ' . (!empty($schedule['is_system']) ? __('schedule.system_label') : ''));
            ?>
            <tr data-admin-row>
                <td data-admin-cell="name" data-sort-value="<?= e((string)$schedule['name']) ?>" data-filter-value="<?= e($nameFilter) ?>"><strong><?= e($schedule['name']) ?></strong><?php if (!empty($schedule['is_system'])): ?><br><span class="muted small"><?= e(__('schedule.system_label')) ?></span><?php endif; ?></td>
                <td data-admin-cell="type" data-sort-value="<?= e($typeLabel) ?>" data-filter-value="<?= e($typeLabel) ?>"><?= e($typeLabel) ?></td>
                <td class="schedule-rules-cell" data-admin-cell="rules" data-sort-value="<?= e($summary) ?>" data-filter-value="<?= e($summary) ?>" title="<?= e($summary) ?>"><?= e($summary) ?></td>
                <td data-admin-cell="assignments" data-sort-value="<?= e((string)$schedule['assignment_count']) ?>" data-filter-value="<?= e((string)$schedule['assignment_count']) ?>"><?= e((string)$schedule['assignment_count']) ?></td>
                <td data-admin-cell="status" data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
                <td class="actions">
                    <?php if (empty($schedule['is_system'])): ?>
                        <a class="button button--normal button--small" href="<?= e(url('/admin/schedules/' . $schedule['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                        <form method="post" action="<?= e(url('/admin/schedules/' . $schedule['id'] . '/delete')) ?>" class="inline-form" data-dialog-submit data-dialog-title="<?= e(__('common.delete')) ?>" data-dialog-message="<?= e(__('schedule.delete_confirm')) ?>" data-dialog-icon="trash" data-dialog-buttons="cancel,delete" data-dialog-accept="<?= e(__('common.delete')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
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
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
