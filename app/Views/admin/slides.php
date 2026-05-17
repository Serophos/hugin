<?php
$title = __('slide.plural');
$allSlides = $allSlides ?? [];
$pluginLabels = $pluginLabels ?? [];
$slideTypeDefinitions = array_values($slideTypeDefinitions ?? []);
$fallbackSlideType = [
    'slide_type' => 'image',
    'label' => enum_label('slide_types', 'image', 'image'),
    'description' => __('slide.type_descriptions.image'),
    'icon_url' => url('/assets/img/slides/slide_generic.png'),
    'icon_fallback_url' => url('/assets/img/slides/slide_generic.png'),
];
$slideTypeDefinitions = $slideTypeDefinitions ?: [$fallbackSlideType];
$firstSlideType = $slideTypeDefinitions[0] ?? $fallbackSlideType;
$firstSlideTypeCreateUrl = url('/admin/slides/create?slide_type=' . rawurlencode((string)$firstSlideType['slide_type']) . '&return_to=' . rawurlencode('/admin/slides'));
$slideTypeOptions = [];
foreach ($allSlides as $slide) {
    $typeLabel = $pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', (string)$slide['slide_type'], (string)$slide['slide_type']);
    $slideTypeOptions[$typeLabel] = $typeLabel;
}
natcasesort($slideTypeOptions);

require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-head">
    <div><h1><?= e(__('slide.plural')) ?></h1><p class="muted"><?= e(__('slide.library_hint')) ?></p></div>
    <a class="button button--default" href="<?= e($firstSlideTypeCreateUrl) ?>" data-open-slide-type-dialog data-create-url="<?= e(url('/admin/slides/create')) ?>" data-return-to="/admin/slides" aria-haspopup="dialog"><?= admin_icon('add') ?><span><?= e(__('slide.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

<section class="slide-workspace-section">
    <!--div class="section-head">
        <div>
            <h2><?= e(__('slide.library')) ?></h2>
            <p class="muted"><?= e(__('slide.library_hint')) ?></p>
        </div>
    </div-->
    <details class="card slide-group" open>
        <summary>
            <span class="slide-group__title">
                <span class="slide-group__chevron" aria-hidden="true"></span>
                <span>
                    <h2><?= e(__('slide.all_slides')) ?></h2>
                    <small><?= e(__('slide.unique_slide_count', ['count' => count($allSlides)])) ?></small>
                </span>
            </span>
            <span class="slide-group__hint"><?= e(__('slide.all_slides_hint')) ?></span>
        </summary>
        <div class="slide-group__body">
            <?php if ($allSlides === []): ?>
                <p class="muted slide-group__empty"><?= e(__('slide.no_slides')) ?></p>
            <?php else: ?>
                <div class="slide-library-toolbar">
                    <span class="slide-library-toolbar__meta" data-slide-library-count data-template="<?= e(__('slide.library_filter_count', ['visible' => '__VISIBLE__', 'total' => '__TOTAL__'])) ?>" aria-live="polite"></span>
                    <button type="button" class="button button--normal button--small" data-slide-library-reset hidden><?= e(__('slide.clear_filters')) ?></button>
                </div>
                <div class="table-scroll">
                    <table class="admin-table slide-library-table" data-admin-table data-slide-library-table>
                        <thead>
                            <tr>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="type" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.type')])) ?>"><?= e(__('common.type')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="channels" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('slide.assigned_channel_names')])) ?>"><?= e(__('slide.assigned_channel_names')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="duration" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.duration')])) ?>"><?= e(__('common.duration')) ?></button></th>
                                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="status" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.status')])) ?>"><?= e(__('common.status')) ?></button></th>
                                <th><?= e(__('common.actions')) ?></th>
                            </tr>
                            <tr class="slide-library-filter-row">
                                <th><input type="search" data-admin-filter="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
                                <th>
                                    <select data-admin-filter="type" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.type')])) ?>">
                                        <option value=""><?= e(__('slide.filter_all_types')) ?></option>
                                        <?php foreach ($slideTypeOptions as $typeLabel): ?>
                                            <option value="<?= e($typeLabel) ?>"><?= e($typeLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </th>
                                <th><input type="search" data-admin-filter="channels" aria-label="<?= e(__('slide.filter_column', ['column' => __('slide.assigned_channel_names')])) ?>" placeholder="<?= e(__('slide.assigned_channel_names')) ?>"></th>
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
                        <tbody>
                        <?php foreach ($allSlides as $slide): ?>
                            <?php
                            $typeLabel = $pluginLabels[$slide['slide_type']] ?? enum_label('slide_types', (string)$slide['slide_type'], (string)$slide['slide_type']);
                            $channelLabel = $slide['channel_names'] ?: __('slide.no_channels');
                            $durationSort = $slide['duration_seconds'] ?? '';
                            $durationLabel = (string)($slide['duration_seconds'] ?? __('common.default'));
                            $statusValue = $slide['is_active'] ? 'active' : 'inactive';
                            $statusLabel = $slide['is_active'] ? __('common.active') : __('common.inactive');
                            ?>
                            <tr data-admin-row>
                                <td data-admin-cell="name" data-sort-value="<?= e((string)$slide['name']) ?>" data-filter-value="<?= e((string)$slide['name']) ?>"><?= e($slide['name']) ?></td>
                                <td data-admin-cell="type" data-sort-value="<?= e($typeLabel) ?>" data-filter-value="<?= e($typeLabel) ?>"><?= e($typeLabel) ?></td>
                                <td data-admin-cell="channels" data-sort-value="<?= e($channelLabel) ?>" data-filter-value="<?= e($channelLabel) ?>"><?= e($channelLabel) ?></td>
                                <td data-admin-cell="duration" data-sort-value="<?= e((string)$durationSort) ?>" data-filter-value="<?= e($durationLabel) ?>"><?= e($durationLabel) ?></td>
                                <td data-admin-cell="status" data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
                                <td class="actions">
                                    <a class="button button--normal button--small" href="<?= e(url('/admin/slides/' . $slide['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                                    <form
                                        method="post"
                                        action="<?= e(url('/admin/slides/' . $slide['id'] . '/delete')) ?>"
                                        class="inline-form"
                                        data-confirm-submit
                                        data-confirm-title="<?= e(__('common.delete')) ?>"
                                        data-confirm-message="<?= e(__('slide.delete_everywhere_confirm', ['slide' => $slide['name'], 'count' => (int)($slide['channel_count'] ?? 0)])) ?>"
                                        data-confirm-accept="<?= e(__('common.delete')) ?>"
                                    >
                                        <?= csrf_field() ?>
                                        <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                                    </form>
                                    <a class="button button--normal button--small" target="_blank" rel="noopener noreferrer" href="<?= e(url('/preview-slide/' . $slide['id'])) ?>"><?= admin_icon('preview') ?><span><?= e(__('common.preview')) ?></span></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="muted slide-group__empty" data-slide-library-empty hidden><?= e(__('slide.library_filter_empty')) ?></p>
            <?php endif; ?>
        </div>
    </details>
</section>

<dialog class="admin-dialog slide-type-dialog" data-slide-type-dialog>
    <form method="dialog" class="admin-dialog__panel slide-type-dialog__panel">
        <div class="section-head">
            <div>
                <h2><?= e(__('slide.choose_type_title')) ?></h2>
                <p class="muted"><?= e(__('slide.choose_type_hint')) ?></p>
            </div>
            <button type="button" class="button button--normal button--small" data-slide-type-close><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></button>
        </div>
        <div class="slide-type-dialog__layout">
            <div class="slide-type-grid" data-slide-type-options>
                <?php foreach ($slideTypeDefinitions as $index => $slideType): ?>
                    <?php
                    $description = trim((string)($slideType['description'] ?? ''));
                    if ($description === '') {
                        $description = __('slide.type_description_unavailable');
                    }
                    ?>
                    <button
                        type="button"
                        class="slide-type-card"
                        data-slide-type-option
                        data-slide-type="<?= e((string)$slideType['slide_type']) ?>"
                        data-label="<?= e((string)$slideType['label']) ?>"
                        data-description="<?= e($description) ?>"
                        data-icon="<?= e((string)$slideType['icon_url']) ?>"
                        data-fallback-icon="<?= e((string)$slideType['icon_fallback_url']) ?>"
                        aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
                    >
                        <span class="slide-type-card__icon">
                            <img src="<?= e((string)$slideType['icon_url']) ?>" data-fallback-icon="<?= e((string)$slideType['icon_fallback_url']) ?>" alt="">
                        </span>
                        <span class="slide-type-card__name"><?= e((string)$slideType['label']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <aside class="slide-type-detail" data-slide-type-detail>
                <img class="slide-type-detail__icon" src="<?= e((string)$firstSlideType['icon_url']) ?>" data-slide-type-detail-icon data-fallback-icon="<?= e((string)$firstSlideType['icon_fallback_url']) ?>" alt="">
                <div>
                    <h3 data-slide-type-detail-title><?= e((string)$firstSlideType['label']) ?></h3>
                    <p class="muted" data-slide-type-detail-description><?= e((string)(($firstSlideType['description'] ?? '') ?: __('slide.type_description_unavailable'))) ?></p>
                </div>
            </aside>
        </div>
        <div class="form-actions">
            <button type="button" class="button button--normal" data-slide-type-close><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></button>
            <a class="button button--default" href="<?= e($firstSlideTypeCreateUrl) ?>" data-slide-type-continue><?= admin_icon('add') ?><span><?= e(__('slide.create_selected_type')) ?></span></a>
        </div>
    </form>
</dialog>

<script>
(() => {
    const slideTypeDialog = document.querySelector('[data-slide-type-dialog]');
    const openSlideTypeDialog = document.querySelector('[data-open-slide-type-dialog]');
    if (slideTypeDialog && openSlideTypeDialog && typeof slideTypeDialog.showModal === 'function') {
        const slideTypeOptions = Array.from(slideTypeDialog.querySelectorAll('[data-slide-type-option]'));
        const detailIcon = slideTypeDialog.querySelector('[data-slide-type-detail-icon]');
        const detailTitle = slideTypeDialog.querySelector('[data-slide-type-detail-title]');
        const detailDescription = slideTypeDialog.querySelector('[data-slide-type-detail-description]');
        const continueLink = slideTypeDialog.querySelector('[data-slide-type-continue]');
        const fallbackDescription = <?= json_encode(__('slide.type_description_unavailable'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let selectedSlideTypeOption = slideTypeOptions.find((option) => option.getAttribute('aria-pressed') === 'true') || slideTypeOptions[0] || null;

        function buildSlideTypeUrl(slideType) {
            const target = new URL(openSlideTypeDialog.dataset.createUrl || openSlideTypeDialog.href, window.location.href);
            target.searchParams.set('slide_type', slideType);
            target.searchParams.set('return_to', openSlideTypeDialog.dataset.returnTo || '/admin/slides');
            return target.toString();
        }

        function useFallbackIcon(event) {
            const image = event.currentTarget;
            if (image.dataset.fallbackApplied === '1' || !image.dataset.fallbackIcon) return;
            image.dataset.fallbackApplied = '1';
            image.src = image.dataset.fallbackIcon;
        }

        function selectSlideType(option) {
            if (!option) return;
            selectedSlideTypeOption = option;
            slideTypeOptions.forEach((item) => {
                item.setAttribute('aria-pressed', item === option ? 'true' : 'false');
            });

            const label = option.dataset.label || option.textContent.trim();
            const description = option.dataset.description || fallbackDescription;
            const icon = option.dataset.icon || option.dataset.fallbackIcon || '';
            const fallbackIcon = option.dataset.fallbackIcon || '';

            if (detailTitle) detailTitle.textContent = label;
            if (detailDescription) detailDescription.textContent = description;
            if (detailIcon && icon) {
                delete detailIcon.dataset.fallbackApplied;
                detailIcon.dataset.fallbackIcon = fallbackIcon;
                detailIcon.src = icon;
            }
            if (continueLink) {
                continueLink.href = buildSlideTypeUrl(option.dataset.slideType || 'image');
            }
        }

        slideTypeDialog.querySelectorAll('img[data-fallback-icon]').forEach((image) => {
            image.addEventListener('error', useFallbackIcon);
        });
        slideTypeOptions.forEach((option) => {
            option.addEventListener('click', () => selectSlideType(option));
            option.addEventListener('dblclick', () => {
                selectSlideType(option);
                if (continueLink) window.location.href = continueLink.href;
            });
        });
        openSlideTypeDialog.addEventListener('click', (event) => {
            event.preventDefault();
            selectSlideType(selectedSlideTypeOption);
            slideTypeDialog.showModal();
            selectedSlideTypeOption?.focus();
        });
        slideTypeDialog.querySelectorAll('[data-slide-type-close]').forEach((button) => {
            button.addEventListener('click', () => slideTypeDialog.close());
        });
    }

    const libraryTable = document.querySelector('[data-slide-library-table]');
    if (libraryTable) {
        const rows = Array.from(libraryTable.querySelectorAll('[data-admin-row]'));
        const filterControls = Array.from(libraryTable.querySelectorAll('[data-admin-filter]'));
        const resetFilters = document.querySelector('[data-slide-library-reset]');
        const count = document.querySelector('[data-slide-library-count]');
        const empty = document.querySelector('[data-slide-library-empty]');
        const total = rows.length;
        const normalize = (value) => String(value || '').trim();

        function updateLibrarySummary() {
            const visible = rows.filter((row) => !row.hidden).length;
            if (count) {
                const template = count.dataset.template || '__VISIBLE__ / __TOTAL__';
                count.textContent = template.replace('__VISIBLE__', String(visible)).replace('__TOTAL__', String(total));
            }
            if (empty) {
                empty.hidden = visible > 0;
            }
            if (resetFilters) {
                resetFilters.hidden = !filterControls.some((control) => normalize(control.value) !== '');
            }
        }

        function scheduleLibrarySummaryUpdate() {
            window.requestAnimationFrame(updateLibrarySummary);
        }

        filterControls.forEach((control) => {
            control.addEventListener('input', scheduleLibrarySummaryUpdate);
            control.addEventListener('change', scheduleLibrarySummaryUpdate);
        });

        resetFilters?.addEventListener('click', () => {
            filterControls.forEach((control) => {
                control.value = '';
                control.dispatchEvent(new Event('input', { bubbles: true }));
            });
            scheduleLibrarySummaryUpdate();
            filterControls[0]?.focus();
        });

        updateLibrarySummary();
    }

})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
