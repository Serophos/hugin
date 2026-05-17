<?php

declare(strict_types=1);

namespace Plugins\Tl1Menu;

use App\Core\AbstractSlidePlugin;
use App\Core\PluginApi;
use Plugins\Tl1Menu\Application\MenuService;
use Plugins\Tl1Menu\Infrastructure\MenuRepository;
use Plugins\Tl1Menu\Parser\MensaXmlParser;
use RuntimeException;

require_once __DIR__ . '/src/Domain/MenuItem.php';
require_once __DIR__ . '/src/Parser/MensaXmlParser.php';
require_once __DIR__ . '/src/Infrastructure/MenuRepository.php';
require_once __DIR__ . '/src/Application/MenuService.php';

class Plugin extends AbstractSlidePlugin
{
    /** @var array<string, mixed>|null */
    private ?array $config = null;
    private ?MenuService $service = null;

    public function getDefaultSettings(): array
    {
        $config = $this->getPluginConfig();
        return [
            'display_mode' => 'card',
            'mensa' => (string)($config['default_mensa'] ?? 'mensa1'),
            'language' => (string)($config['default_language'] ?? 'de'),
            'exclude_types' => array_values(array_map('intval', is_array($config['default_exclude'] ?? null) ? $config['default_exclude'] : [])),
            'display_student_price' => true,
            'display_employee_price' => true,
            'display_guest_price' => true,
            'display_co2' => (bool)($config['default_display_co2'] ?? true),
            'display_water' => (bool)($config['default_display_water'] ?? false),
            'display_animal_welfare' => (bool)($config['default_display_animal_welfare'] ?? false),
            'display_rainforest' => (bool)($config['default_display_rainforest'] ?? false),
            'environment_display_style' => 'global',
            'background_color_mode' => 'global',
            'background_color' => '',
            'background_image_mode' => 'global',
            'background_media_asset_id' => null,
            'show_header' => (bool)($config['default_show_header'] ?? true),
        ];
    }

    public function getDefaultGlobalSettings(): array
    {
        $config = $this->getPluginConfig();
        return [
            'background_color' => $this->normalizeColor((string)($config['default_background_color'] ?? '#f1f5f9')),
            'background_media_asset_id' => null,
            'environment_display_style' => $this->normalizeEnvironmentalDisplayStyle($config['default_environment_display_style'] ?? 'symbols', false),
        ];
    }

    public function renderAdminSettings(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
        $backgroundAssetId = $this->normalizeOptionalId($settings['background_media_asset_id'] ?? null);
        $backgroundAsset = $backgroundAssetId !== null ? $api->getMediaAsset($backgroundAssetId) : null;
        if (($backgroundAsset['media_kind'] ?? null) !== 'image') {
            $backgroundAsset = null;
        }

        return $this->renderView('views/config.php', [
            'settings' => $settings,
            'globalSettings' => $globalSettings,
            'plugin' => $this,
            'mensen' => $this->getMenuService()->getAvailableMensen(),
            'foodTypes' => $this->getMenuService()->getFoodTypes(),
            'imageMediaAssets' => $api->listMediaAssets('image'),
            'backgroundImageUrl' => $api->mediaAssetUrl($backgroundAsset),
        ]);
    }

    public function renderGlobalSettings(array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultGlobalSettings(), $settings);
        $backgroundAssetId = $this->normalizeOptionalId($settings['background_media_asset_id'] ?? null);
        $backgroundAsset = $backgroundAssetId !== null ? $api->getMediaAsset($backgroundAssetId) : null;
        if (($backgroundAsset['media_kind'] ?? null) !== 'image') {
            $backgroundAsset = null;
        }

        return $this->renderView('views/global_config.php', [
            'settings' => $settings,
            'plugin' => $this,
            'imageMediaAssets' => $api->listMediaAssets('image'),
            'backgroundImageUrl' => $api->mediaAssetUrl($backgroundAsset),
        ]);
    }

    public function normalizeGlobalSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        $existingSettings = array_replace($this->getDefaultGlobalSettings(), $existingSettings);
        $backgroundColor = $this->normalizeColorInput($input, (string)$existingSettings['background_color']);
        $backgroundMediaAssetId = $this->normalizeOptionalId($input['background_media_asset_id'] ?? null);
        $environmentDisplayStyle = $this->normalizeEnvironmentalDisplayStyle(
            $input['environment_display_style'] ?? $existingSettings['environment_display_style'] ?? 'symbols',
            false
        );

        $uploadedBackground = $api->pluginUploadedFile($this->getName(), 'background_image_file', 'plugin_global_settings');
        if ($uploadedBackground && ($uploadedBackground['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->assertUploadedImage($uploadedBackground);
            $mediaAsset = $api->storeMediaAsset($uploadedBackground, $this->getDisplayName() . ' background');
            if (!$mediaAsset || ($mediaAsset['media_kind'] ?? '') !== 'image') {
                throw new RuntimeException(__('plugins.tl-1menu.errors.background_invalid_type'));
            }
            $backgroundMediaAssetId = (int)$mediaAsset['id'];
        } elseif ($backgroundMediaAssetId !== null) {
            $mediaAsset = $api->getMediaAsset($backgroundMediaAssetId);
            if (!$mediaAsset) {
                throw new RuntimeException(__('plugins.tl-1menu.errors.background_asset_not_found'));
            }
            if (($mediaAsset['media_kind'] ?? '') !== 'image') {
                throw new RuntimeException(__('plugins.tl-1menu.errors.background_invalid_type'));
            }
        }

        return [
            'background_color' => $backgroundColor,
            'background_media_asset_id' => $backgroundMediaAssetId,
            'environment_display_style' => $environmentDisplayStyle,
        ];
    }

    public function normalizeSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        $defaults = $this->getDefaultSettings();
        $existingSettings = array_replace($defaults, $existingSettings);
        $service = $this->getMenuService();
        $availableMensen = $service->getAvailableMensen();
        $foodTypes = $service->getFoodTypes();
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));

        $displayMode = $this->normalizeDisplayMode($input['display_mode'] ?? $existingSettings['display_mode'] ?? $defaults['display_mode']);
        $environmentDisplayStyle = $this->normalizeEnvironmentalDisplayStyle(
            $input['environment_display_style'] ?? $existingSettings['environment_display_style'] ?? $defaults['environment_display_style'],
            true
        );
        $mensa = trim((string)($input['mensa'] ?? $defaults['mensa']));
        $language = trim((string)($input['language'] ?? $defaults['language']));
        $rawExcludeTypes = $input['exclude_types'] ?? $defaults['exclude_types'];
        $excludeTypes = [];
        if (is_array($rawExcludeTypes)) {
            foreach ($rawExcludeTypes as $typeId) {
                $intId = (int)$typeId;
                if (isset($foodTypes[$intId])) {
                    $excludeTypes[] = $intId;
                }
            }
        }
        sort($excludeTypes);
        $excludeTypes = array_values(array_unique($excludeTypes));

        if (!in_array($mensa, $availableMensen, true)) {
            throw new RuntimeException(__('plugins.tl-1menu.errors.invalid_mensa'));
        }
        if (!in_array($language, ['de', 'en'], true)) {
            throw new RuntimeException(__('plugins.tl-1menu.errors.invalid_language'));
        }

        $backgroundColorMode = $this->normalizeBackgroundColorMode($input['background_color_mode'] ?? $existingSettings['background_color_mode'] ?? 'global');
        $backgroundColor = '';
        if ($backgroundColorMode === 'custom') {
            $fallbackColor = (string)($existingSettings['background_color'] ?: $globalSettings['background_color']);
            $backgroundColor = $this->normalizeColorInput($input, $fallbackColor);
        }

        $backgroundImageMode = $this->normalizeBackgroundImageMode($input['background_image_mode'] ?? $existingSettings['background_image_mode'] ?? 'global');
        $backgroundMediaAssetId = $this->normalizeOptionalId($input['background_media_asset_id'] ?? ($existingSettings['background_media_asset_id'] ?? null));
        $uploadedBackground = $api->pluginUploadedFile($this->getName(), 'background_image_file');
        if ($uploadedBackground && ($uploadedBackground['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->assertUploadedImage($uploadedBackground);
            $mediaAsset = $api->storeMediaAsset($uploadedBackground, $this->getDisplayName() . ' slide background');
            if (!$mediaAsset || ($mediaAsset['media_kind'] ?? '') !== 'image') {
                throw new RuntimeException(__('plugins.tl-1menu.errors.background_invalid_type'));
            }
            $backgroundMediaAssetId = (int)$mediaAsset['id'];
            $backgroundImageMode = 'custom';
        } elseif ($backgroundImageMode === 'custom' && $backgroundMediaAssetId !== null) {
            $mediaAsset = $api->getMediaAsset($backgroundMediaAssetId);
            if (!$mediaAsset) {
                throw new RuntimeException(__('plugins.tl-1menu.errors.background_asset_not_found'));
            }
            if (($mediaAsset['media_kind'] ?? '') !== 'image') {
                throw new RuntimeException(__('plugins.tl-1menu.errors.background_invalid_type'));
            }
        }

        return [
            'display_mode' => $displayMode,
            'mensa' => $mensa,
            'language' => $language,
            'exclude_types' => $excludeTypes,
            'display_student_price' => !empty($input['display_student_price']),
            'display_employee_price' => !empty($input['display_employee_price']),
            'display_guest_price' => !empty($input['display_guest_price']),
            'display_co2' => !empty($input['display_co2']),
            'display_water' => !empty($input['display_water']),
            'display_animal_welfare' => !empty($input['display_animal_welfare']),
            'display_rainforest' => !empty($input['display_rainforest']),
            'environment_display_style' => $environmentDisplayStyle,
            'background_color_mode' => $backgroundColorMode,
            'background_color' => $backgroundColor,
            'background_image_mode' => $backgroundImageMode,
            'background_media_asset_id' => $backgroundImageMode === 'custom' ? $backgroundMediaAssetId : null,
            'show_header' => !empty($input['show_header']),
        ];
    }

    public function renderFrontend(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
        $language = in_array($settings['language'], ['de', 'en'], true) ? $settings['language'] : 'de';
        $today = $this->resolveEffectiveDate();
        $service = $this->getMenuService();
        $mensaKey = (string)$settings['mensa'];
        $mensaLabel = $service->getMensaLabel($mensaKey);
        $errorMessage = null;
        $resolvedBackground = $this->resolveBackground($settings, $globalSettings, $api);

        try {
            $items = $service->getMenuForDate($mensaKey, $today, is_array($settings['exclude_types'] ?? null) ? $settings['exclude_types'] : [], false);
        } catch (\Throwable $e) {
            $items = [];
            $errorMessage = __('plugins.tl-1menu.frontend.loading_error');
        }

        $view = $this->normalizeDisplayMode($settings['display_mode'] ?? 'card') === 'list'
            ? 'views/render_list.php'
            : 'views/render.php';

        return $this->renderView($view, [
            'plugin' => $this,
            'settings' => $settings,
            'language' => $language,
            'today' => $today,
            'formattedDate' => $service->formatDate($today, $language),
            'mensaLabel' => $mensaLabel,
            'items' => $items,
            'errorMessage' => $errorMessage,
            'service' => $service,
            'globalSettings' => $globalSettings,
            'environmentDisplayStyle' => $this->resolveEnvironmentalDisplayStyle($settings, $globalSettings),
            'environmentIconAssets' => $this->getEnvironmentalIconAssets($api),
            'backgroundColor' => $resolvedBackground['color'],
            'backgroundImageUrl' => $resolvedBackground['image_url'],
        ]);
    }

    public function getFrontendAssets(array $slide, array $settings, PluginApi $api): array
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        $assets = ['css' => [$api->pluginAssetUrl($this->getName(), 'assets/tl-1menu.css')], 'js' => []];
        if ($this->normalizeDisplayMode($settings['display_mode'] ?? 'card') === 'list') {
            $assets['js'][] = $api->pluginAssetUrl($this->getName(), 'assets/tl-1menu-list.js');
        }
        return $assets;
    }

    public function getStateData(array $slide, array $settings, PluginApi $api): array
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        return [
            'display_mode' => $this->normalizeDisplayMode($settings['display_mode'] ?? 'card'),
            'mensa' => $settings['mensa'],
            'language' => $settings['language'],
            'date' => $this->resolveEffectiveDate(),
            'exclude_types' => $settings['exclude_types'],
            'display_student_price' => (bool)$settings['display_student_price'],
            'display_employee_price' => (bool)$settings['display_employee_price'],
            'display_guest_price' => (bool)$settings['display_guest_price'],
            'display_co2' => (bool)$settings['display_co2'],
            'display_water' => (bool)$settings['display_water'],
            'display_animal_welfare' => (bool)$settings['display_animal_welfare'],
            'display_rainforest' => (bool)$settings['display_rainforest'],
            'environment_display_style' => $settings['environment_display_style'],
            'background_color_mode' => $settings['background_color_mode'],
            'background_color' => $settings['background_color'],
            'background_image_mode' => $settings['background_image_mode'],
            'background_media_asset_id' => $settings['background_media_asset_id'],
            'show_header' => (bool)$settings['show_header'],
            'global' => array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName())),
        ];
    }

    /** @return array<string, mixed> */
    private function getPluginConfig(): array
    {
        if ($this->config === null) {
            $configFile = __DIR__ . '/config.php';
            if (!is_file($configFile)) {
                $configFile = __DIR__ . '/config.example.php';
            }
            $loaded = is_file($configFile) ? require $configFile : [];
            $this->config = is_array($loaded) ? $loaded : [];
        }
        return $this->config;
    }

    public function getMenuService(): MenuService
    {
        if ($this->service === null) {
            $config = $this->getPluginConfig();
            $parser = new MensaXmlParser($config);
            $repository = new MenuRepository($parser, $config);
            $this->service = new MenuService($repository, $config);
        }
        return $this->service;
    }

    private function resolveEffectiveDate(): string
    {
        $config = $this->getPluginConfig();
        $debugDate = trim((string)($config['debug_date'] ?? ''));
        if ($debugDate !== '') {
            $timestamp = strtotime($debugDate);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        return date('Y-m-d');
    }

    private function normalizeColor(string $value): string
    {
        $value = trim($value);
        if ($value !== '' && $value[0] !== '#') {
            $value = '#' . $value;
        }

        return \normalize_hex_color($value, '#f1f5f9');
    }

    /** @param array<string, mixed> $input */
    private function normalizeColorInput(array $input, string $default): string
    {
        $hex = trim((string)($input['background_color'] ?? ''));
        if ($hex !== '') {
            if ($hex[0] !== '#') {
                $hex = '#' . $hex;
            }
            $normalized = \normalize_hex_color($hex, '');
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $channels = [];
        foreach (['background_red', 'background_green', 'background_blue'] as $key) {
            if (!isset($input[$key]) || !is_numeric($input[$key])) {
                $channels = [];
                break;
            }
            $channels[] = max(0, min(255, (int)$input[$key]));
        }

        if (count($channels) === 3) {
            return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
        }

        return $this->normalizeColor($default);
    }

    private function normalizeOptionalId(mixed $value): ?int
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        $id = (int)$value;
        return $id > 0 ? $id : null;
    }

    private function normalizeDisplayMode(mixed $value): string
    {
        $mode = strtolower(trim((string)$value));
        return in_array($mode, ['card', 'list'], true) ? $mode : 'card';
    }

    private function normalizeEnvironmentalDisplayStyle(mixed $value, bool $allowGlobal): string
    {
        $style = strtolower(trim((string)$value));
        $allowed = $allowGlobal ? ['global', 'symbols', 'values'] : ['symbols', 'values'];
        return in_array($style, $allowed, true) ? $style : ($allowGlobal ? 'global' : 'symbols');
    }

    /** @param array<string, mixed> $settings @param array<string, mixed> $globalSettings */
    private function resolveEnvironmentalDisplayStyle(array $settings, array $globalSettings): string
    {
        $style = $this->normalizeEnvironmentalDisplayStyle($settings['environment_display_style'] ?? 'global', true);
        if ($style === 'global') {
            return $this->normalizeEnvironmentalDisplayStyle($globalSettings['environment_display_style'] ?? 'symbols', false);
        }

        return $style;
    }

    private function normalizeBackgroundColorMode(mixed $value): string
    {
        return strtolower(trim((string)$value)) === 'custom' ? 'custom' : 'global';
    }

    private function normalizeBackgroundImageMode(mixed $value): string
    {
        $mode = strtolower(trim((string)$value));
        return in_array($mode, ['global', 'none', 'custom'], true) ? $mode : 'global';
    }

    /** @param array<string, mixed> $settings @param array<string, mixed> $globalSettings @return array{color: string, image_url: ?string} */
    private function resolveBackground(array $settings, array $globalSettings, PluginApi $api): array
    {
        $backgroundColor = $this->normalizeColor((string)($globalSettings['background_color'] ?? '#f1f5f9'));
        if ($this->normalizeBackgroundColorMode($settings['background_color_mode'] ?? 'global') === 'custom') {
            $customColor = trim((string)($settings['background_color'] ?? ''));
            if ($customColor !== '') {
                $backgroundColor = $this->normalizeColor($customColor);
            }
        }

        $backgroundImageMode = $this->normalizeBackgroundImageMode($settings['background_image_mode'] ?? 'global');
        $backgroundMediaAssetId = null;
        if ($backgroundImageMode === 'global') {
            $backgroundMediaAssetId = $this->normalizeOptionalId($globalSettings['background_media_asset_id'] ?? null);
        } elseif ($backgroundImageMode === 'custom') {
            $backgroundMediaAssetId = $this->normalizeOptionalId($settings['background_media_asset_id'] ?? null);
        }

        return [
            'color' => $backgroundColor,
            'image_url' => $api->mediaAssetUrl($backgroundMediaAssetId),
        ];
    }

    /** @return array<string, array{filled: string, outline: string}> */
    private function getEnvironmentalIconAssets(PluginApi $api): array
    {
        $assets = [];
        foreach (['leaf', 'drop', 'heart', 'tree'] as $icon) {
            $assets[$icon] = [
                'filled' => $api->pluginAssetUrl($this->getName(), 'assets/img/' . $icon . '-filled.svg'),
                'outline' => $api->pluginAssetUrl($this->getName(), 'assets/img/' . $icon . '-outline.svg'),
            ];
        }

        return $assets;
    }

    public function environmentalSymbolFillCount(mixed $rating): int
    {
        $rating = strtoupper(trim((string)$rating));
        if (in_array($rating, ['A', 'B'], true)) {
            return 3;
        }
        if (in_array($rating, ['C', 'D'], true)) {
            return 2;
        }
        if (in_array($rating, ['E', 'F'], true)) {
            return 1;
        }

        return 0;
    }

    /** @param array<string, mixed> $file */
    private function assertUploadedImage(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return;
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '') {
            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo !== false ? (string)finfo_file($finfo, $tmpName) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            throw new RuntimeException(__('plugins.tl-1menu.errors.background_invalid_type'));
        }
    }
}
