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
            'show_header' => (bool)($config['default_show_header'] ?? true),
        ];
    }

    public function getDefaultGlobalSettings(): array
    {
        $config = $this->getPluginConfig();
        return [
            'background_color' => $this->normalizeColor((string)($config['default_background_color'] ?? '#f1f5f9')),
            'background_media_asset_id' => null,
        ];
    }

    public function renderAdminSettings(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        return $this->renderView('views/config.php', [
            'settings' => $settings,
            'plugin' => $this,
            'mensen' => $this->getMenuService()->getAvailableMensen(),
            'foodTypes' => $this->getMenuService()->getFoodTypes(),
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
        ];
    }

    public function normalizeSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        $defaults = $this->getDefaultSettings();
        $existingSettings = array_replace($defaults, $existingSettings);
        $service = $this->getMenuService();
        $availableMensen = $service->getAvailableMensen();
        $foodTypes = $service->getFoodTypes();

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

        return [
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

        try {
            $items = $service->getMenuForDate($mensaKey, $today, is_array($settings['exclude_types'] ?? null) ? $settings['exclude_types'] : [], false);
        } catch (\Throwable $e) {
            $items = [];
            $errorMessage = __('plugins.tl-1menu.frontend.loading_error');
        }

        return $this->renderView('views/render.php', [
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
            'backgroundImageUrl' => $api->mediaAssetUrl($this->normalizeOptionalId($globalSettings['background_media_asset_id'] ?? null)),
        ]);
    }

    public function getFrontendAssets(array $slide, array $settings, PluginApi $api): array
    {
        return ['css' => [$api->pluginAssetUrl($this->getName(), 'assets/tl-1menu.css')], 'js' => []];
    }

    public function getStateData(array $slide, array $settings, PluginApi $api): array
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
        return [
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
