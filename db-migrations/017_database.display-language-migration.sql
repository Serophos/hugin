ALTER TABLE displays
    ADD COLUMN display_language ENUM('system', 'en', 'de') NOT NULL DEFAULT 'system' AFTER timezone;
