CREATE TABLE IF NOT EXISTS display_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_display_location_name (name)
);

CREATE TABLE IF NOT EXISTS display_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    sync_enabled TINYINT(1) NOT NULL DEFAULT 0,
    sync_mode VARCHAR(50) NOT NULL DEFAULT 'independent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_display_group_location_name (location_id, name),
    KEY idx_display_groups_location_sort (location_id, sort_order),
    CONSTRAINT fk_display_group_location FOREIGN KEY (location_id) REFERENCES display_locations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS display_group_memberships (
    display_id INT UNSIGNED NOT NULL PRIMARY KEY,
    group_id INT UNSIGNED NULL,
    layout_x INT NOT NULL DEFAULT 0,
    layout_y INT NOT NULL DEFAULT 0,
    layout_width INT UNSIGNED NULL,
    layout_height INT UNSIGNED NULL,
    layout_rotation_degrees SMALLINT NOT NULL DEFAULT 0,
    bezel_top INT UNSIGNED NOT NULL DEFAULT 0,
    bezel_right INT UNSIGNED NOT NULL DEFAULT 0,
    bezel_bottom INT UNSIGNED NOT NULL DEFAULT 0,
    bezel_left INT UNSIGNED NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_dgm_group_sort (group_id, sort_order),
    CONSTRAINT fk_dgm_display FOREIGN KEY (display_id) REFERENCES displays(id) ON DELETE CASCADE,
    CONSTRAINT fk_dgm_group FOREIGN KEY (group_id) REFERENCES display_groups(id) ON DELETE SET NULL
);
