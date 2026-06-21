</main>
<?php if (!empty($adminShellActive)): ?>
    </div>
</div>
<?php endif; ?>
<?php
$dialogConfig = [
    'labels' => [
        'ok' => __('dialog.buttons.ok', [], 'OK'),
        'yes' => __('dialog.buttons.yes', [], __('common.yes')),
        'no' => __('dialog.buttons.no', [], __('common.no')),
        'cancel' => __('dialog.buttons.cancel', [], __('common.cancel')),
        'delete' => __('dialog.buttons.delete', [], __('common.delete')),
        'close' => __('dialog.buttons.close', [], __('common.close')),
    ],
    'titles' => [
        'default' => __('dialog.titles.default', [], 'Message'),
        'information' => __('dialog.titles.information', [], 'Information'),
        'question' => __('dialog.titles.question', [], 'Question'),
        'exclamation' => __('dialog.titles.exclamation', [], 'Attention'),
        'warning' => __('dialog.titles.warning', [], 'Warning'),
        'error' => __('dialog.titles.error', [], 'Error'),
        'trash' => __('dialog.titles.trash', [], 'Delete confirmation'),
    ],
    'icons' => [
        'information' => asset_url('/assets/icons/admin/dialog-information.svg'),
        'question' => asset_url('/assets/icons/admin/dialog-question.svg'),
        'exclamation' => asset_url('/assets/icons/admin/dialog-exclamation.svg'),
        'warning' => asset_url('/assets/icons/admin/dialog-warning.svg'),
        'error' => asset_url('/assets/icons/admin/dialog-error.svg'),
        'trash' => asset_url('/assets/icons/admin/dialog-trash.svg'),
    ],
    'buttonIcons' => [
        'ok' => asset_url('/assets/icons/admin/check.svg'),
        'yes' => asset_url('/assets/icons/admin/check.svg'),
        'no' => asset_url('/assets/icons/admin/cancel.svg'),
        'cancel' => asset_url('/assets/icons/admin/cancel.svg'),
        'delete' => asset_url('/assets/icons/admin/delete.svg'),
        'close' => asset_url('/assets/icons/admin/cancel.svg'),
    ],
];
?>
<dialog class="admin-dialog hugin-dialog" data-hugin-dialog aria-modal="true" aria-labelledby="hugin-dialog-title" aria-describedby="hugin-dialog-message">
    <form method="dialog" class="admin-dialog__panel hugin-dialog__panel" data-hugin-dialog-panel>
        <header class="hugin-dialog__titlebar">
            <h2 id="hugin-dialog-title" data-hugin-dialog-title></h2>
            <button type="button" class="hugin-dialog__close" data-hugin-dialog-close aria-label="<?= e(__('dialog.buttons.close', [], __('common.close'))) ?>" title="<?= e(__('dialog.buttons.close', [], __('common.close'))) ?>">
                <?= admin_icon('cancel') ?>
            </button>
        </header>
        <div class="hugin-dialog__body">
            <div class="hugin-dialog__icon" data-hugin-dialog-icon hidden aria-hidden="true">
                <span class="hugin-dialog__icon-symbol" data-hugin-dialog-icon-symbol></span>
            </div>
            <div class="hugin-dialog__main">
                <p id="hugin-dialog-message" class="hugin-dialog__message" data-hugin-dialog-message></p>
                <div class="hugin-dialog__content" data-hugin-dialog-content></div>
            </div>
        </div>
        <div class="form-actions hugin-dialog__actions" data-hugin-dialog-actions></div>
    </form>
</dialog>
<script type="application/json" data-hugin-dialog-config><?= json_encode($dialogConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset_url('/assets/js/sortable.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/display-groups.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/admin-dialog.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/admin-table.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/admin-layout.js')) ?>"></script>
</body>
</html>
