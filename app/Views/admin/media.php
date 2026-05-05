<?php
$uploadForm = 'media_upload';
$title = __('media.title');
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-head">
    <div>
        <h1><?= e(__('media.title')) ?></h1>
        <p class="muted"><?= e(__('media.intro')) ?></p>
    </div>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<?php
$baseMediaUrl = url('/admin/media');
$kindQuery = $kind !== '' ? '?kind=' . rawurlencode($kind) : '';
$allUrl = $baseMediaUrl;
$imageUrl = $baseMediaUrl . '?kind=image';
$videoUrl = $baseMediaUrl . '?kind=video';
$prevPageUrl = $baseMediaUrl . ($kind !== '' ? '?kind=' . rawurlencode($kind) . '&page=' . max(1, $page - 1) : '?page=' . max(1, $page - 1));
$nextPageUrl = $baseMediaUrl . ($kind !== '' ? '?kind=' . rawurlencode($kind) . '&page=' . ($page + 1) : '?page=' . ($page + 1));
?>

<div class="media-kind-tabs">
    <a class="tab<?= $kind === '' ? ' active' : '' ?>" href="<?= e($allUrl) ?>"><?= e(__('media.all')) ?></a>
    <a class="tab<?= $kind === 'image' ? ' active' : '' ?>" href="<?= e($imageUrl) ?>"><?= e(__('media.images')) ?></a>
    <a class="tab<?= $kind === 'video' ? ' active' : '' ?>" href="<?= e($videoUrl) ?>"><?= e(__('media.videos')) ?></a>
</div>

<div class="media-summary"><?= e(__('media.showing_assets', ['count' => count($media), 'total' => $totalCount])) ?></div>

<div class="grid-2">
    <div class="card">
        <h2><?= e(__('media.upload_title')) ?></h2>
        <form method="post" action="<?= e(url('/admin/media/upload')) ?>" enctype="multipart/form-data" class="form-grid">
            <?= csrf_field() ?>
            <label><?= e(__('common.name')) ?>
                <input type="text" name="name" value="<?= e((string)old('name', '', $uploadForm)) ?>" placeholder="<?= e(__('media.name_placeholder')) ?>"<?= field_attrs('name', $uploadForm) ?>>
                <?= field_error_html('name', $uploadForm) ?>
            </label>
            <label><?= e(__('media.file')) ?>
                <input type="file" name="media_file" accept="image/*,video/*" required<?= field_attrs('media_file', $uploadForm) ?>>
                <?= field_error_html('media_file', $uploadForm) ?>
                <small class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
            </label>
            <button type="submit" class="button button--default"><?= admin_icon('upload') ?><span><?= e(__('media.upload_title')) ?></span></button>
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
    <div class="table-scroll">
        <table class="media-table">
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
                    <td class="media-name-cell">
                        <strong class="break-word"><?= e($asset['name']) ?></strong><br>
                        <span class="muted break-word"><?= e($asset['original_name']) ?></span>
                    </td>
                    <td class="break-word"><?= e(enum_label('slide_types', $asset['media_kind'], $asset['media_kind'])) ?><br><span class="muted break-word"><?= e($asset['mime_type']) ?></span></td>
                    <td><?= e(format_bytes((int)$asset['file_size'])) ?></td>
                    <td><?= e((string)$asset['usage_count']) ?></td>
                    <td class="break-word"><?= e($asset['uploaded_by'] ?? __('common.unknown', [], 'Unknown')) ?></td>
                    <td class="actions">
                        <a class="button button--normal button--small" href="<?= e(url($asset['file_path'])) ?>" target="_blank"><?= admin_icon('open') ?><span><?= e(__('common.open')) ?></span></a>
                        <?php if (in_array(current_user_role(), ['admin', 'editor'], true)): ?>
                            <form method="post" action="<?= e(url('/admin/media/' . $asset['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm(<?= json_encode(__('media.delete_confirm')) ?>);">
                                <?= csrf_field() ?>
                                <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <div>
            <?php if ($page > 1): ?>
                <a class="button button--normal button--small" href="<?= e($prevPageUrl) ?>"><?= e(__('media.previous_page')) ?></a>
            <?php endif; ?>
        </div>
        <div class="pagination-info"><?= e(__('media.page_of', ['page' => $page, 'count' => $pageCount])) ?></div>
        <div>
            <?php if ($page < $pageCount): ?>
                <a class="button button--normal button--small" href="<?= e($nextPageUrl) ?>"><?= e(__('media.next_page')) ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
