ALTER TABLE displays
    ADD COLUMN vnc_username VARCHAR(150) NULL AFTER display_language,
    ADD COLUMN vnc_password VARCHAR(255) NULL AFTER vnc_username;
