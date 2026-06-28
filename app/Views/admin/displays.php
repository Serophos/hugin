<?php
$title = __('display.plural');
$breadcrumbs = [['label' => $title]];
$displayIcons = is_array($displayIcons ?? null) ? $displayIcons : [];
$defaultDisplayIcon = (string)($defaultDisplayIcon ?? ($displayIcons === [] ? '' : array_key_first($displayIcons)));
$defaultDisplayModel = $displayIcons[$defaultDisplayIcon] ?? ($displayIcons === [] ? null : reset($displayIcons));
require __DIR__ . '/../layouts/admin_header.php';
?>
<div class="page-actions">
    <a class="button button--normal" href="<?= e(url('/admin/locations')) ?>"><?= admin_icon('manage') ?><span><?= e(__('locations.manage')) ?></span></a>
    <a
        class="button button--default"
        href="<?= e(url('/admin/displays/create')) ?>"
        <?php if ($displayIcons !== []): ?>
            data-open-display-model-dialog
            data-create-url="<?= e(url('/admin/displays/create')) ?>"
            aria-haspopup="dialog"
        <?php endif; ?>
    ><?= admin_icon('add') ?><span><?= e(__('display.new')) ?></span></a>
</div>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<div class="card">
    <div class="table-scroll">
    <table class="admin-table admin-table--displays" data-admin-table>
        <thead>
        <tr>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="url" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('display.url_label')])) ?>"><?= e(__('display.url_label')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="location" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('locations.singular')])) ?>"><?= e(__('locations.singular')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="group" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('display_groups.singular')])) ?>"><?= e(__('display_groups.singular')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="channels" data-sort-type="number" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('display.channels_count')])) ?>"><?= e(__('display.channels_count')) ?></button></th>
            <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="status" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.status')])) ?>"><?= e(__('common.status')) ?></button></th>
            <th><?= e(__('common.actions')) ?></th>
        </tr>
        <tr class="slide-library-filter-row">
            <th><input type="search" data-admin-filter="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
            <th><input type="search" data-admin-filter="url" aria-label="<?= e(__('slide.filter_column', ['column' => __('display.url_label')])) ?>" placeholder="<?= e(__('display.url_label')) ?>"></th>
            <th><input type="search" data-admin-filter="location" aria-label="<?= e(__('slide.filter_column', ['column' => __('locations.singular')])) ?>" placeholder="<?= e(__('locations.singular')) ?>"></th>
            <th><input type="search" data-admin-filter="group" aria-label="<?= e(__('slide.filter_column', ['column' => __('display_groups.singular')])) ?>" placeholder="<?= e(__('display_groups.singular')) ?>"></th>
            <th><input type="search" data-admin-filter="channels" aria-label="<?= e(__('slide.filter_column', ['column' => __('display.channels_count')])) ?>" placeholder="<?= e(__('display.channels_count')) ?>"></th>
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
        <?php foreach ($displays as $display): ?>
            <?php
            $displayUrl = '/display/' . $display['slug'];
            $locationLabel = $display['location_name'] ?: __('locations.unassigned');
            $groupLabel = $display['group_name'] ?: __('locations.unassigned');
            $statusValue = $display['is_active'] ? 'active' : 'inactive';
            $statusLabel = $display['is_active'] ? __('common.active') : __('common.inactive');
            ?>
            <tr data-admin-row>
                <td data-admin-cell="name" data-sort-value="<?= e((string)$display['name']) ?>" data-filter-value="<?= e((string)$display['name']) ?>"><?= e($display['name']) ?></td>
                <td data-admin-cell="url" data-sort-value="<?= e($displayUrl) ?>" data-filter-value="<?= e($displayUrl) ?>"><a href="<?= e(url($displayUrl)) ?>" target="_blank"><?= e($displayUrl) ?></a></td>
                <td data-admin-cell="location" data-sort-value="<?= e($locationLabel) ?>" data-filter-value="<?= e($locationLabel) ?>"><?= e($locationLabel) ?></td>
                <td data-admin-cell="group" data-sort-value="<?= e($groupLabel) ?>" data-filter-value="<?= e($groupLabel) ?>"><?= e($groupLabel) ?></td>
                <td data-admin-cell="channels" data-sort-value="<?= e((string)$display['channel_count']) ?>" data-filter-value="<?= e((string)$display['channel_count']) ?>"><?= e((string)$display['channel_count']) ?></td>
                <td data-admin-cell="status" data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
                <td class="actions">
                    <a class="button button--normal button--small" href="<?= e(url($displayUrl)) ?>" target="_blank" rel="noopener noreferrer"><?= admin_icon('preview') ?><span><?= e(__('common.preview')) ?></span></a>
                    <a class="button button--normal button--small" href="<?= e(url('/admin/displays/' . $display['id'] . '/edit')) ?>"><?= admin_icon('edit') ?><span><?= e(__('common.edit')) ?></span></a>
                    <form method="post" action="<?= e(url('/admin/displays/' . $display['id'] . '/reload')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="/admin/displays">
                        <button type="submit" class="button button--normal button--small" aria-label="<?= e(__('display.reload_slideshow')) ?>"><?= admin_icon('reload') ?><span><?= e(__('common.reload')) ?></span></button>
                    </form>
                    <form method="post" action="<?= e(url('/admin/displays/' . $display['id'] . '/delete')) ?>" class="inline-form" data-dialog-submit data-dialog-title="<?= e(__('common.delete')) ?>" data-dialog-message="<?= e(__('display.delete_confirm', [], 'Delete display?')) ?>" data-dialog-icon="trash" data-dialog-buttons="cancel,delete" data-dialog-accept="<?= e(__('common.delete')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="button button--danger button--small"><?= admin_icon('delete') ?><span><?= e(__('common.delete')) ?></span></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php if ($displayIcons !== [] && is_array($defaultDisplayModel)): ?>
    <?php
    $firstDisplayModelUrl = url('/admin/displays/create?icon_file=' . rawurlencode((string)$defaultDisplayModel['file']));
    ?>
    <dialog class="admin-dialog display-model-dialog" data-display-model-dialog aria-labelledby="display-model-dialog-title" aria-describedby="display-model-dialog-description">
        <form method="dialog" class="admin-dialog__panel display-model-dialog__panel">
            <div class="section-head display-model-dialog__head">
                <div>
                    <h2 id="display-model-dialog-title"><?= e(__('display.model_picker_title')) ?></h2>
                    <p id="display-model-dialog-description" class="muted"><?= e(__('display.model_picker_hint')) ?></p>
                </div>
            </div>
            <div class="display-model-dialog__scroll">
                <div class="display-model-grid" data-display-model-options role="radiogroup" aria-labelledby="display-model-dialog-title">
                    <?php $displayModelIndex = 0; ?>
                    <?php foreach ($displayIcons as $icon): ?>
                        <?php $isSelected = (string)$icon['file'] === (string)$defaultDisplayModel['file']; ?>
                        <button
                            type="button"
                            class="display-model-card"
                            data-display-model-option
                            data-icon-file="<?= e((string)$icon['file']) ?>"
                            data-label="<?= e((string)$icon['label']) ?>"
                            aria-pressed="<?= $isSelected ? 'true' : 'false' ?>"
                            role="radio"
                            aria-checked="<?= $isSelected ? 'true' : 'false' ?>"
                            tabindex="<?= $isSelected ? '0' : '-1' ?>"
                        >
                            <span class="display-model-card__image">
                                <img src="<?= e((string)$icon['url']) ?>" alt="" loading="<?= $displayModelIndex < 6 ? 'eager' : 'lazy' ?>">
                            </span>
                            <span class="display-model-card__name"><?= e((string)$icon['label']) ?></span>
                        </button>
                        <?php $displayModelIndex++; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="display-model-empty muted" data-display-model-empty hidden><?= e(__('display.model_picker_empty')) ?></p>
            <div class="form-actions display-model-dialog__actions">
                <button type="button" class="button button--normal" data-display-model-close><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></button>
                <a class="button button--default" href="<?= e($firstDisplayModelUrl) ?>" data-display-model-continue><?= admin_icon('add') ?><span><?= e(__('display.model_picker_continue')) ?></span></a>
            </div>
        </form>
    </dialog>

    <script>
    (() => {
        const dialog = document.querySelector('[data-display-model-dialog]');
        const openButton = document.querySelector('[data-open-display-model-dialog]');
        if (!dialog || !openButton || typeof dialog.showModal !== 'function') return;

        const options = Array.from(dialog.querySelectorAll('[data-display-model-option]'));
        const continueLink = dialog.querySelector('[data-display-model-continue]');
        const closeButton = dialog.querySelector('[data-display-model-close]');
        const emptyMessage = dialog.querySelector('[data-display-model-empty]');
        let selectedOption = options.find(option => option.getAttribute('aria-checked') === 'true') || options[0] || null;
        let opener = null;

        if (emptyMessage) {
            emptyMessage.hidden = options.length > 0;
        }

        function buildCreateUrl(iconFile) {
            const target = new URL(openButton.dataset.createUrl || openButton.href, window.location.href);
            target.searchParams.set('icon_file', iconFile || '');
            return target.toString();
        }

        function selectOption(option, focus = false) {
            if (!option) return;
            selectedOption = option;
            options.forEach(item => {
                const selected = item === option;
                item.setAttribute('aria-pressed', selected ? 'true' : 'false');
                item.setAttribute('aria-checked', selected ? 'true' : 'false');
                item.tabIndex = selected ? 0 : -1;
            });
            if (continueLink) {
                continueLink.href = buildCreateUrl(option.dataset.iconFile || '');
            }
            if (focus) {
                option.focus({ preventScroll: true });
            }
        }

        function closeDialog() {
            if (dialog.open) {
                dialog.close();
            }
            opener?.focus?.({ preventScroll: true });
            opener = null;
        }

        function continueWithSelected() {
            if (continueLink) {
                window.location.href = continueLink.href;
            }
        }

        function moveSelection(event) {
            if (!['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const current = Math.max(0, options.indexOf(selectedOption));
            const last = options.length - 1;
            let next = current;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = last;
            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = current >= last ? 0 : current + 1;
            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = current <= 0 ? last : current - 1;
            selectOption(options[next], true);
        }

        options.forEach(option => {
            option.addEventListener('click', () => selectOption(option));
            option.addEventListener('dblclick', () => {
                selectOption(option);
                continueWithSelected();
            });
            option.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    selectOption(option);
                    return;
                }
                moveSelection(event);
            });
        });

        openButton.addEventListener('click', event => {
            event.preventDefault();
            if (options.length === 0) {
                window.location.href = openButton.href;
                return;
            }
            opener = openButton;
            selectOption(selectedOption || options[0]);
            dialog.showModal();
            window.setTimeout(() => selectedOption?.focus({ preventScroll: true }), 0);
        });

        closeButton?.addEventListener('click', closeDialog);
        continueLink?.addEventListener('click', () => {
            opener = null;
        });
        dialog.addEventListener('cancel', event => {
            event.preventDefault();
            closeDialog();
        });
        dialog.addEventListener('click', event => {
            if (event.target === dialog) {
                closeDialog();
            }
        });
    })();
    </script>
<?php endif; ?>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
