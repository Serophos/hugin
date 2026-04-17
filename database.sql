CREATE DATABASE IF NOT EXISTS info_display CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE info_display;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS slide_plugin_data;
DROP TABLE IF EXISTS plugins;
DROP TABLE IF EXISTS channel_slide_assignments;
DROP TABLE IF EXISTS display_channel_schedules;
DROP TABLE IF EXISTS display_channel_assignments;
DROP TABLE IF EXISTS slides;
DROP TABLE IF EXISTS channels;
DROP TABLE IF EXISTS display_heartbeats;
DROP TABLE IF EXISTS displays;
DROP TABLE IF EXISTS media_assets;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) NULL,
    role ENUM('admin', 'editor') NOT NULL DEFAULT 'editor',
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE displays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NULL,
    transition_effect ENUM('fade', 'slide-left', 'slide-right', 'slide-up', 'slide-down', 'zoom', 'flip', 'blur', 'none') NOT NULL DEFAULT 'fade',
    slide_duration_seconds INT UNSIGNED NOT NULL DEFAULT 8,
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
    orientation ENUM('landscape', 'vertical') NOT NULL DEFAULT 'landscape',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    transition_effect ENUM('inherit', 'fade', 'slide-left', 'slide-right', 'slide-up', 'slide-down', 'zoom', 'flip', 'blur', 'none') NOT NULL DEFAULT 'inherit',
    slide_duration_seconds INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE display_heartbeats (
    display_id INT UNSIGNED NOT NULL PRIMARY KEY,
    current_channel_id INT UNSIGNED NULL,
    current_channel_name VARCHAR(150) NULL,
    last_seen_ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    browser_name VARCHAR(80) NULL,
    browser_version VARCHAR(80) NULL,
    os_name VARCHAR(80) NULL,
    os_version VARCHAR(80) NULL,
    platform VARCHAR(120) NULL,
    language VARCHAR(32) NULL,
    client_timezone VARCHAR(64) NULL,
    screen_width INT NULL,
    screen_height INT NULL,
    avail_screen_width INT NULL,
    avail_screen_height INT NULL,
    viewport_width INT NULL,
    viewport_height INT NULL,
    device_pixel_ratio DECIMAL(6,2) NULL,
    color_depth INT NULL,
    max_touch_points INT NULL,
    hardware_concurrency INT NULL,
    device_memory_gb DECIMAL(6,2) NULL,
    screen_orientation VARCHAR(32) NULL,
    is_online TINYINT(1) NULL,
    cookies_enabled TINYINT(1) NULL,
    client_payload_json LONGTEXT NULL,
    last_seen_at DATETIME NOT NULL,
    CONSTRAINT fk_heartbeat_display FOREIGN KEY (display_id) REFERENCES displays(id) ON DELETE CASCADE,
    CONSTRAINT fk_heartbeat_channel FOREIGN KEY (current_channel_id) REFERENCES channels(id) ON DELETE SET NULL
);

CREATE TABLE display_channel_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_id INT UNSIGNED NOT NULL,
    channel_id INT UNSIGNED NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_display_channel (display_id, channel_id),
    CONSTRAINT fk_dca_display FOREIGN KEY (display_id) REFERENCES displays(id) ON DELETE CASCADE,
    CONSTRAINT fk_dca_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
);

CREATE TABLE display_channel_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_channel_assignment_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday ... 6=Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dcs_assignment FOREIGN KEY (display_channel_assignment_id) REFERENCES display_channel_assignments(id) ON DELETE CASCADE
);

CREATE TABLE media_assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    media_kind ENUM('image', 'video') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE slides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slide_type VARCHAR(100) NOT NULL,
    source_mode ENUM('external', 'media') NOT NULL DEFAULT 'external',
    source_url TEXT NULL,
    media_asset_id INT UNSIGNED NULL,
    duration_seconds INT UNSIGNED NULL,
    title_position ENUM('hide', 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'center') NOT NULL DEFAULT 'bottom-left',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_slides_media FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE SET NULL
);

CREATE TABLE channel_slide_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    channel_id INT UNSIGNED NOT NULL,
    slide_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_channel_slide (channel_id, slide_id),
    CONSTRAINT fk_csa_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_csa_slide FOREIGN KEY (slide_id) REFERENCES slides(id) ON DELETE CASCADE
);

CREATE TABLE plugins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plugin_name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) NOT NULL,
    version VARCHAR(50) NOT NULL,
    description TEXT NULL,
    slide_type VARCHAR(100) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    manifest_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE slide_plugin_data (
    slide_id INT UNSIGNED NOT NULL,
    plugin_name VARCHAR(100) NOT NULL,
    settings_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (slide_id, plugin_name),
    CONSTRAINT fk_spd_slide FOREIGN KEY (slide_id) REFERENCES slides(id) ON DELETE CASCADE
);

INSERT INTO users (username, display_name, role, password_hash, is_active) VALUES
('admin', 'Administrator', 'admin', '$2y$12$IoewkYyycR./iDEV.rq5/eZU61ixU5l222mohQBYtC.uLg40bXmHK', 1),
('editor', 'Content Editor', 'editor', '$2y$12$IoewkYyycR./iDEV.rq5/eZU61ixU5l222mohQBYtC.uLg40bXmHK', 1);

INSERT INTO displays (name, slug, description, transition_effect, slide_duration_seconds, timezone, orientation, sort_order, is_active) VALUES
('Lobby Screen', 'lobby-screen', 'Main lobby information screen', 'fade', 8, 'Europe/Berlin', 'landscape', 1, 1),
('Cafeteria Screen', 'cafeteria-screen', 'Menu and news display', 'slide-left', 8, 'Europe/Berlin', 'landscape', 2, 1);

INSERT INTO channels (name, description, transition_effect, slide_duration_seconds, is_active) VALUES
('Default Channel', 'Fallback content', 'inherit', 8, 1),
('Morning News', 'Shown in the morning', 'slide-left', 6, 1),
('Menu Loop', 'General cafeteria content', 'zoom', 8, 1);

INSERT INTO display_channel_assignments (display_id, channel_id, is_default, sort_order) VALUES
(1, 1, 1, 1),
(1, 2, 0, 2),
(2, 1, 1, 1),
(2, 3, 0, 2);

INSERT INTO display_channel_schedules (display_channel_assignment_id, weekday, start_time, end_time, is_enabled) VALUES
(2, 1, '06:00:00', '11:59:59', 1),
(2, 2, '06:00:00', '11:59:59', 1),
(2, 3, '06:00:00', '11:59:59', 1),
(2, 4, '06:00:00', '11:59:59', 1),
(2, 5, '06:00:00', '11:59:59', 1),
(4, 1, '10:00:00', '14:00:00', 1),
(4, 2, '10:00:00', '14:00:00', 1),
(4, 3, '10:00:00', '14:00:00', 1),
(4, 4, '10:00:00', '14:00:00', 1),
(4, 5, '10:00:00', '14:00:00', 1);

INSERT INTO slides (name, slide_type, source_mode, source_url, media_asset_id, duration_seconds, title_position, is_active) VALUES
('Welcome Image', 'image', 'external', 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1600&q=80', NULL, 8, 'bottom-left', 1),
('Campus Website', 'website', 'external', 'https://www.example.com', NULL, 10, 'bottom-left', 1),
('Morning Video', 'video', 'external', 'https://www.w3schools.com/html/mov_bbb.mp4', NULL, 12, 'bottom-left', 1),
('Menu Website', 'website', 'external', 'https://www.example.com/menu', NULL, 10, 'bottom-left', 1),
('Client Metadata', 'screen-meta', 'external', '', NULL, 10, 'top-left', 1);

INSERT INTO channel_slide_assignments (channel_id, slide_id, sort_order) VALUES
(1, 1, 1),
(1, 2, 2),
(2, 3, 1),
(3, 4, 1),
(1, 5, 3);

INSERT INTO plugins (plugin_name, display_name, version, description, slide_type, is_enabled, manifest_json) VALUES
('screen-meta', 'Screen Metadata', '1.0.0', 'Renders the most recent heartbeat metadata collected from the display client.', 'screen-meta', 1, NULL);

INSERT INTO slide_plugin_data (slide_id, plugin_name, settings_json) VALUES
(5, 'screen-meta', '{"heading":"Display Client Information","show_browser":true,"show_os":true,"show_resolution":true,"show_viewport":true,"show_ip":true,"show_timezone":true,"note":"This example plugin slide is powered by the new plugin system."}');
