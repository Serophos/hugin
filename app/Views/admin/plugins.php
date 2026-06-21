<?php
$title = __('plugins.title');
$pluginDialogStrings = [
    'title' => __('plugins.disable_impact_title'),
    'message' => __('plugins.disable_impact_message'),
    'noChannels' => __('slide.no_channels'),
    'active' => __('common.active'),
    'inactive' => __('common.inactive'),
    'accept' => __('plugins.disable_and_inactivate'),
    'affectedSlides' => __('plugins.affected_slides'),
    'slideEditBase' => url('/admin/slides'),
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

<script>
(() => {
    const strings = <?= json_encode($pluginDialogStrings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

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

    function markConfirmed(form) {
        const confirmInput = form.querySelector('[data-plugin-disable-confirm-input]');
        if (confirmInput) confirmInput.value = '1';
        form.dataset.confirmed = '1';
    }

    function submitConfirmed(form) {
        markConfirmed(form);
        form.submit();
    }

    function createImpactContent(slides) {
        const wrapper = document.createElement('div');
        wrapper.className = 'plugin-impact-dialog';

        const heading = document.createElement('h3');
        heading.textContent = strings.affectedSlides || '';
        wrapper.append(heading);

        const list = document.createElement('div');
        list.className = 'plugin-impact-dialog__list';
        list.append(...slides.map((slide) => {
            const item = document.createElement('a');
            item.className = 'plugin-impact-dialog__item';
            item.href = `${strings.slideEditBase || '/admin/slides'}/${slide.id}/edit`;
            item.target = '_blank';
            item.rel = 'noopener noreferrer';

            const name = document.createElement('strong');
            name.textContent = slide.name || `#${slide.id}`;

            const meta = document.createElement('small');
            const status = Number(slide.is_active || 0) === 1 ? strings.active : strings.inactive;
            const channels = slide.channel_names || strings.noChannels || '';
            meta.textContent = [status, channels].filter(Boolean).join(' · ');

            item.append(name, meta);
            return item;
        }));
        wrapper.append(list);
        return wrapper;
    }

    function confirmPluginDisable(form, slides) {
        if (typeof window.HuginDialog?.open !== 'function') {
            return Promise.resolve(false);
        }
        const pluginName = form.dataset.pluginName || '';
        return window.HuginDialog.open({
            title: (strings.title || '').replace(':plugin', pluginName),
            message: confirmMessage(pluginName, slides.length),
            icon: 'warning',
            content: createImpactContent(slides),
            buttons: ['cancel', { preset: 'delete', label: strings.accept || '' }],
            defaultButton: 'cancel',
            cancelButton: 'cancel',
        }).then((result) => result === 'delete');
    }

    document.querySelectorAll('[data-plugin-disable-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === '1') return;
            const slides = parseSlides(form);
            if (slides.length === 0) return;

            event.preventDefault();
            confirmPluginDisable(form, slides).then((accepted) => {
                if (!accepted) return;
                submitConfirmed(form);
            });
        });
    });
})();
</script>
<?php require __DIR__ . '/../layouts/admin_footer.php'; ?>
