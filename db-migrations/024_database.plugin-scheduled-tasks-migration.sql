CREATE TABLE plugin_scheduled_tasks (
    plugin_name VARCHAR(100) NOT NULL,
    task_name VARCHAR(100) NOT NULL,
    interval_seconds INT UNSIGNED NOT NULL,
    retry_seconds INT UNSIGNED NOT NULL,
    is_registered TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('idle', 'running', 'success', 'failed') NOT NULL DEFAULT 'idle',
    last_started_at DATETIME NULL,
    last_finished_at DATETIME NULL,
    last_success_at DATETIME NULL,
    next_run_at DATETIME NOT NULL,
    last_duration_ms INT UNSIGNED NULL,
    last_error VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (plugin_name, task_name),
    KEY idx_plugin_scheduled_tasks_due (is_registered, next_run_at)
);
