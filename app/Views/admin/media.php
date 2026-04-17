<?php $title = __('media.title'); require __DIR__ . '/../layouts/admin_header.php'; ?>
<div class="page-head">
    <div>
        <h1><?= e(__('media.title')) ?></h1>
        <p class="muted"><?= e(__('media.intro')) ?></p>
    </div>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2">
    <div class="card">
        <h2><?= e(__('media.upload_title')) ?></h2>
        <form method="post" action="<?= e(url('/admin/media/upload')) ?>" enctype="multipart/form-data" class="form-grid">
            <?= csrf_field() ?>
            <label><?= e(__('common.name')) ?>
                <input type="text" name="name" placeholder="<?= e(__('media.optional_display_name')) ?>">
            </label>
            <label><?= e(__('media.file')) ?>
                <input type="file" name="media_file" accept="image/*,video/*" required>
            </label>
            <button type="submit"><?= e(__('media.upload_title')) ?></button>
        </form>
    </div>

    <div class="card">
        <h2><?= e(__('media.notes_title')) ?></h2>
        <ul class="list">
            <li><?= e(__('media.allowed_formats')) ?></li>
            <li><?= e(__('media.max_size_note')) ?></li>
            <li><?= e(__('media.permissions_note')) ?></li>
        </ul>
    </div>
</div>

<div class="card">
    <table>
        <thead>
        <tr>
            <th><?= e(__('common.preview')) ?></th>
            <th><?= e(__('common.name')) ?></th>
            <th><?= e(__('common.type')) ?></th>
            <th><?= e(__('common.size', [], 'Size')) ?></th>
            <th><?= e(__('media.used_by_slides')) ?></th>
            <th><?= e(__('media.uploaded_by')) ?></th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($media as $asset): ?>
            <tr>
                <td class="preview-cell">
                    <?php if ($asset['media_kind'] === 'image'): ?>
                        <img class="thumb" src="<?= e(url($asset['file_path'])) ?>" alt="<?= e($asset['name']) ?>">
                    <?php else: ?>
                        <video class="thumb" src="<?= e(url($asset['file_path'])) ?>" muted></video>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?= e($asset['name']) ?></strong><br>
                    <span class="muted"><?= e($asset['original_name']) ?></span>
                </td>
                <td><?= e(enum_label('slide_types', $asset['media_kind'], $asset['media_kind'])) ?><br><span class="muted"><?= e($asset['mime_type']) ?></span></td>
                <td><?= e(format_bytes((int)$asset['file_size'])) ?></td>
                <td><?= e((string)$asset['usage_count']) ?></td>
                <td><?= e($asset['uploaded_by'] ?? __('common.unknown', [], 'Unknown')) ?></td>
                <td class="actions">
                    <a href="<?= e(url($asset['file_path'])) ?>" target="_blank"><?= e(__('common.open')) ?></a>
                    <?php if (is_admin()): ?>
                        <form method="post" action="<?= e(url('/admin/media/' . $asset['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm(<?= json_encode(__('media.delete_confirm')) ?>);">
                            <?= csrf_field() ?>
                            <button type="submit" class="link-button danger"><?= e(__('common.delete')) ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
