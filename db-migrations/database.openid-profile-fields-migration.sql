ALTER TABLE users
    ADD COLUMN department VARCHAR(150) NULL AFTER last_name,
    ADD COLUMN title VARCHAR(150) NULL AFTER department,
    ADD COLUMN picture_url VARCHAR(2048) NULL AFTER title;
