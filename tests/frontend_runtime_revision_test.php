<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/helpers.php';

use App\Controllers\FrontendController;

function revision_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$method = new ReflectionMethod(FrontendController::class, 'coreFrontendRuntimeRevisionForContents');
$method->setAccessible(true);
$revision = static fn(array $contents): string => (string)$method->invoke(null, $contents);

$expectedCorePaths = [
    '/assets/css/display.css',
    '/assets/js/hugin-qr.js',
    '/assets/js/playback-scheduler.js',
    '/assets/js/display-heartbeat.js',
    '/assets/js/display-media-lifecycle.js',
    '/assets/js/slideshow.js',
    '/display-service-worker.js',
];
$baseline = [
    '/assets/css/display.css' => 'body{color:alpha}',
    '/assets/js/hugin-qr.js' => 'const qr=alpha;',
    '/assets/js/playback-scheduler.js' => 'const scheduler=alpha;',
    '/assets/js/display-heartbeat.js' => 'const beat=alpha;',
    '/assets/js/display-media-lifecycle.js' => 'const media=alpha;',
    '/assets/js/slideshow.js' => 'const show=alpha;',
    '/display-service-worker.js' => 'const cache=alpha;',
];
$sameContentsDifferentOrder = array_reverse($baseline, true);
$sameLengthCoreEdit = $baseline;
$sameLengthCoreEdit['/assets/js/slideshow.js'] = 'const show=bravo;';
$missingCoreAsset = $baseline;
$missingCoreAsset['/assets/js/slideshow.js'] = null;

$first = $revision($baseline);
revision_assert($first !== '', 'The core frontend revision must not be empty.');
revision_assert(
    $revision($baseline) === $first,
    'Unchanged core frontend contents must produce a stable revision.'
);
revision_assert(
    $revision($sameContentsDifferentOrder) === $first,
    'Revision stability must not depend on associative-array insertion order.'
);
revision_assert(
    $revision($sameLengthCoreEdit) !== $first,
    'A same-length core asset edit must change the revision and request a display reload.'
);
revision_assert(
    $revision($missingCoreAsset) !== $first,
    'A missing core runtime asset must also change the revision.'
);
$changedRevision = $revision($sameLengthCoreEdit);
$urlMethod = new ReflectionMethod(FrontendController::class, 'coreFrontendAssetUrlForRevision');
$urlMethod->setAccessible(true);
$GLOBALS['app_config'] = [
    'app' => ['base_url' => 'https://display.example.test'],
    'paths' => ['public' => realpath(__DIR__ . '/../public')],
];
$baselineUrl = (string)$urlMethod->invoke(null, '/assets/js/slideshow.js', $first);
$changedUrl = (string)$urlMethod->invoke(null, '/assets/js/slideshow.js', $changedRevision);
revision_assert($baselineUrl !== $changedUrl, 'A core content edit must produce a different runtime URL.');
parse_str((string)parse_url($baselineUrl, PHP_URL_QUERY), $baselineQuery);
parse_str((string)parse_url($changedUrl, PHP_URL_QUERY), $changedQuery);
revision_assert(
    ($baselineQuery['runtime'] ?? '') === $first,
    'The display runtime URL must carry the baseline content revision.'
);
revision_assert(
    ($changedQuery['runtime'] ?? '') === $changedRevision,
    'The changed display runtime URL must carry the new content revision.'
);

$controller = (new ReflectionClass(FrontendController::class))->newInstanceWithoutConstructor();
$publicRoot = realpath(__DIR__ . '/../public');
revision_assert(is_string($publicRoot), 'The production public root must exist.');
$productionContents = [];
foreach ($expectedCorePaths as $publicPath) {
    $content = file_get_contents($publicRoot . $publicPath);
    revision_assert(is_string($content), 'Expected production runtime asset is missing: ' . $publicPath);
    $productionContents[$publicPath] = $content;
}
$productionMethod = new ReflectionMethod(FrontendController::class, 'coreFrontendRuntimeRevision');
$productionMethod->setAccessible(true);
revision_assert(
    $productionMethod->invoke($controller) === $revision($productionContents),
    'The production revision must cover the complete expected core runtime asset set.'
);
$signMethod = new ReflectionMethod(FrontendController::class, 'signDisplayState');
$signMethod->setAccessible(true);
$payload = [
    'core_frontend_revision' => $first,
    'frontend_assets' => [
        'css' => ['https://plugin.example.test/first.css'],
        'js' => [],
        'service_worker' => $baselineUrl,
    ],
    'slides' => [],
    'next_selection_at_ms' => 0,
];
$baselineState = $signMethod->invoke($controller, $payload);
$dynamicAssetEdit = $payload;
$dynamicAssetEdit['frontend_assets']['css'] = ['https://plugin.example.test/changed.css'];
$dynamicAssetState = $signMethod->invoke($controller, $dynamicAssetEdit);
$coreEdit = $payload;
$coreEdit['core_frontend_revision'] = $changedRevision;
$coreEditState = $signMethod->invoke($controller, $coreEdit);
revision_assert(
    $dynamicAssetState['signature'] === $baselineState['signature'],
    'Dynamic frontend asset URLs must remain excluded to prevent the historical reload loop.'
);
revision_assert(
    $coreEditState['signature'] !== $baselineState['signature'],
    'Changing the core frontend revision must change display state and request a reload.'
);

echo "frontend runtime revision tests passed\n";
