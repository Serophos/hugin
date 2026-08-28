-- QR URLs for text slides reuse slides.source_url.
ALTER TABLE slides
    ADD COLUMN text_color VARCHAR(40) NULL AFTER background_color,
    ADD COLUMN text_box_background_color VARCHAR(40) NULL AFTER text_color,
    ADD COLUMN text_box_layout VARCHAR(40) NULL AFTER text_box_background_color,
    ADD COLUMN text_box_animation VARCHAR(40) NULL AFTER text_box_layout,
    ADD COLUMN qr_foreground_color VARCHAR(40) NULL AFTER text_box_animation,
    ADD COLUMN qr_background_color VARCHAR(40) NULL AFTER qr_foreground_color;
