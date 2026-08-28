ALTER TABLE media_assets
    MODIFY media_kind ENUM('image', 'video', 'font') NOT NULL,
    ADD COLUMN font_family_name VARCHAR(190) NULL AFTER preview_file_path,
    ADD COLUMN font_full_name VARCHAR(190) NULL AFTER font_family_name,
    ADD COLUMN font_subfamily VARCHAR(120) NULL AFTER font_full_name,
    ADD COLUMN font_weight SMALLINT UNSIGNED NULL AFTER font_subfamily,
    ADD COLUMN font_postscript_name VARCHAR(190) NULL AFTER font_weight,
    ADD COLUMN font_version VARCHAR(190) NULL AFTER font_postscript_name,
    ADD COLUMN font_format VARCHAR(16) NULL AFTER font_version,
    ADD COLUMN license_note TEXT NULL AFTER font_format;
