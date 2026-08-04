ALTER TABLE display_groups
    ADD COLUMN primary_display_id INT UNSIGNED NULL AFTER description,
    ADD KEY idx_display_groups_primary_display (primary_display_id),
    ADD CONSTRAINT fk_display_group_primary_display FOREIGN KEY (primary_display_id) REFERENCES displays(id) ON DELETE SET NULL;
