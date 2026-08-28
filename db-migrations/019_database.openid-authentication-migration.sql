ALTER TABLE users
    MODIFY password_hash VARCHAR(255) NULL,
    ADD COLUMN first_name VARCHAR(150) NULL AFTER display_name,
    ADD COLUMN last_name VARCHAR(150) NULL AFTER first_name,
    ADD COLUMN auth_provider ENUM('local', 'openid') NOT NULL DEFAULT 'local' AFTER password_changed_at,
    ADD COLUMN oidc_issuer VARCHAR(255) NULL AFTER auth_provider,
    ADD COLUMN oidc_subject VARCHAR(255) NULL AFTER oidc_issuer,
    ADD UNIQUE KEY uniq_users_oidc_identity (oidc_issuer, oidc_subject);
