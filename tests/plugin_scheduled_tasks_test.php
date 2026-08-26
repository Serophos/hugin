<?php

declare(strict_types=1);

use App\Services\PluginTaskScheduler;
use Plugins\Tl1Menu\Menu\MenuRepository;
use Plugins\Tl1Menu\Menu\MensaXmlParser;

require_once __DIR__ . '/../app/Services/PluginTaskScheduler.php';
require_once __DIR__ . '/../plugins/tl1-menu/Menu/MenuItem.php';
require_once __DIR__ . '/../plugins/tl1-menu/Menu/MensaXmlParser.php';
require_once __DIR__ . '/../plugins/tl1-menu/Menu/MenuRepository.php';

function task_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expect_task_error(callable $callback, string $fragment): void
{
    try {
        $callback();
    } catch (RuntimeException $error) {
        task_assert(str_contains($error->getMessage(), $fragment), 'Unexpected error: ' . $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected error containing: ' . $fragment);
}

$scheduler = (new ReflectionClass(PluginTaskScheduler::class))->newInstanceWithoutConstructor();
$normalized = $scheduler->normalizeDefinitions([
    ['name' => 'refresh-feed', 'interval_seconds' => 1800],
    ['name' => 'cleanup_cache', 'interval_seconds' => 600, 'retry_seconds' => 120],
]);
task_assert($normalized[0]['retry_seconds'] === 300, 'Default retry must be capped at 300 seconds.');
task_assert($normalized[1]['retry_seconds'] === 120, 'Explicit valid retry was not retained.');
expect_task_error(static fn() => $scheduler->normalizeDefinitions([
    ['name' => 'too-fast', 'interval_seconds' => 59],
]), 'at least 60 seconds');
expect_task_error(static fn() => $scheduler->normalizeDefinitions([
    ['name' => 'duplicate', 'interval_seconds' => 60],
    ['name' => 'duplicate', 'interval_seconds' => 120],
]), 'Duplicate task name');
expect_task_error(static fn() => $scheduler->normalizeDefinitions([
    ['name' => 'Invalid Name', 'interval_seconds' => 60],
]), 'Task names');

$slideInterface = (string)file_get_contents(__DIR__ . '/../app/Core/SlidePluginInterface.php');
task_assert(!str_contains($slideInterface, 'getScheduledTasks'), 'Scheduled tasks must remain an optional plugin capability.');

$temporary = sys_get_temp_dir() . '/hugin-tl1-task-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
$source = $temporary . '/source.xml';
$cache = $temporary . '/cache/speiseplan.xml';
mkdir(dirname($cache), 0700, true);
$firstXml = '<?xml version="1.0"?><DATAPACKET><ROWDATA><ROW ID="1" DATUM="2026-08-26"/></ROWDATA></DATAPACKET>';
$secondXml = '<?xml version="1.0"?><DATAPACKET><ROWDATA><ROW ID="2" DATUM="2026-08-26"/></ROWDATA></DATAPACKET>';
$config = [
    'menu_url' => 'file://' . $source,
    'cache_ttl' => 60,
    'schema_version' => 2,
    'field_mapping' => ['id' => 'ID', 'date' => 'DATUM'],
];

try {
    file_put_contents($source, $firstXml);
    $repository = new MenuRepository(new MensaXmlParser($config), $config, $cache);
    $repository->refreshCache(false);
    task_assert((string)file_get_contents($cache) === $firstXml, 'Valid XML did not replace the cache.');
    $firstRevision = $repository->contentRevision();
    task_assert($firstRevision === hash('sha256', $firstXml), 'Cache revision is not the accepted XML content hash.');

    $repository->refreshCache(false);
    task_assert($repository->contentRevision() === $firstRevision, 'Identical XML changed the content revision.');

    file_put_contents($source, $secondXml);
    $repository->refreshCache(false);
    $secondRevision = $repository->contentRevision();
    task_assert($secondRevision === hash('sha256', $secondXml) && $secondRevision !== $firstRevision, 'Changed XML did not change the content revision.');

    file_put_contents($source, '');
    expect_task_error(static fn() => $repository->refreshCache(false), 'Could not download TL1 menu XML');
    task_assert((string)file_get_contents($cache) === $secondXml, 'Empty XML replaced the last known-good cache.');

    file_put_contents($source, '<DATAPACKET><ROWDATA>');
    expect_task_error(static fn() => $repository->refreshCache(false), 'Could not load XML file');
    task_assert((string)file_get_contents($cache) === $secondXml, 'Malformed XML replaced the last known-good cache.');
    task_assert($repository->contentRevision() === $secondRevision, 'Malformed XML changed the accepted revision.');

    $missingConfig = array_replace($config, ['menu_url' => 'file://' . $temporary . '/missing.xml']);
    $fallbackRepository = new MenuRepository(new MensaXmlParser($missingConfig), $missingConfig, $cache);
    task_assert($fallbackRepository->refreshCache(true) === $cache, 'Unavailable feed did not return the cached fallback.');
    task_assert($fallbackRepository->contentRevision() === $secondRevision, 'Fallback changed the accepted revision.');

    $pluginSource = (string)file_get_contents(__DIR__ . '/../plugins/tl1-menu/Plugin.php');
    task_assert(str_contains($pluginSource, "'menu_content_revision'"), 'TL1 plugin state does not expose its menu revision.');
    $frontendSource = (string)file_get_contents(__DIR__ . '/../app/Controllers/FrontendController.php');
    task_assert(str_contains($frontendSource, "'plugin_state' => \$slide['plugin_state']"), 'Plugin state is no longer part of display state.');
} finally {
    foreach (glob($temporary . '/cache/*') ?: [] as $file) {
        @unlink($file);
    }
    @unlink($source);
    @rmdir($temporary . '/cache');
    @rmdir($temporary);
}

echo "Plugin scheduled-task and TL1 cache tests passed.\n";
