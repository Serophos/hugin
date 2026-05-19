ALTER TABLE slides
    ADD COLUMN text_box_radius_top_left_rem DECIMAL(4,2) UNSIGNED NULL AFTER text_box_width_percent,
    ADD COLUMN text_box_radius_top_right_rem DECIMAL(4,2) UNSIGNED NULL AFTER text_box_radius_top_left_rem,
    ADD COLUMN text_box_radius_bottom_right_rem DECIMAL(4,2) UNSIGNED NULL AFTER text_box_radius_top_right_rem,
    ADD COLUMN text_box_radius_bottom_left_rem DECIMAL(4,2) UNSIGNED NULL AFTER text_box_radius_bottom_right_rem,
    ADD COLUMN qr_animation_enabled TINYINT(1) NULL AFTER qr_size_percent,
    ADD COLUMN qr_radius_top_left_rem DECIMAL(4,2) UNSIGNED NULL AFTER qr_animation_enabled,
    ADD COLUMN qr_radius_top_right_rem DECIMAL(4,2) UNSIGNED NULL AFTER qr_radius_top_left_rem,
    ADD COLUMN qr_radius_bottom_right_rem DECIMAL(4,2) UNSIGNED NULL AFTER qr_radius_top_right_rem,
    ADD COLUMN qr_radius_bottom_left_rem DECIMAL(4,2) UNSIGNED NULL AFTER qr_radius_bottom_right_rem;
