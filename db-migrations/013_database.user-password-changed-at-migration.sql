ALTER TABLE users
    ADD COLUMN password_changed_at TIMESTAMP NULL DEFAULT NULL AFTER password_hash;

UPDATE users
SET password_changed_at = updated_at,
    updated_at = updated_at
WHERE updated_at > created_at;
