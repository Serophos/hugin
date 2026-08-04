CREATE TABLE IF NOT EXISTS display_cache_readiness (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_id INT UNSIGNED NOT NULL,
    display_group_id INT UNSIGNED NULL,
    state_signature CHAR(40) NOT NULL,
    manifest_signature CHAR(40) NOT NULL DEFAULT '',
    cache_status VARCHAR(20) NOT NULL DEFAULT 'ready',
    reason VARCHAR(50) NOT NULL DEFAULT 'startup',
    total_assets INT UNSIGNED NOT NULL DEFAULT 0,
    cached_assets INT UNSIGNED NOT NULL DEFAULT 0,
    skipped_assets INT UNSIGNED NOT NULL DEFAULT 0,
    bytes_reserved BIGINT UNSIGNED NOT NULL DEFAULT 0,
    client_payload_json LONGTEXT NULL,
    ready_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_cache_readiness_display_state (display_id, state_signature),
    KEY idx_cache_readiness_group_state (display_group_id, state_signature),
    CONSTRAINT fk_cache_readiness_display FOREIGN KEY (display_id) REFERENCES displays(id) ON DELETE CASCADE,
    CONSTRAINT fk_cache_readiness_group FOREIGN KEY (display_group_id) REFERENCES display_groups(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS display_sync_releases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_group_id INT UNSIGNED NOT NULL,
    generation_hash CHAR(40) NOT NULL,
    participant_count INT UNSIGNED NOT NULL DEFAULT 0,
    ready_count INT UNSIGNED NOT NULL DEFAULT 0,
    start_at DATETIME NULL,
    released_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sync_release_generation (display_group_id, generation_hash),
    KEY idx_sync_release_group_created (display_group_id, created_at),
    CONSTRAINT fk_sync_release_group FOREIGN KEY (display_group_id) REFERENCES display_groups(id) ON DELETE CASCADE
);
