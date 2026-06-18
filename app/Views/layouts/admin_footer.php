</main>
<?php if (!empty($adminShellActive)): ?>
    </div>
</div>
<?php endif; ?>
<dialog class="admin-dialog" data-confirm-dialog aria-labelledby="admin-confirm-title" aria-describedby="admin-confirm-message">
    <form method="dialog" class="admin-dialog__panel">
        <h2 id="admin-confirm-title" data-confirm-dialog-title></h2>
        <p id="admin-confirm-message" class="muted" data-confirm-dialog-message></p>
        <div class="form-actions">
            <button type="button" class="button button--normal" data-confirm-cancel><?= admin_icon('cancel') ?><span><?= e(__('common.no')) ?></span></button>
            <button type="button" class="button button--danger" data-confirm-accept><?= admin_icon('delete') ?><span data-default-label="<?= e(__('common.yes')) ?>"><?= e(__('common.yes')) ?></span></button>
        </div>
    </form>
</dialog>
<script src="<?= e(asset_url('/assets/js/sortable.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/display-groups.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/admin-confirm.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/admin-table.js')) ?>"></script>
<script src="<?= e(asset_url('/assets/js/admin-layout.js')) ?>"></script>
</body>
</html>
