<?php
$uploadForm = 'media_upload';
$title = __('media.title');
require __DIR__ . '/../layouts/admin_header.php';
?>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<?php
$baseMediaUrl = url('/admin/media');
$kindQuery = $kind !== '' ? '?kind=' . rawurlencode($kind) : '';
$allUrl = $baseMediaUrl;
$imageUrl = $baseMediaUrl . '?kind=image';
$videoUrl = $baseMediaUrl . '?kind=video';
$fontUrl = $baseMediaUrl . '?kind=font';
$prevPageUrl = $baseMediaUrl . ($kind !== '' ? '?kind=' . rawurlencode($kind) . '&page=' . max(1, $page - 1) : '?page=' . max(1, $page - 1));
$nextPageUrl = $baseMediaUrl . ($kind !== '' ? '?kind=' . rawurlencode($kind) . '&page=' . ($page + 1) : '?page=' . ($page + 1));
$fontPreviewText = __('media.font_preview_default', [], 'The quick brown fox jumps over the lazy dog');
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
                <input type="file" name="media_file" accept="image/*,video/*,.woff2,.woff,.ttf,.otf,font/woff2,font/woff,font/ttf,font/otf" required<?= field_attrs('media_file', $uploadForm, field_note_id('media_file', $uploadForm)) ?>>
                <?= field_error_html('media_file', $uploadForm) ?>
                <small id="<?= e(field_note_id('media_file', $uploadForm)) ?>" class="field-note"><?= e(__('forms.file_reselect_hint')) ?></small>
            </label>
            <label class="full-width"><?= e(__('media.license_note')) ?>
                <textarea name="license_note" rows="3"<?= field_attrs('license_note', $uploadForm) ?>><?= e((string)old('license_note', '', $uploadForm)) ?></textarea>
                <?= field_error_html('license_note', $uploadForm) ?>
                <small class="field-note"><?= e(__('media.font_license_warning')) ?></small>
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
    <a class="tab<?= $kind === 'font' ? ' active' : '' ?>" href="<?= e($fontUrl) ?>"><?= e(__('media.fonts')) ?></a>
</div>

<?php if ($media): ?>
    <style data-media-font-previews>
        <?php foreach ($media as $asset): ?>
            <?php if (($asset['media_kind'] ?? '') === 'font'): ?>
                <?= uploaded_font_face_css($asset) ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </style>
<?php endif; ?>

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
                $assetPreviewUrl = $assetKind === 'video' && !empty($asset['preview_file_path'])
                    ? url('/api/media/' . (int)$asset['id'] . '/preview')
                    : '';
                $assetTypeLabel = $assetKind === 'font' ? __('media.font', [], 'Font') : enum_label('slide_types', $assetKind, $assetKind);
                $assetSize = format_bytes((int)$asset['file_size']);
                $assetUploadedBy = $asset['uploaded_by'] ?? __('common.unknown', [], 'Unknown');
                $fontCssFamily = $assetKind === 'font' ? uploaded_font_css_family((int)$asset['id']) : '';
                $fontFamilyName = (string)($asset['font_family_name'] ?? '');
                $fontFullName = (string)($asset['font_full_name'] ?? '');
                $fontSubfamily = (string)($asset['font_subfamily'] ?? '');
                $fontWeight = (string)($asset['font_weight'] ?? '');
                $fontFormat = strtoupper((string)($asset['font_format'] ?? ''));
                $fontPostscriptName = (string)($asset['font_postscript_name'] ?? '');
                $fontVersion = (string)($asset['font_version'] ?? '');
                $licenseNote = (string)($asset['license_note'] ?? '');
                ?>
                <tr
                    data-admin-row
                    data-media-preview-row
                    data-media-url="<?= e($assetUrl) ?>"
                    data-media-preview-url="<?= e($assetPreviewUrl) ?>"
                    data-media-kind="<?= e($assetKind) ?>"
                    data-media-name="<?= e($asset['name']) ?>"
                    data-media-original="<?= e($asset['original_name']) ?>"
                    data-media-type="<?= e($assetTypeLabel) ?>"
                    data-media-mime="<?= e($asset['mime_type']) ?>"
                    data-media-size="<?= e($assetSize) ?>"
                    data-media-usage="<?= e((string)$asset['usage_count']) ?>"
                    data-media-uploaded-by="<?= e($assetUploadedBy) ?>"
                    data-media-font-css-family="<?= e($fontCssFamily) ?>"
                    data-media-font-family="<?= e($fontFamilyName) ?>"
                    data-media-font-full-name="<?= e($fontFullName) ?>"
                    data-media-font-subfamily="<?= e($fontSubfamily) ?>"
                    data-media-font-weight="<?= e($fontWeight) ?>"
                    data-media-font-format="<?= e($fontFormat) ?>"
                    data-media-font-postscript="<?= e($fontPostscriptName) ?>"
                    data-media-font-version="<?= e($fontVersion) ?>"
                    data-media-license-note="<?= e($licenseNote) ?>"
                >
                    <td class="preview-cell">
                        <button type="button" class="media-thumb-button" data-media-preview-open aria-label="<?= e(__('media.open_preview_for', ['name' => $asset['name']], 'Preview :name')) ?>">
                        <?php if ($assetKind === 'image'): ?>
                            <img class="thumb" src="<?= e($assetUrl) ?>" alt="<?= e($asset['name']) ?>">
                        <?php elseif ($assetPreviewUrl !== ''): ?>
                            <img class="thumb" src="<?= e($assetPreviewUrl) ?>" alt="<?= e($asset['name']) ?>">
                        <?php elseif ($assetKind === 'font'): ?>
                            <span class="media-font-thumb" style="font-family: '<?= e($fontCssFamily) ?>', sans-serif;">Ag</span>
                        <?php else: ?>
                            <video class="thumb" src="<?= e($assetUrl) ?>" muted preload="metadata"></video>
                        <?php endif; ?>
                        </button>
                    </td>
                    <td class="media-name-cell" data-admin-cell="name" data-sort-value="<?= e((string)$asset['name']) ?>" data-filter-value="<?= e(trim((string)$asset['name'] . ' ' . (string)$asset['original_name'])) ?>">
                        <strong class="break-word"><?= e($asset['name']) ?></strong><br>
                        <span class="muted break-word"><?= e($asset['original_name']) ?></span>
                        <?php if ($assetKind === 'font' && ($fontFamilyName !== '' || $fontFullName !== '')): ?>
                            <br><span class="muted break-word"><?= e($fontFullName !== '' ? $fontFullName : $fontFamilyName) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="break-word" data-admin-cell="type" data-sort-value="<?= e($assetTypeLabel) ?>" data-filter-value="<?= e(trim($assetTypeLabel . ' ' . (string)$asset['mime_type'] . ' ' . $fontFormat . ' ' . $fontSubfamily . ' ' . $fontWeight)) ?>"><?= e($assetTypeLabel) ?><?= $fontFormat !== '' ? ' · ' . e($fontFormat) : '' ?><br><span class="muted break-word"><?= e($asset['mime_type']) ?></span><?php if ($assetKind === 'font' && ($fontSubfamily !== '' || $fontWeight !== '')): ?><br><span class="muted break-word"><?= e(trim($fontSubfamily . ($fontWeight !== '' ? ' · ' . $fontWeight : ''))) ?></span><?php endif; ?></td>
                    <td data-admin-cell="size" data-sort-value="<?= e((string)$asset['file_size']) ?>" data-filter-value="<?= e($assetSize) ?>"><?= e($assetSize) ?></td>
                    <td data-admin-cell="usage" data-sort-value="<?= e((string)$asset['usage_count']) ?>" data-filter-value="<?= e((string)$asset['usage_count']) ?>"><?= e((string)$asset['usage_count']) ?></td>
                    <td class="break-word" data-admin-cell="uploaded_by" data-sort-value="<?= e($assetUploadedBy) ?>" data-filter-value="<?= e($assetUploadedBy) ?>"><?= e($assetUploadedBy) ?></td>
                    <td class="actions">
                        <button type="button" class="button button--normal button--small" data-media-preview-open><?= admin_icon('open') ?><span><?= e(__('common.open')) ?></span></button>
                        <?php if (in_array(current_user_role(), ['admin', 'editor'], true)): ?>
                            <form method="post" action="<?= e(url('/admin/media/' . $asset['id'] . '/delete')) ?>" class="inline-form" data-dialog-submit data-dialog-title="<?= e(__('common.delete')) ?>" data-dialog-message="<?= e(__('media.delete_confirm')) ?>" data-dialog-icon="trash" data-dialog-buttons="cancel,delete" data-dialog-accept="<?= e(__('common.delete')) ?>">
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
<dialog class="media-preview-dialog" data-media-preview-dialog aria-labelledby="media-preview-title" aria-describedby="media-preview-description">
    <div class="media-preview-shell">
        <div class="media-preview-stage" data-media-preview-stage tabindex="-1">
            <img class="media-preview-asset" data-media-preview-image alt="" hidden>
            <video class="media-preview-asset" data-media-preview-video controls playsinline preload="metadata" hidden></video>
            <div class="media-font-preview" data-media-preview-font hidden>
                <p data-media-preview-font-line><?= e($fontPreviewText) ?></p>
                <p data-media-preview-font-line><?= e(__('media.font_preview_hamburgefontsiv', [], 'Hamburgefontsiv')) ?></p>
                <p data-media-preview-font-line><?= e(__('media.font_preview_numbers', [], '0123456789')) ?></p>
                <p data-media-preview-font-line><?= e(__('media.font_preview_german', [], 'Falsches Üben von Xylophonmusik quält jeden größeren Zwerg.')) ?></p>
                <p data-media-preview-font-line><?= e(__('media.font_preview_turkish', [], 'Pijamalı hasta yağız şoföre çabucak güvendi.')) ?></p>
            </div>
        </div>
        <aside class="media-preview-details">
            <div class="media-preview-details__head">
                <div>
                    <p id="media-preview-description" class="media-preview-kind" data-media-preview-kind></p>
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
                <div data-media-preview-dimensions-row>
                    <dt><?= e(__('media.dimensions', [], 'Dimensions')) ?></dt>
                    <dd data-media-preview-dimensions><?= e(__('media.metadata_loading', [], 'Loading...')) ?></dd>
                </div>
                <div data-media-preview-aspect-ratio-row>
                    <dt><?= e(__('media.aspect_ratio', [], 'Aspect ratio')) ?></dt>
                    <dd data-media-preview-aspect-ratio><?= e(__('media.metadata_loading', [], 'Loading...')) ?></dd>
                </div>
                <div data-media-preview-font-row hidden>
                    <dt><?= e(__('media.font_family_name')) ?></dt>
                    <dd data-media-preview-font-family></dd>
                </div>
                <div data-media-preview-font-row hidden>
                    <dt><?= e(__('media.font_full_name')) ?></dt>
                    <dd data-media-preview-font-full-name></dd>
                </div>
                <div data-media-preview-font-row hidden>
                    <dt><?= e(__('media.font_subfamily')) ?></dt>
                    <dd data-media-preview-font-subfamily></dd>
                </div>
                <div data-media-preview-font-row hidden>
                    <dt><?= e(__('media.font_weight')) ?></dt>
                    <dd data-media-preview-font-weight></dd>
                </div>
                <div data-media-preview-font-row hidden>
                    <dt><?= e(__('media.font_postscript_name')) ?></dt>
                    <dd data-media-preview-font-postscript></dd>
                </div>
                <div data-media-preview-font-row hidden>
                    <dt><?= e(__('media.font_version')) ?></dt>
                    <dd data-media-preview-font-version></dd>
                </div>
                <div data-media-preview-font-row hidden>
                    <dt><?= e(__('media.license_note')) ?></dt>
                    <dd data-media-preview-license-note></dd>
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
            <label class="media-font-preview-control" data-media-preview-font-control hidden><?= e(__('media.font_preview_text')) ?>
                <textarea rows="3" data-media-preview-font-input><?= e($fontPreviewText) ?></textarea>
            </label>
        </aside>
    </div>
</dialog>
<script>
(() => {
    const dialog = document.querySelector('[data-media-preview-dialog]');
    if (!dialog) return;

    const image = dialog.querySelector('[data-media-preview-image]');
    const video = dialog.querySelector('[data-media-preview-video]');
    const fontPreview = dialog.querySelector('[data-media-preview-font]');
    const fontPreviewLines = Array.from(dialog.querySelectorAll('[data-media-preview-font-line]'));
    const fontPreviewControl = dialog.querySelector('[data-media-preview-font-control]');
    const fontPreviewInput = dialog.querySelector('[data-media-preview-font-input]');
    const stage = dialog.querySelector('[data-media-preview-stage]');
    const durationRow = dialog.querySelector('[data-media-preview-duration-row]');
    const dimensionsRow = dialog.querySelector('[data-media-preview-dimensions-row]');
    const aspectRatioRow = dialog.querySelector('[data-media-preview-aspect-ratio-row]');
    const fontRows = Array.from(dialog.querySelectorAll('[data-media-preview-font-row]'));
    let activeKind = '';
    let opener = null;
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
        fontFamily: dialog.querySelector('[data-media-preview-font-family]'),
        fontFullName: dialog.querySelector('[data-media-preview-font-full-name]'),
        fontSubfamily: dialog.querySelector('[data-media-preview-font-subfamily]'),
        fontWeight: dialog.querySelector('[data-media-preview-font-weight]'),
        fontPostscript: dialog.querySelector('[data-media-preview-font-postscript]'),
        fontVersion: dialog.querySelector('[data-media-preview-font-version]'),
        licenseNote: dialog.querySelector('[data-media-preview-license-note]'),
    };
    const labels = {
        loading: <?= json_encode(__('media.metadata_loading', [], 'Loading...')) ?>,
        unknown: <?= json_encode(__('common.unknown', [], 'Unknown')) ?>,
        fontPreviewText: <?= json_encode($fontPreviewText, JSON_UNESCAPED_UNICODE) ?>,
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
        video.removeAttribute('poster');
        video.load();
        fontPreview.hidden = true;
        fontPreview.style.fontFamily = '';
        if (fontPreviewInput) fontPreviewInput.value = labels.fontPreviewText;
        fontPreviewLines.forEach((line, index) => {
            if (index === 0) line.textContent = labels.fontPreviewText;
        });
    };

    const closePreview = () => {
        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
            return;
        }
        dialog.removeAttribute('open');
        clearPreview();
        opener?.focus?.({ preventScroll: true });
        opener = null;
    };

    const openPreview = (row, trigger = null) => {
        opener = trigger instanceof HTMLElement ? trigger : (document.activeElement instanceof HTMLElement ? document.activeElement : null);
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
        setText(fields.fontFamily, media.mediaFontFamily);
        setText(fields.fontFullName, media.mediaFontFullName);
        setText(fields.fontSubfamily, media.mediaFontSubfamily);
        setText(fields.fontWeight, media.mediaFontWeight);
        setText(fields.fontPostscript, media.mediaFontPostscript);
        setText(fields.fontVersion, media.mediaFontVersion);
        setText(fields.licenseNote, media.mediaLicenseNote);

        const isFont = media.mediaKind === 'font';
        const isVideo = media.mediaKind === 'video';
        activeKind = isFont ? 'font' : (isVideo ? 'video' : 'image');
        durationRow.hidden = !isVideo;
        dimensionsRow.hidden = isFont;
        aspectRatioRow.hidden = isFont;
        fontRows.forEach(row => { row.hidden = !isFont; });
        if (fontPreviewControl) fontPreviewControl.hidden = !isFont;
        setText(fields.duration, labels.loading);

        if (isFont) {
            fontPreview.hidden = false;
            fontPreview.style.fontFamily = media.mediaFontCssFamily ? `"${media.mediaFontCssFamily}", sans-serif` : 'sans-serif';
            if (fontPreviewInput) {
                fontPreviewInput.value = labels.fontPreviewText;
                fontPreviewInput.dispatchEvent(new Event('input'));
            }
            setText(fields.dimensions, labels.unknown);
            setText(fields.aspectRatio, labels.unknown);
        } else if (isVideo) {
            video.hidden = false;
            if (media.mediaPreviewUrl) {
                video.poster = media.mediaPreviewUrl;
            } else {
                video.removeAttribute('poster');
            }
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
    fontPreviewInput?.addEventListener('input', () => {
        const text = fontPreviewInput.value.trim() || labels.fontPreviewText;
        if (fontPreviewLines[0]) fontPreviewLines[0].textContent = text;
    });

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const trigger = target?.closest('[data-media-preview-open]');
        if (!trigger) return;
        const row = trigger.closest('[data-media-preview-row]');
        if (!row) return;
        event.preventDefault();
        openPreview(row, trigger);
    });

    dialog.querySelector('[data-media-preview-close]')?.addEventListener('click', closePreview);
    dialog.addEventListener('close', () => {
        clearPreview();
        opener?.focus?.({ preventScroll: true });
        opener = null;
    });
})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
