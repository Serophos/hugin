<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\PluginManager;
use App\Core\ScheduledTaskProviderInterface;
use RuntimeException;
use Throwable;

final class PluginTaskScheduler
{
    private const LOCK = 'hugin:plugin-scheduled-tasks';
    private const MIN_INTERVAL_SECONDS = 60;
    private const DEFAULT_MAX_RETRY_SECONDS = 300;

    public function __construct(private readonly Database $db, private readonly PluginManager $plugins)
    {
    }

    /** @param callable(string):void $output @return array{locked:bool,executed:int,failed:int,definition_errors:int} */
    public function run(callable $output): array
    {
        if (!$this->acquireLock()) {
            $output('Another scheduled-task runner is active; nothing to do.');
            return ['locked' => false, 'executed' => 0, 'failed' => 0, 'definition_errors' => 0];
        }

        try {
            $definitionErrors = $this->synchronize($output);
            $tasks = $this->db->all(
                "SELECT * FROM plugin_scheduled_tasks
                 WHERE is_registered = 1 AND next_run_at <= NOW()
                 ORDER BY next_run_at ASC, plugin_name ASC, task_name ASC"
            );
            $enabled = $this->plugins->getEnabledPlugins();
            $executed = 0;
            $failed = 0;

            foreach ($tasks as $task) {
                $pluginName = (string)$task['plugin_name'];
                $taskName = (string)$task['task_name'];
                $plugin = $enabled[$pluginName] ?? null;
                if (!$plugin instanceof ScheduledTaskProviderInterface) {
                    continue;
                }

                $executed++;
                $started = microtime(true);
                $this->db->execute(
                    "UPDATE plugin_scheduled_tasks
                     SET status = 'running', last_started_at = NOW(), last_finished_at = NULL, last_duration_ms = NULL
                     WHERE plugin_name = ? AND task_name = ? AND is_registered = 1",
                    [$pluginName, $taskName]
                );
                $output('Running ' . $pluginName . ':' . $taskName);

                try {
                    $plugin->runScheduledTask($taskName, $this->plugins->buildApi());
                    $duration = max(0, (int)round((microtime(true) - $started) * 1000));
                    $interval = max(self::MIN_INTERVAL_SECONDS, (int)$task['interval_seconds']);
                    $this->db->execute(
                        "UPDATE plugin_scheduled_tasks
                         SET status = 'success', last_finished_at = NOW(), last_success_at = NOW(),
                             last_duration_ms = ?, last_error = NULL,
                             next_run_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
                         WHERE plugin_name = ? AND task_name = ?",
                        [$duration, $interval, $pluginName, $taskName]
                    );
                    $output('Completed ' . $pluginName . ':' . $taskName . ' in ' . $duration . ' ms');
                } catch (Throwable $error) {
                    $failed++;
                    $duration = max(0, (int)round((microtime(true) - $started) * 1000));
                    $retry = max(self::MIN_INTERVAL_SECONDS, (int)$task['retry_seconds']);
                    $summary = $this->errorSummary($error);
                    $this->db->execute(
                        "UPDATE plugin_scheduled_tasks
                         SET status = 'failed', last_finished_at = NOW(), last_duration_ms = ?, last_error = ?,
                             next_run_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
                         WHERE plugin_name = ? AND task_name = ?",
                        [$duration, $summary, $retry, $pluginName, $taskName]
                    );
                    $output('Failed ' . $pluginName . ':' . $taskName . ': ' . $summary);
                }
            }

            return [
                'locked' => true,
                'executed' => $executed,
                'failed' => $failed,
                'definition_errors' => $definitionErrors,
            ];
        } finally {
            $this->releaseLock();
        }
    }

    /** @return list<array<string,mixed>> */
    public function status(): array
    {
        return $this->db->all(
            "SELECT *,
                    CASE WHEN is_registered = 1 AND next_run_at < NOW() AND status <> 'running' THEN 1 ELSE 0 END AS is_overdue
             FROM plugin_scheduled_tasks
             ORDER BY is_registered DESC, plugin_name ASC, task_name ASC"
        );
    }

    /** @param callable(string):void $output */
    private function synchronize(callable $output): int
    {
        $this->db->execute('UPDATE plugin_scheduled_tasks SET is_registered = 0 WHERE is_registered <> 0');
        $errors = 0;

        foreach ($this->plugins->getEnabledPlugins() as $pluginName => $plugin) {
            if (!$plugin instanceof ScheduledTaskProviderInterface) {
                continue;
            }

            try {
                $definitions = $plugin->getScheduledTasks($this->plugins->buildApi());
                $normalized = $this->normalizeDefinitions($definitions);
            } catch (Throwable $error) {
                $errors++;
                $output('Invalid tasks for ' . $pluginName . ': ' . $this->errorSummary($error));
                continue;
            }

            foreach ($normalized as $definition) {
                $existing = $this->db->one(
                    'SELECT interval_seconds FROM plugin_scheduled_tasks WHERE plugin_name = ? AND task_name = ?',
                    [$pluginName, $definition['name']]
                );
                if ($existing === null) {
                    $this->db->execute(
                        "INSERT INTO plugin_scheduled_tasks
                         (plugin_name, task_name, interval_seconds, retry_seconds, is_registered, status, next_run_at)
                         VALUES (?, ?, ?, ?, 1, 'idle', NOW())",
                        [$pluginName, $definition['name'], $definition['interval_seconds'], $definition['retry_seconds']]
                    );
                    continue;
                }

                $this->db->execute(
                    "UPDATE plugin_scheduled_tasks
                     SET next_run_at = CASE
                             WHEN ? < interval_seconds THEN LEAST(next_run_at, DATE_ADD(NOW(), INTERVAL ? SECOND))
                             ELSE next_run_at
                         END, interval_seconds = ?, retry_seconds = ?, is_registered = 1
                     WHERE plugin_name = ? AND task_name = ?",
                    [
                        $definition['interval_seconds'],
                        $definition['interval_seconds'],
                        $definition['interval_seconds'],
                        $definition['retry_seconds'],
                        $pluginName,
                        $definition['name'],
                    ]
                );
            }
        }

        return $errors;
    }

    /** @param array<mixed> $definitions @return list<array{name:string,interval_seconds:int,retry_seconds:int}> */
    public function normalizeDefinitions(array $definitions): array
    {
        $normalized = [];
        $seen = [];
        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                throw new RuntimeException('Each task definition must be an array.');
            }
            $name = trim((string)($definition['name'] ?? ''));
            if (preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/D', $name) !== 1) {
                throw new RuntimeException('Task names must use lowercase letters, numbers, underscores, or hyphens.');
            }
            if (isset($seen[$name])) {
                throw new RuntimeException('Duplicate task name: ' . $name);
            }
            $interval = filter_var($definition['interval_seconds'] ?? null, FILTER_VALIDATE_INT);
            if ($interval === false || $interval < self::MIN_INTERVAL_SECONDS) {
                throw new RuntimeException('Task ' . $name . ' must have an interval of at least 60 seconds.');
            }
            $retry = array_key_exists('retry_seconds', $definition)
                ? filter_var($definition['retry_seconds'], FILTER_VALIDATE_INT)
                : min($interval, self::DEFAULT_MAX_RETRY_SECONDS);
            if ($retry === false || $retry < self::MIN_INTERVAL_SECONDS) {
                throw new RuntimeException('Task ' . $name . ' must have a retry interval of at least 60 seconds.');
            }
            $seen[$name] = true;
            $normalized[] = ['name' => $name, 'interval_seconds' => $interval, 'retry_seconds' => $retry];
        }
        return $normalized;
    }

    private function acquireLock(): bool
    {
        $statement = $this->db->pdo()->prepare('SELECT GET_LOCK(?, 0)');
        $statement->execute([self::LOCK]);
        return (int)$statement->fetchColumn() === 1;
    }

    private function releaseLock(): void
    {
        try {
            $statement = $this->db->pdo()->prepare('SELECT RELEASE_LOCK(?)');
            $statement->execute([self::LOCK]);
        } catch (Throwable) {
        }
    }

    private function errorSummary(Throwable $error): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $error->getMessage()) ?? 'Scheduled task failed.');
        if ($message === '') {
            $message = 'Scheduled task failed.';
        }
        return function_exists('mb_substr') ? mb_substr($message, 0, 500) : substr($message, 0, 500);
    }
}
