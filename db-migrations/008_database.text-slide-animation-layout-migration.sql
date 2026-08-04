ALTER TABLE slides
    ADD COLUMN text_box_animation_duration_ms INT UNSIGNED NULL AFTER text_box_animation,
    ADD COLUMN text_box_animation_delay_ms INT UNSIGNED NULL AFTER text_box_animation_duration_ms,
    ADD COLUMN text_box_blur_enabled TINYINT(1) NULL AFTER text_box_animation_delay_ms,
    ADD COLUMN text_box_width_percent TINYINT UNSIGNED NULL AFTER text_box_blur_enabled,
    ADD COLUMN qr_position VARCHAR(40) NULL AFTER qr_background_color;
