ALTER TABLE display_heartbeats
    ADD COLUMN reported_state_signature CHAR(40) NULL AFTER current_channel_name,
    ADD COLUMN reported_playback_status VARCHAR(32) NULL AFTER reported_state_signature,
    ADD COLUMN playback_reported_at DATETIME NULL AFTER reported_playback_status,
    ADD COLUMN pending_state_signature CHAR(40) NULL AFTER playback_reported_at,
    ADD COLUMN pending_activation_at_ms BIGINT UNSIGNED NULL AFTER pending_state_signature;
