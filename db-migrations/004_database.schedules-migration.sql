SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('fulltime', 'weekly_time_slot') NOT NULL DEFAULT 'weekly_time_slot',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS schedule_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '1=Monday ... 7=Sunday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_rules_schedule FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS channel_display_schedule_assignments (
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

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO schedules (name, type, is_system, is_active)
SELECT 'Fulltime', 'fulltime', 1, 1
WHERE NOT EXISTS (
    SELECT 1 FROM schedules WHERE type = 'fulltime' AND is_system = 1
);

INSERT INTO schedules (name, type, is_system, is_active)
SELECT CONCAT('Migrated: ', c.name, ' / ', d.name, ' #', dca.id), 'weekly_time_slot', 0, 1
FROM display_channel_assignments dca
INNER JOIN channels c ON c.id = dca.channel_id
INNER JOIN displays d ON d.id = dca.display_id
WHERE dca.is_default = 0
  AND EXISTS (
      SELECT 1
      FROM display_channel_schedules dcs
      WHERE dcs.display_channel_assignment_id = dca.id
        AND dcs.is_enabled = 1
        AND dcs.start_time < dcs.end_time
  )
  AND NOT EXISTS (
      SELECT 1
      FROM schedules s
      WHERE s.name = CONCAT('Migrated: ', c.name, ' / ', d.name, ' #', dca.id)
  );

INSERT INTO schedule_rules (schedule_id, weekday, start_time, end_time)
SELECT s.id,
       CASE WHEN dcs.weekday = 0 THEN 7 ELSE dcs.weekday END,
       dcs.start_time,
       dcs.end_time
FROM display_channel_assignments dca
INNER JOIN channels c ON c.id = dca.channel_id
INNER JOIN displays d ON d.id = dca.display_id
INNER JOIN schedules s ON s.name = CONCAT('Migrated: ', c.name, ' / ', d.name, ' #', dca.id)
INNER JOIN display_channel_schedules dcs ON dcs.display_channel_assignment_id = dca.id
WHERE dcs.is_enabled = 1
  AND dcs.start_time < dcs.end_time
  AND NOT EXISTS (
      SELECT 1
      FROM schedule_rules sr
      WHERE sr.schedule_id = s.id
        AND sr.weekday = CASE WHEN dcs.weekday = 0 THEN 7 ELSE dcs.weekday END
        AND sr.start_time = dcs.start_time
        AND sr.end_time = dcs.end_time
  );

INSERT IGNORE INTO channel_display_schedule_assignments (display_id, channel_id, schedule_id, priority, is_active, created_at)
SELECT dca.display_id, dca.channel_id, s.id, dca.sort_order, 1, dca.created_at
FROM display_channel_assignments dca
INNER JOIN schedules s ON s.type = 'fulltime' AND s.is_system = 1
WHERE dca.is_default = 1;

INSERT IGNORE INTO channel_display_schedule_assignments (display_id, channel_id, schedule_id, priority, is_active, created_at)
SELECT dca.display_id, dca.channel_id, s.id, dca.sort_order, 1, dca.created_at
FROM display_channel_assignments dca
INNER JOIN channels c ON c.id = dca.channel_id
INNER JOIN displays d ON d.id = dca.display_id
INNER JOIN schedules s ON s.name = CONCAT('Migrated: ', c.name, ' / ', d.name, ' #', dca.id)
WHERE dca.is_default = 0;

DROP TABLE IF EXISTS display_channel_schedules;
DROP TABLE IF EXISTS display_channel_assignments;
