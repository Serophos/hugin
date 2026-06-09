<?php
$title = __('plugins.title');
$pluginDialogStrings = [
    'title' => __('plugins.disable_impact_title'),
    'message' => __('plugins.disable_impact_message'),
    'noChannels' => __('slide.no_channels'),
    'active' => __('common.active'),
    'inactive' => __('common.inactive'),
];
require __DIR__ . '/../layouts/admin_header.php';
?>
<?php if ($flash): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
    <?php if (!$plugins): ?>
        <p class="muted"><?= e(__('plugins.none_discovered')) ?></p>
    <?php else: ?>
        <div class="table-scroll">
        <table class="admin-table admin-table--plugins" data-admin-table>
            <thead>
            <tr>
                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="name" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.name')])) ?>"><?= e(__('common.name')) ?></button></th>
                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="type" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('slide.slide_type')])) ?>"><?= e(__('slide.slide_type')) ?></button></th>
                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="version" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('common.version', [], 'Version')])) ?>"><?= e(__('common.version', [], 'Version')) ?></button></th>
                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="description" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('plugins.description')])) ?>"><?= e(__('plugins.description')) ?></button></th>
                <th aria-sort="none"><button type="button" class="slide-library-sort" data-admin-sort="status" data-sort-type="text" aria-label="<?= e(__('slide.sort_by_column', ['column' => __('plugins.status')])) ?>"><?= e(__('plugins.status')) ?></button></th>
                <th><?= e(__('common.actions')) ?></th>
            </tr>
            <tr class="slide-library-filter-row">
                <th><input type="search" data-admin-filter="name" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.name')])) ?>" placeholder="<?= e(__('common.name')) ?>"></th>
                <th><input type="search" data-admin-filter="type" aria-label="<?= e(__('slide.filter_column', ['column' => __('slide.slide_type')])) ?>" placeholder="<?= e(__('slide.slide_type')) ?>"></th>
                <th><input type="search" data-admin-filter="version" aria-label="<?= e(__('slide.filter_column', ['column' => __('common.version', [], 'Version')])) ?>" placeholder="<?= e(__('common.version', [], 'Version')) ?>"></th>
                <th><input type="search" data-admin-filter="description" aria-label="<?= e(__('slide.filter_column', ['column' => __('plugins.description')])) ?>" placeholder="<?= e(__('plugins.description')) ?>"></th>
                <th>
                    <select data-admin-filter="status" aria-label="<?= e(__('slide.filter_column', ['column' => __('plugins.status')])) ?>">
                        <option value=""><?= e(__('slide.filter_all_statuses')) ?></option>
                        <option value="enabled"><?= e(__('common.enabled')) ?></option>
                        <option value="disabled"><?= e(__('common.disabled')) ?></option>
                    </select>
                </th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($plugins as $plugin): ?>
                <?php
                $affectedSlides = array_values($plugin['affected_slides'] ?? []);
                $pluginNameLabel = trim((string)$plugin['display_name'] . ' ' . (string)$plugin['plugin_name']);
                $statusValue = $plugin['is_enabled'] ? 'enabled' : 'disabled';
                $statusLabel = $plugin['is_enabled'] ? __('common.enabled') : __('common.disabled');
                ?>
                <tr data-admin-row>
                    <td data-admin-cell="name" data-sort-value="<?= e($pluginNameLabel) ?>" data-filter-value="<?= e($pluginNameLabel) ?>"><strong><?= e($plugin['display_name']) ?></strong><div class="muted small mono"><?= e($plugin['plugin_name']) ?></div></td>
                    <td data-admin-cell="type" data-sort-value="<?= e((string)$plugin['slide_type']) ?>" data-filter-value="<?= e((string)$plugin['slide_type']) ?>"><?= e($plugin['slide_type']) ?></td>
                    <td data-admin-cell="version" data-sort-value="<?= e((string)$plugin['version']) ?>" data-filter-value="<?= e((string)$plugin['version']) ?>"><?= e($plugin['version']) ?></td>
                    <td data-admin-cell="description" data-sort-value="<?= e((string)$plugin['description']) ?>" data-filter-value="<?= e((string)$plugin['description']) ?>"><?= e($plugin['description']) ?></td>
                    <td data-admin-cell="status" data-sort-value="<?= e($statusLabel) ?>" data-filter-value="<?= e($statusValue) ?>"><?= e($statusLabel) ?></td>
                    <td class="actions">
                        <a class="button button--normal button--small" href="<?= e(url('/admin/plugins/' . $plugin['plugin_name'] . '/settings')) ?>"><?= admin_icon('settings') ?><span><?= e(__('plugins.configure')) ?></span></a>
                        <form
                            method="post"
                            action="<?= e(url('/admin/plugins/' . $plugin['plugin_name'] . '/toggle')) ?>"
                            class="inline-form"
                            <?php if ($plugin['is_enabled'] && $affectedSlides !== []): ?>
                                data-plugin-disable-confirm
                                data-plugin-name="<?= e($plugin['display_name']) ?>"
                                data-plugin-slides="<?= e(json_encode($affectedSlides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
                            <?php endif; ?>
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="enable" value="<?= $plugin['is_enabled'] ? '0' : '1' ?>">
                            <input type="hidden" name="confirm_slide_deactivation" value="0" data-plugin-disable-confirm-input>
                            <button type="submit" class="button button--normal button--small"><?= admin_icon($plugin['is_enabled'] ? 'cancel' : 'add') ?><span><?= e($plugin['is_enabled'] ? __('plugins.disable') : __('plugins.enable')) ?></span></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<dialog class="admin-dialog plugin-impact-dialog" data-plugin-impact-dialog>
    <form method="dialog" class="admin-dialog__panel plugin-impact-dialog__panel">
        <div class="plugin-impact-dialog__head">
            <div class="plugin-impact-dialog__mark" aria-hidden="true">!</div>
            <div>
                <h2 data-plugin-impact-title></h2>
                <p class="muted" data-plugin-impact-message></p>
            </div>
        </div>
        <div>
            <h3><?= e(__('plugins.affected_slides')) ?></h3>
            <div class="plugin-impact-dialog__list" data-plugin-impact-list></div>
        </div>
        <div class="form-actions">
            <button type="button" class="button button--normal" data-plugin-impact-cancel><?= admin_icon('cancel') ?><span><?= e(__('common.cancel')) ?></span></button>
            <button type="button" class="button button--danger" data-plugin-impact-accept><?= admin_icon('cancel') ?><span><?= e(__('plugins.disable_and_inactivate')) ?></span></button>
        </div>
    </form>
</dialog>

<script>
(() => {
    const dialog = document.querySelector('[data-plugin-impact-dialog]');
    const strings = <?= json_encode($pluginDialogStrings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    let pendingForm = null;

    function parseSlides(form) {
        try {
            const slides = JSON.parse(form.dataset.pluginSlides || '[]');
            return Array.isArray(slides) ? slides : [];
        } catch (error) {
            return [];
        }
    }

    function confirmMessage(pluginName, count) {
        return (strings.message || '')
            .replace(':plugin', pluginName)
            .replace(':count', String(count));
    }

    if (dialog && typeof dialog.showModal === 'function') {
        const title = dialog.querySelector('[data-plugin-impact-title]');
        const message = dialog.querySelector('[data-plugin-impact-message]');
        const list = dialog.querySelector('[data-plugin-impact-list]');
        const cancel = dialog.querySelector('[data-plugin-impact-cancel]');
        const accept = dialog.querySelector('[data-plugin-impact-accept]');

        document.querySelectorAll('[data-plugin-disable-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === '1') return;
                const slides = parseSlides(form);
                if (slides.length === 0) return;

                event.preventDefault();
                pendingForm = form;
                const pluginName = form.dataset.pluginName || '';
                if (title) title.textContent = (strings.title || '').replace(':plugin', pluginName);
                if (message) message.textContent = confirmMessage(pluginName, slides.length);
                if (list) {
                    list.replaceChildren(...slides.map((slide) => {
                        const item = document.createElement('a');
                        item.className = 'plugin-impact-dialog__item';
                        item.href = `/admin/slides/${slide.id}/edit`;
                        item.target = '_blank';
                        const name = document.createElement('strong');
                        name.textContent = slide.name || `#${slide.id}`;
                        const meta = document.createElement('small');
                        const status = Number(slide.is_active || 0) === 1 ? strings.active : strings.inactive;
                        const channels = slide.channel_names || strings.noChannels || '';
                        meta.textContent = [status, channels].filter(Boolean).join(' · ');
                        item.append(name, meta);
                        return item;
                    }));
                }
                dialog.showModal();
            });
        });

        cancel?.addEventListener('click', () => {
            pendingForm = null;
            dialog.close();
        });
        accept?.addEventListener('click', () => {
            if (!pendingForm) return;
            const confirmInput = pendingForm.querySelector('[data-plugin-disable-confirm-input]');
            if (confirmInput) confirmInput.value = '1';
            pendingForm.dataset.confirmed = '1';
            pendingForm.submit();
        });
    } else {
        document.querySelectorAll('[data-plugin-disable-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === '1') return;
                const slides = parseSlides(form);
                if (slides.length === 0) return;
                if (!window.confirm(confirmMessage(form.dataset.pluginName || '', slides.length))) {
                    event.preventDefault();
                    return;
                }
                const confirmInput = form.querySelector('[data-plugin-disable-confirm-input]');
                if (confirmInput) confirmInput.value = '1';
                form.dataset.confirmed = '1';
            });
        });
    }
})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
