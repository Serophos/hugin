<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Optional capability for plugins that provide server-side scheduled work.
 *
 * Task definitions contain a stable `name`, an `interval_seconds` value of at
 * least 60, and may contain a `retry_seconds` value of at least 60.
 */
interface ScheduledTaskProviderInterface
{
    /** @return list<array{name:string,interval_seconds:int,retry_seconds?:int}> */
    public function getScheduledTasks(PluginApi $api): array;

    public function runScheduledTask(string $taskName, PluginApi $api): void;
}
