<?php
$slideTypeDefinitions = array_values($slideTypeDefinitions ?? []);
$slideTypeCreateUrl = (string)($slideTypeCreateUrl ?? url('/admin/slides/create'));
$slideTypeReturnTo = (string)($slideTypeReturnTo ?? '/admin/slides');
$fallbackSlideType = [
    'slide_type' => 'image',
    'label' => enum_label('slide_types', 'image', 'image'),
    'description' => __('slide.type_descriptions.image'),
    'icon_url' => url('/assets/img/slides/slide_generic.png'),
    'icon_fallback_url' => url('/assets/img/slides/slide_generic.png'),
];
$slideTypeDefinitions = $slideTypeDefinitions ?: [$fallbackSlideType];
$firstSlideType = $slideTypeDefinitions[0] ?? $fallbackSlideType;
$firstSlideTypeCreateUrl = $slideTypeCreateUrl
    . (str_contains($slideTypeCreateUrl, '?') ? '&' : '?')
    . 'slide_type=' . rawurlencode((string)$firstSlideType['slide_type'])
    . '&return_to=' . rawurlencode($slideTypeReturnTo);
?>
<dialog class="admin-dialog slide-type-dialog" data-slide-type-dialog aria-labelledby="slide-type-dialog-title" aria-describedby="slide-type-dialog-description">
    <form method="dialog" class="admin-dialog__panel slide-type-dialog__panel">
        <div class="section-head">
            <div>
                <h2 id="slide-type-dialog-title"><?= e(__('slide.choose_type_title')) ?></h2>
                <p id="slide-type-dialog-description" class="muted"><?= e(__('slide.choose_type_hint')) ?></p>
            </div>
        </div>
        <div class="slide-type-dialog__layout">
            <div class="slide-type-grid" data-slide-type-options role="radiogroup" aria-labelledby="slide-type-dialog-title">
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
                        role="radio"
                        aria-checked="<?= $index === 0 ? 'true' : 'false' ?>"
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
        let opener = null;

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
                item.setAttribute('aria-checked', item === option ? 'true' : 'false');
                item.tabIndex = item === option ? 0 : -1;
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
            option.tabIndex = option === selectedSlideTypeOption ? 0 : -1;
            option.addEventListener('click', () => selectSlideType(option));
            option.addEventListener('dblclick', () => {
                selectSlideType(option);
                if (continueLink) window.location.href = continueLink.href;
            });
            option.addEventListener('keydown', (event) => {
                if (!['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                const current = slideTypeOptions.indexOf(selectedSlideTypeOption);
                const last = slideTypeOptions.length - 1;
                let next = current;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = last;
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = current >= last ? 0 : current + 1;
                if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = current <= 0 ? last : current - 1;
                selectSlideType(slideTypeOptions[next]);
                selectedSlideTypeOption?.focus();
            });
        });
        openSlideTypeDialog.addEventListener('click', (event) => {
            event.preventDefault();
            opener = openSlideTypeDialog;
            selectSlideType(selectedSlideTypeOption);
            slideTypeDialog.showModal();
            selectedSlideTypeOption?.focus();
        });
        slideTypeDialog.querySelectorAll('[data-slide-type-close]').forEach((button) => {
            button.addEventListener('click', () => slideTypeDialog.close());
        });
        slideTypeDialog.addEventListener('close', () => {
            opener?.focus?.({ preventScroll: true });
            opener = null;
        });
    }
})();
</script>
