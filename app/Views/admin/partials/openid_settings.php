<fieldset class="card settings-card">
    <legend><?= e(__('openid.heading')) ?></legend>
    <p class="text-body-secondary muted"><?= e(__('openid.intro')) ?></p>
    <div class="settings-card-grid">
        <label class="checkbox-row settings-card-toggle">
            <input type="checkbox" name="settings[openid_enabled]" value="1" <?= old_checked('openid_enabled', $settings['openid_enabled'] ?? '0', 'settings') ?>>
            <?= e(__('openid.enabled')) ?>
        </label>
        <?= field_error_html('openid_enabled', 'settings') ?>
        <label><?= e(__('openid.issuer_url')) ?>
            <input class="form-control" type="url" name="settings[openid_issuer_url]" value="<?= e((string)old('openid_issuer_url', $settings['openid_issuer_url'] ?? '', 'settings')) ?>" placeholder="https://keycloak.example.org/realms/example"<?= field_attrs('openid_issuer_url', 'settings') ?>>
            <?= field_error_html('openid_issuer_url', 'settings') ?>
        </label>
        <label><?= e(__('openid.client_id')) ?>
            <input class="form-control" type="text" name="settings[openid_client_id]" value="<?= e((string)old('openid_client_id', $settings['openid_client_id'] ?? '', 'settings')) ?>" autocomplete="off">
        </label>
        <label><?= e(__('openid.client_secret')) ?>
            <input class="form-control" type="password" name="settings[openid_client_secret]" value="" autocomplete="new-password" placeholder="<?= !empty($settings['openid_has_client_secret']) ? e(__('openid.secret_saved')) : '' ?>">
            <?= field_error_html('openid_client_secret', 'settings') ?>
        </label>
        <label class="checkbox-row">
            <input type="checkbox" name="settings[openid_clear_client_secret]" value="1">
            <?= e(__('openid.clear_secret')) ?>
        </label>
        <label><?= e(__('openid.callback_url')) ?>
            <input class="form-control" type="text" readonly value="<?= e(url('/admin/oidc/callback')) ?>">
        </label>
        <label><?= e(__('openid.scopes')) ?>
            <input class="form-control" type="text" name="settings[openid_scopes]" value="<?= e((string)old('openid_scopes', $settings['openid_scopes'] ?? 'openid profile', 'settings')) ?>">
        </label>
        <?php foreach ([
            'username_claim' => 'openid.username_claim',
            'name_claim' => 'openid.name_claim',
            'first_name_claim' => 'openid.first_name_claim',
            'last_name_claim' => 'openid.last_name_claim',
            'department_claim' => 'openid.department_claim',
            'title_claim' => 'openid.title_claim',
            'picture_claim' => 'openid.picture_claim',
            'groups_claim' => 'openid.groups_claim',
        ] as $field => $label): ?>
            <label><?= e(__($label)) ?>
                <input class="form-control" type="text" name="settings[openid_<?= e($field) ?>]" value="<?= e((string)old('openid_' . $field, $settings['openid_' . $field] ?? '', 'settings')) ?>"<?= field_attrs('openid_' . $field, 'settings') ?>>
                <?= field_error_html('openid_' . $field, 'settings') ?>
            </label>
        <?php endforeach; ?>
        <label><?= e(__('openid.admin_group')) ?>
            <input class="form-control" type="text" name="settings[openid_admin_group]" value="<?= e((string)old('openid_admin_group', $settings['openid_admin_group'] ?? '', 'settings')) ?>">
        </label>
        <label><?= e(__('openid.editor_group')) ?>
            <input class="form-control" type="text" name="settings[openid_editor_group]" value="<?= e((string)old('openid_editor_group', $settings['openid_editor_group'] ?? '', 'settings')) ?>">
        </label>
    </div>
    <p class="text-body-secondary small"><?= e(__('openid.group_help')) ?></p>
    <button class="btn btn-secondary" type="submit" formaction="<?= e(url('/admin/oidc/test')) ?>" formmethod="post"><?= e(__('openid.test')) ?></button>
</fieldset>
