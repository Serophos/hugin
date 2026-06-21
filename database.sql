SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS slide_plugin_data;
DROP TABLE IF EXISTS slide_template_data;
DROP TABLE IF EXISTS slide_templates;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS plugin_global_settings;
DROP TABLE IF EXISTS plugins;
DROP TABLE IF EXISTS channel_slide_assignments;
DROP TABLE IF EXISTS channel_display_schedule_assignments;
DROP TABLE IF EXISTS schedule_rules;
DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS display_channel_schedules;
DROP TABLE IF EXISTS display_channel_assignments;
DROP TABLE IF EXISTS slides;
DROP TABLE IF EXISTS channels;
DROP TABLE IF EXISTS display_heartbeats;
DROP TABLE IF EXISTS display_group_memberships;
DROP TABLE IF EXISTS display_groups;
DROP TABLE IF EXISTS display_locations;
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
    password_changed_at TIMESTAMP NULL DEFAULT NULL,
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
    icon_file VARCHAR(120) NOT NULL DEFAULT 'display_16_9.png',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE display_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address TEXT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_display_location_name (name)
);

CREATE TABLE display_groups (
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

CREATE TABLE display_group_memberships (
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

CREATE TABLE schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('fulltime', 'weekly_time_slot') NOT NULL DEFAULT 'weekly_time_slot',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE schedule_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '1=Monday ... 7=Sunday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_rules_schedule FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
);

CREATE TABLE channel_display_schedule_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_id INT UNSIGNED NOT NULL,
    channel_id INT UNSIGNED NOT NULL,
    schedule_id INT UNSIGNED NOT NULL,
    priority INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_display_channel_schedule (display_id, channel_id, schedule_id),
    KEY idx_cdsa_display_priority (display_id, priority),
    CONSTRAINT fk_cdsa_display FOREIGN KEY (display_id) REFERENCES displays(id) ON DELETE CASCADE,
    CONSTRAINT fk_cdsa_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_cdsa_schedule FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE RESTRICT
);

CREATE TABLE media_assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    media_kind ENUM('image', 'video', 'font') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    preview_file_path VARCHAR(255) NULL,
    font_family_name VARCHAR(190) NULL,
    font_full_name VARCHAR(190) NULL,
    font_subfamily VARCHAR(120) NULL,
    font_weight SMALLINT UNSIGNED NULL,
    font_postscript_name VARCHAR(190) NULL,
    font_version VARCHAR(190) NULL,
    font_format VARCHAR(16) NULL,
    license_note TEXT NULL,
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
    background_media_asset_id INT UNSIGNED NULL,
    text_markup LONGTEXT NULL,
    background_color VARCHAR(20) NULL,
    text_color VARCHAR(40) NULL,
    text_box_background_color VARCHAR(40) NULL,
    text_box_layout VARCHAR(40) NULL,
    text_box_animation VARCHAR(40) NULL,
    text_box_animation_duration_ms INT UNSIGNED NULL,
    text_box_animation_delay_ms INT UNSIGNED NULL,
    text_box_blur_enabled TINYINT(1) NULL,
    text_box_width_percent TINYINT UNSIGNED NULL,
    text_box_radius_top_left_rem DECIMAL(4,2) UNSIGNED NULL,
    text_box_radius_top_right_rem DECIMAL(4,2) UNSIGNED NULL,
    text_box_radius_bottom_right_rem DECIMAL(4,2) UNSIGNED NULL,
    text_box_radius_bottom_left_rem DECIMAL(4,2) UNSIGNED NULL,
    qr_foreground_color VARCHAR(40) NULL,
    qr_background_color VARCHAR(40) NULL,
    qr_position VARCHAR(40) NULL,
    qr_size_percent TINYINT UNSIGNED NULL,
    qr_animation_enabled TINYINT(1) NULL,
    qr_radius_top_left_rem DECIMAL(4,2) UNSIGNED NULL,
    qr_radius_top_right_rem DECIMAL(4,2) UNSIGNED NULL,
    qr_radius_bottom_right_rem DECIMAL(4,2) UNSIGNED NULL,
    qr_radius_bottom_left_rem DECIMAL(4,2) UNSIGNED NULL,
    duration_seconds INT UNSIGNED NULL,
    title_position ENUM('hide', 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'center') NOT NULL DEFAULT 'bottom-left',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_slides_media FOREIGN KEY (media_asset_id) REFERENCES media_assets(id) ON DELETE SET NULL,
    CONSTRAINT fk_slides_background_media FOREIGN KEY (background_media_asset_id) REFERENCES media_assets(id) ON DELETE SET NULL
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

CREATE TABLE slide_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    landscape_spec_json LONGTEXT NOT NULL,
    portrait_spec_json LONGTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id INT UNSIGNED NULL,
    updated_by_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_slide_templates_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_slide_templates_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE slide_template_data (
    slide_id INT UNSIGNED PRIMARY KEY,
    template_id INT UNSIGNED NOT NULL,
    values_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_slide_template_data_slide FOREIGN KEY (slide_id) REFERENCES slides(id) ON DELETE CASCADE,
    CONSTRAINT fk_slide_template_data_template FOREIGN KEY (template_id) REFERENCES slide_templates(id) ON DELETE RESTRICT
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

CREATE TABLE plugin_global_settings (
    plugin_name VARCHAR(100) NOT NULL PRIMARY KEY,
    settings_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE app_settings (
    namespace VARCHAR(100) NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (namespace, setting_key)
);

INSERT INTO users (username, display_name, role, password_hash, is_active) VALUES
('admin', 'Administrator', 'admin', '$2y$12$IoewkYyycR./iDEV.rq5/eZU61ixU5l222mohQBYtC.uLg40bXmHK', 1),
('editor', 'Content Editor', 'editor', '$2y$12$IoewkYyycR./iDEV.rq5/eZU61ixU5l222mohQBYtC.uLg40bXmHK', 0);

INSERT INTO display_locations (name, address, description, sort_order) VALUES
('Milliways', 'Frogstar World B, Milliways Approach 42', 'The Restaurant at the End of the Universe; a safe, fictional demo location for Hugin displays.', 1);

INSERT INTO display_groups (location_id, name, description, sort_order, sync_enabled, sync_mode) VALUES
(1, 'Arrival Lounge Screens', 'Displays welcoming guests and showing general Hugin demo content.', 1, 0, 'independent'),
(1, 'Dining Deck Screens', 'Displays for scheduled messages, morning content, and visual announcements.', 2, 0, 'independent');

INSERT INTO displays (name, slug, description, transition_effect, slide_duration_seconds, timezone, orientation, icon_file, sort_order, is_active) VALUES
('Arrival Lounge Display', 'arrival-lounge-display', 'Demo display in the Milliways arrival lounge.', 'fade', 8, 'Europe/Berlin', 'landscape', 'display_16_9.png', 1, 1),
('Dining Deck Display', 'dining-deck-display', 'Demo display on the Milliways dining deck.', 'slide-left', 8, 'Europe/Berlin', 'landscape', 'display_16_9.png', 2, 1);

INSERT INTO display_group_memberships (display_id, group_id, layout_x, layout_y, layout_width, layout_height, layout_rotation_degrees, bezel_top, bezel_right, bezel_bottom, bezel_left, sort_order) VALUES
(1, 1, 0, 0, NULL, NULL, 0, 0, 0, 0, 0, 1),
(2, 2, 0, 0, NULL, NULL, 0, 0, 0, 0, 0, 1);

INSERT INTO channels (name, description, transition_effect, slide_duration_seconds, is_active) VALUES
('Hugin Demo Playlist', 'Full-time demo loop for Hugin | Open Source Digital Signage.', 'inherit', 8, 1),
('Hugin Morning Showcase', 'Morning-only demo playlist showing additional slide settings and presentation styles.', 'slide-left', 7, 1);

INSERT INTO schedules (name, type, is_system, is_active) VALUES
('Fulltime', 'fulltime', 1, 1),
('Weekday mornings', 'weekly_time_slot', 0, 1),
('Weekday lunch', 'weekly_time_slot', 0, 1);

INSERT INTO schedule_rules (schedule_id, weekday, start_time, end_time) VALUES
(2, 1, '06:00:00', '12:00:00'),
(2, 2, '06:00:00', '12:00:00'),
(2, 3, '06:00:00', '12:00:00'),
(2, 4, '06:00:00', '12:00:00'),
(2, 5, '06:00:00', '12:00:00'),
(3, 1, '10:00:00', '14:00:00'),
(3, 2, '10:00:00', '14:00:00'),
(3, 3, '10:00:00', '14:00:00'),
(3, 4, '10:00:00', '14:00:00'),
(3, 5, '10:00:00', '14:00:00');

INSERT INTO channel_display_schedule_assignments (display_id, channel_id, schedule_id, priority, is_active) VALUES
(1, 1, 1, 10, 1),
(2, 1, 1, 10, 1),
(1, 2, 2, 1, 1);

INSERT INTO media_assets (name, original_name, mime_type, file_size, media_kind, file_path, uploaded_by_user_id) VALUES
('Hugin Teaser Video', 'hugin_open_source_digital_signage.mp4', 'video/mp4', 17750869, 'video', '/demo/hugin_open_source_digital_signage.mp4', 1),
('Hugin Background Image', 'hugin_open_source_digital_signage.png', 'image/png', 1711082, 'image', '/demo/hugin_open_source_digital_signage.png', 1),
('Hugin Demo Image 1', 'hugin_demo_image_1.jpeg', 'image/jpeg', 1534871, 'image', '/demo/hugin_demo_image_1.jpeg', 1),
('Hugin Demo Image 2', 'hugin_demo_image_2.jpeg', 'image/jpeg', 1431159, 'image', '/demo/hugin_demo_image_2.jpeg', 1);

INSERT INTO slides (
    name, slide_type, source_mode, source_url, media_asset_id, background_media_asset_id, text_markup,
    background_color, text_color, text_box_background_color, text_box_layout, text_box_animation,
    text_box_animation_duration_ms, text_box_animation_delay_ms, text_box_blur_enabled, text_box_width_percent,
    text_box_radius_top_left_rem, text_box_radius_top_right_rem, text_box_radius_bottom_right_rem, text_box_radius_bottom_left_rem,
    qr_foreground_color, qr_background_color, qr_position, qr_size_percent,
    qr_animation_enabled, qr_radius_top_left_rem, qr_radius_top_right_rem, qr_radius_bottom_right_rem, qr_radius_bottom_left_rem,
    duration_seconds, title_position, is_active
) VALUES
('Hugin Teaser Video', 'video', 'media', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 15, 'hide', 1),
('Hugin Brand Image', 'image', 'media', NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10, 'hide', 1),
('Flexible Text Overlay', 'text', 'external', 'https://github.com/serophos/hugin', NULL, 3, '# Hugin | Open Source Digital Signage\n\nCreate polished information screens with rich text, media backgrounds, translucent cards, and QR codes. This slide uses a demo media asset as its background.', '#0f172a', 'rgba(248,250,252,1)', 'rgba(15,23,42,0.72)', 'bottom-left', 'fade-up', 600, 100, 1, 76, NULL, NULL, NULL, NULL, 'rgba(255,255,255,1)', 'rgba(31,41,55,0)', 'bottom-right', 14, 0, NULL, NULL, NULL, NULL, 12, 'hide', 1),
('Visual Announcements', 'text', 'external', NULL, NULL, 4, '# Readable messages, fast updates\n\nUse Hugin to publish announcements, wayfinding notes, menus, and service updates. Layout, card width, animation timing, background media, and colors can be tuned for each slide.', '#111827', 'rgba(255,255,255,1)', 'rgba(17,24,39,0.64)', 'bottom-right', 'fade-up', 700, 1500, 1, 33, NULL, NULL, NULL, NULL, 'rgba(17,24,39,1)', 'rgba(255,255,255,1)', 'top-right', 12, 0, NULL, NULL, NULL, NULL, 12, 'hide', 1),
('Morning Website Slide', 'website', 'external', 'https://en.wikipedia.org/wiki/Digital_signage', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 14, 'top-left', 1),
('Morning Media Message', 'text', 'external', 'https://github.com/', NULL, 2, '# Scheduled playlists\n\nThis morning playlist is active only on weekday mornings and only on one display. Hugin can combine schedules, priorities, and display assignments for targeted communication.', '#06202a', 'rgba(240,253,250,1)', 'rgba(6,32,42,0.70)', 'top-left', 'zoom', 650, 0, 1, 72, NULL, NULL, NULL, NULL, 'rgba(6,32,42,1)', 'rgba(240,253,250,1)', 'bottom-right', 13, 0, NULL, NULL, NULL, NULL, 10, 'hide', 1),
('External Video Example', 'video', 'external', 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12, 'bottom-right', 1),
('Compact Status Card', 'text', 'external', NULL, NULL, NULL, '# Simple by default\n\nRun Hugin on standard web clients, assign playlists to screens, and keep demo content professional, safe, and easy to replace.', '#1f2937', 'rgba(249,250,251,1)', 'rgba(31,41,55,0.78)', 'center', 'none', 600, 0, 0, 55, NULL, NULL, NULL, NULL, 'rgba(31,41,55,1)', 'rgba(249,250,251,1)', 'bottom-left', 10, 0, NULL, NULL, NULL, NULL, 9, 'hide', 1);

INSERT INTO channel_slide_assignments (channel_id, slide_id, sort_order) VALUES
(1, 1, 1),
(1, 2, 2),
(1, 3, 3),
(1, 4, 4),
(2, 5, 1),
(2, 6, 2),
(2, 7, 3),
(2, 8, 4);
