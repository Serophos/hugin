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

<div class="media-kind-tabs">
    <a class="tab<?= $kind === '' ? ' active' : '' ?>" href="<?= e($allUrl) ?>"><?= e(__('media.all')) ?></a>
    <a class="tab<?= $kind === 'image' ? ' active' : '' ?>" href="<?= e($imageUrl) ?>"><?= e(__('media.images')) ?></a>
    <a class="tab<?= $kind === 'video' ? ' active' : '' ?>" href="<?= e($videoUrl) ?>"><?= e(__('media.videos')) ?></a>
</div>



<div class="card">
    <div class="table-scroll">
        <table class="admin-table media-table" data-admin-table>
            <thead>
            <tr>
                <th><?= e(__('common.preview')) ?></th>
                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="type" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.type')])) ?>"><?= e(__('common.type')) ?></button></th>
                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="size" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.size', [], 'Size')])) ?>"><?= e(__('common.size', [], 'Size')) ?></button></th>
                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="usage" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('media.used_by_slides')])) ?>"><?= e(__('media.used_by_slides')) ?></button></th>
                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="uploaded_by" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('media.uploaded_by')])) ?>"><?= e(__('media.uploaded_by')) ?></button></th>
                <th><?= e(__('common.actions')) ?></th>
            </tr>
            <tr class="slide-library-filter-row">
                <th></th>
                <th><input type="search" data-admin-filter="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
                <th><input type="search" data-admin-filter="type" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.type')])) ?>" placeholder="<?= e(__('common.type')) ?>"></th>
                <th><input type="search" data-admin-filter="size" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.size', [], 'Size')])) ?>" placeholder="<?= e(__('common.size', [], 'Size')) ?>"></th>
                <th><input type="search" data-admin-filter="usage" aria-label="<?= e(__('slide.filter_column', ['column' => __('media.used_by_slides')])) ?>" placeholder="<?= e(__('media.used_by_slides')) ?>"></th>
                <th><input type="search" data-admin-filter="uploaded_by" aria-label="<?= e(__('slide.filter_column', ['column' => __('media.uploaded_by')])) ?>" placeholder="<?= e(__('media.uploaded_by')) ?>"></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($media as $asset): ?>
                <?php
                $assetUrl = url($asset['file_path']);
                $assetKind = (string)$asset['media_kind'];
                $assetTypeLabel = enum_label('slide_types', $assetKind, $assetKind);
                $assetSize = format_bytes((int)$asset['file_size']);
                $assetUploadedBy = $asset['uploaded_by'] ?? __('common.unknown', [], 'Unknown');
                ?>
                <tr
                    data-admin-row
                    data-media-preview-row
                    data-media-url="<?= e($assetUrl) ?>"
                    data-media-kind="<?= e($assetKind) ?>"
                    data-media-name="<?= e($asset['name']) ?>"
                    data-media-original="<?= e($asset['original_name']) ?>"
                    data-media-type="<?= e($assetTypeLabel) ?>"
                    data-media-mime="<?= e($asset['mime_type']) ?>"
                    data-media-size="<?= e($assetSize) ?>"
                    data-media-usage="<?= e((string)$asset['usage_count']) ?>"
                    data-media-uploaded-by="<?= e($assetUploadedBy) ?>"
                >
                    <td class="preview-cell">
                        <button type="button" class="media-thumb-button" data-media-preview-open aria-label="<?= e(__('media.open_preview_for', ['name' => $asset['name']], 'Preview :name')) ?>">
                        <?php if ($assetKind === 'image'): ?>
                            <img class="thumb" src="<?= e($assetUrl) ?>" alt="<?= e($asset['name']) ?>">
                        <?php else: ?>
                            <video class="thumb" src="<?= e($assetUrl) ?>" muted preload="metadata"></video>
                        <?php endif; ?>
                        </button>
                    </td>
                    <td class="media-name-cell" data-admin-cell="name" data-sort-value="<?= e((string)$asset['name']) ?>" data-filter-value="<?= e(trim((string)$asset['name'] . ' ' . (string)$asset['original_name'])) ?>">
                        <strong class="break-word"><?= e($asset['name']) ?></strong><br>
                        <span class="muted break-word"><?= e($asset['original_name']) ?></span>
                    </td>
                    <td class="break-word" data-admin-cell="type" data-sort-value="<?= e($assetTypeLabel) ?>" data-filter-value="<?= e(trim($assetTypeLabel . ' ' . (string)$asset['mime_type'])) ?>"><?= e($assetTypeLabel) ?><br><span class="muted break-word"><?= e($asset['mime_type']) ?></span></td>
                    <td data-admin-cell="size" data-sort-value="<?= e((string)$asset['file_size']) ?>" data-filter-value="<?= e($assetSize) ?>"><?= e($assetSize) ?></td>
                    <td data-admin-cell="usage" data-sort-value="<?= e((string)$asset['usage_count']) ?>" data-filter-value="<?= e((string)$asset['usage_count']) ?>"><?= e((string)$asset['usage_count']) ?></td>
                    <td class="break-word" data-admin-cell="uploaded_by" data-sort-value="<?= e($assetUploadedBy) ?>" data-filter-value="<?= e($assetUploadedBy) ?>"><?= e($assetUploadedBy) ?></td>
                    <td class="actions">
                        <button type="button" class="button button--normal button--small" data-media-preview-open><?= admin_icon('open') ?><span><?= e(__('common.open')) ?></span></button>
                        <?php if (in_array(current_user_role(), ['admin', 'editor'], true)): ?>
                            <form method="post" action="<?= e(url('/admin/media/' . $asset['id'] . '/delete')) ?>" class="inline-form" data-confirm-submit data-confirm-title="<?= e(__('common.delete')) ?>" data-confirm-message="<?= e(__('media.delete_confirm')) ?>" data-confirm-accept="<?= e(__('common.delete')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="media-summary"><?= e(__('media.showing_assets', ['count' => count($media), 'total' => $totalCount])) ?></div>

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
<dialog class="media-preview-dialog" data-media-preview-dialog aria-labelledby="media-preview-title">
    <div class="media-preview-shell">
        <div class="media-preview-stage" data-media-preview-stage tabindex="-1">
            <img class="media-preview-asset" data-media-preview-image alt="" hidden>
            <video class="media-preview-asset" data-media-preview-video controls playsinline preload="metadata" hidden></video>
        </div>
        <aside class="media-preview-details">
            <div class="media-preview-details__head">
                <div>
                    <p class="media-preview-kind" data-media-preview-kind></p>
                    <h2 id="media-preview-title" data-media-preview-title></h2>
                </div>
                <button type="button" class="button button--normal button--small button--icon-only" data-media-preview-close aria-label="<?= e(__('common.close')) ?>">
                    <?= admin_icon('cancel') ?>
                </button>
            </div>
            <dl class="media-preview-meta">
                <div>
                    <dt><?= e(__('media.original_file', [], 'Original file')) ?></dt>
                    <dd data-media-preview-original></dd>
                </div>
                <div>
                    <dt><?= e(__('common.type')) ?></dt>
                    <dd data-media-preview-type></dd>
                </div>
                <div>
                    <dt><?= e(__('media.dimensions', [], 'Dimensions')) ?></dt>
                    <dd data-media-preview-dimensions><?= e(__('media.metadata_loading', [], 'Loading...')) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('media.aspect_ratio', [], 'Aspect ratio')) ?></dt>
                    <dd data-media-preview-aspect-ratio><?= e(__('media.metadata_loading', [], 'Loading...')) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('common.size', [], 'Size')) ?></dt>
                    <dd data-media-preview-size></dd>
                </div>
                <div data-media-preview-duration-row hidden>
                    <dt><?= e(__('common.duration')) ?></dt>
                    <dd data-media-preview-duration><?= e(__('media.metadata_loading', [], 'Loading...')) ?></dd>
                </div>
                <div>
                    <dt><?= e(__('media.used_by_slides')) ?></dt>
                    <dd data-media-preview-usage></dd>
                </div>
                <div>
                    <dt><?= e(__('media.uploaded_by')) ?></dt>
                    <dd data-media-preview-uploaded-by></dd>
                </div>
            </dl>
        </aside>
    </div>
</dialog>
<script>
(() => {
    const dialog = document.querySelector('[data-media-preview-dialog]');
    if (!dialog) return;

    const image = dialog.querySelector('[data-media-preview-image]');
    const video = dialog.querySelector('[data-media-preview-video]');
    const stage = dialog.querySelector('[data-media-preview-stage]');
    const durationRow = dialog.querySelector('[data-media-preview-duration-row]');
    let activeKind = '';
    const fields = {
        title: dialog.querySelector('[data-media-preview-title]'),
        kind: dialog.querySelector('[data-media-preview-kind]'),
        original: dialog.querySelector('[data-media-preview-original]'),
        type: dialog.querySelector('[data-media-preview-type]'),
        dimensions: dialog.querySelector('[data-media-preview-dimensions]'),
        aspectRatio: dialog.querySelector('[data-media-preview-aspect-ratio]'),
        size: dialog.querySelector('[data-media-preview-size]'),
        duration: dialog.querySelector('[data-media-preview-duration]'),
        usage: dialog.querySelector('[data-media-preview-usage]'),
        uploadedBy: dialog.querySelector('[data-media-preview-uploaded-by]'),
    };
    const labels = {
        loading: <?= json_encode(__('media.metadata_loading', [], 'Loading...')) ?>,
        unknown: <?= json_encode(__('common.unknown', [], 'Unknown')) ?>,
    };

    const setText = (node, value) => {
        if (node) node.textContent = value || labels.unknown;
    };

    const knownResolutions = new Map([
        ['1280x720', 'HD'],
        ['1366x768', 'WXGA'],
        ['1600x900', 'HD+'],
        ['1920x1080', 'Full HD'],
        ['1920x1200', 'WUXGA'],
        ['2560x1080', 'Ultrawide Full HD'],
        ['2560x1440', 'QHD'],
        ['3440x1440', 'UWQHD'],
        ['3840x2160', '4K UHD'],
        ['4096x2160', 'DCI 4K'],
        ['5120x2880', '5K'],
        ['7680x4320', '8K UHD'],
    ]);

    const gcd = (left, right) => {
        left = Math.abs(left);
        right = Math.abs(right);
        while (right) {
            const next = right;
            right = left % right;
            left = next;
        }
        return left || 1;
    };

    const findKnownResolution = (width, height) => {
        const landscapeWidth = Math.max(width, height);
        const landscapeHeight = Math.min(width, height);
        return knownResolutions.get(`${landscapeWidth}x${landscapeHeight}`) || '';
    };

    const setMediaGeometry = (width, height) => {
        if (width <= 0 || height <= 0) {
            setText(fields.dimensions, labels.unknown);
            setText(fields.aspectRatio, labels.unknown);
            return;
        }

        const divisor = gcd(width, height);
        const knownResolution = findKnownResolution(width, height);
        setText(fields.dimensions, knownResolution ? `${width} x ${height} (${knownResolution})` : `${width} x ${height}`);
        setText(fields.aspectRatio, `${width / divisor}:${height / divisor}`);
    };

    const formatDuration = (seconds) => {
        if (!Number.isFinite(seconds) || seconds <= 0) return labels.unknown;
        const total = Math.round(seconds);
        const hours = Math.floor(total / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const remainder = total % 60;
        const two = (value) => String(value).padStart(2, '0');
        return hours > 0 ? `${hours}:${two(minutes)}:${two(remainder)}` : `${minutes}:${two(remainder)}`;
    };

    const clearPreview = () => {
        activeKind = '';
        image.hidden = true;
        video.hidden = true;
        image.removeAttribute('src');
        video.pause();
        video.removeAttribute('src');
        video.load();
    };

    const closePreview = () => {
        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
            return;
        }
        dialog.removeAttribute('open');
        clearPreview();
    };

    const openPreview = (row) => {
        const media = row.dataset;
        clearPreview();
        setText(fields.title, media.mediaName);
        setText(fields.kind, media.mediaType);
        setText(fields.original, media.mediaOriginal);
        setText(fields.type, media.mediaMime ? `${media.mediaType} - ${media.mediaMime}` : media.mediaType);
        setText(fields.dimensions, labels.loading);
        setText(fields.aspectRatio, labels.loading);
        setText(fields.size, media.mediaSize);
        setText(fields.usage, media.mediaUsage);
        setText(fields.uploadedBy, media.mediaUploadedBy);

        const isVideo = media.mediaKind === 'video';
        activeKind = isVideo ? 'video' : 'image';
        durationRow.hidden = !isVideo;
        setText(fields.duration, labels.loading);

        if (isVideo) {
            video.hidden = false;
            video.src = media.mediaUrl;
            video.load();
        } else {
            image.hidden = false;
            image.alt = media.mediaName || '';
            image.src = media.mediaUrl;
        }

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
        stage?.focus?.();
    };

    image.addEventListener('load', () => {
        if (activeKind === 'image') setMediaGeometry(image.naturalWidth, image.naturalHeight);
    });
    image.addEventListener('error', () => {
        if (activeKind === 'image') setMediaGeometry(0, 0);
    });
    video.addEventListener('loadedmetadata', () => {
        if (activeKind !== 'video') return;
        setMediaGeometry(video.videoWidth, video.videoHeight);
        setText(fields.duration, formatDuration(video.duration));
    });
    video.addEventListener('error', () => {
        if (activeKind !== 'video') return;
        setMediaGeometry(0, 0);
        setText(fields.duration, labels.unknown);
    });

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const trigger = target?.closest('[data-media-preview-open]');
        if (!trigger) return;
        const row = trigger.closest('[data-media-preview-row]');
        if (!row) return;
        event.preventDefault();
        openPreview(row);
    });

    dialog.querySelector('[data-media-preview-close]')?.addEventListener('click', closePreview);
    dialog.addEventListener('close', clearPreview);
})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
