ALTER TABLE slides
    ADD COLUMN background_media_asset_id INT UNSIGNED NULL AFTER media_asset_id,
    ADD COLUMN text_markup LONGTEXT NULL AFTER background_media_asset_id,
    ADD COLUMN background_color VARCHAR(20) NULL AFTER text_markup,
    ADD CONSTRAINT fk_slides_background_media FOREIGN KEY (background_media_asset_id) REFERENCES media_assets(id) ON DELETE SET NULL;
