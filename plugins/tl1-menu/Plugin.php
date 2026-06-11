<?php

declare(strict_types=1);

namespace Plugins\Tl1Menu;

use App\Core\AbstractSlidePlugin;
use App\Core\PluginApi;
use Plugins\Tl1Menu\Menu\MenuRepository;
use Plugins\Tl1Menu\Menu\MenuService;
use Plugins\Tl1Menu\Menu\MensaXmlParser;
use Plugins\Tl1Menu\Setup\Tl1SetupAnalyzer;
use RuntimeException;

require_once __DIR__ . '/Menu/MenuItem.php';
require_once __DIR__ . '/Menu/MensaXmlParser.php';
require_once __DIR__ . '/Menu/MenuRepository.php';
require_once __DIR__ . '/Menu/MenuService.php';
require_once __DIR__ . '/Setup/Tl1SetupAnalyzer.php';

class Plugin extends AbstractSlidePlugin
{
    private ?MenuService $service = null;

    public function getDefaultSettings(): array
    {
        return $this->defaultSlideSettings($this->getDefaultGlobalSettings());
    }

    public function getDefaultGlobalSettings(): array
    {
        return [
            'menu_url' => '',
            'cache_ttl' => 1800,
            'debug_date' => null,
            'default_language' => 'de',
            'default_mensa' => '',
            'default_exclude' => [],
            'default_display_co2' => true,
            'default_display_water' => false,
            'default_display_animal_welfare' => false,
            'default_display_rainforest' => false,
            'default_show_header' => true,
            'background_color' => '#f1f5f9',
            'background_media_asset_id' => null,
            'environment_display_style' => 'symbols',
            'environment_rating_icons' => [
                'co2' => 'assets/img/environment/leaf-filled.svg',
                'water' => 'assets/img/environment/drop-filled.svg',
                'animal_welfare' => 'assets/img/environment/heart-filled.svg',
                'rainforest' => 'assets/img/environment/tree-filled.svg',
            ],
            'parser_config' => $this->runtimeSafeDefaults(),
        ];
    }

    /** @param array<string, mixed> $globalSettings */
    private function defaultSlideSettings(array $globalSettings): array
    {
        return [
            'display_mode' => 'card',
            'mensa' => (string)($globalSettings['default_mensa'] ?? ''),
            'language' => $this->normalizeLanguage($globalSettings['default_language'] ?? 'de'),
            'exclude_types' => array_values(array_map('intval', is_array($globalSettings['default_exclude'] ?? null) ? $globalSettings['default_exclude'] : [])),
            'display_price_groups' => array_fill_keys(array_column($this->getMenuService($globalSettings)->getPriceGroups(), 'key'), true),
            'display_student_price' => true,
            'display_employee_price' => true,
            'display_guest_price' => true,
            'display_co2' => (bool)($globalSettings['default_display_co2'] ?? true),
            'display_water' => (bool)($globalSettings['default_display_water'] ?? false),
            'display_animal_welfare' => (bool)($globalSettings['default_display_animal_welfare'] ?? false),
            'display_rainforest' => (bool)($globalSettings['default_display_rainforest'] ?? false),
            'environment_display_style' => 'global',
            'background_color_mode' => 'global',
            'background_color' => '',
            'background_image_mode' => 'global',
            'background_media_asset_id' => null,
            'show_header' => (bool)($globalSettings['default_show_header'] ?? true),
        ];
    }

    public function renderAdminSettings(array $slide, array $settings, PluginApi $api): string
    {
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
        $settings = array_replace($this->defaultSlideSettings($globalSettings), $settings);
        $service = $this->getMenuService($globalSettings);
        $backgroundAssetId = $this->normalizeOptionalId($settings['background_media_asset_id'] ?? null);
        $backgroundAsset = $backgroundAssetId !== null ? $api->getMediaAsset($backgroundAssetId) : null;
        if (($backgroundAsset['media_kind'] ?? null) !== 'image') {
            $backgroundAsset = null;
        }

        return $this->renderView('views/slide_settings.php', [
            'settings' => $settings,
            'globalSettings' => $globalSettings,
            'plugin' => $this,
            'menuService' => $service,
            'mensen' => $service->getAvailableMensen(),
            'foodTypes' => $service->getFoodTypes(),
            'priceGroups' => $service->getPriceGroups(),
            'imageMediaAssets' => $api->listMediaAssets('image'),
            'backgroundImageUrl' => $api->mediaAssetUrl($backgroundAsset),
            'displayModePreviews' => [
                'card' => $api->pluginAssetUrl($this->getName(), 'assets/img/display-modes/card.svg'),
                'list' => $api->pluginAssetUrl($this->getName(), 'assets/img/display-modes/list.svg'),
            ],
            'environmentIconAssets' => $this->getEnvironmentalIconAssets($api),
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

        $service = $this->getMenuService($settings);

        return $this->renderView('views/global_settings.php', [
            'settings' => $settings,
            'plugin' => $this,
            'menuService' => $service,
            'mensen' => $service->getAvailableMensen(),
            'foodTypes' => $service->getFoodTypes(),
            'imageMediaAssets' => $api->listMediaAssets('image'),
            'backgroundImageUrl' => $api->mediaAssetUrl($backgroundAsset),
            'environmentIconAssets' => $this->getEnvironmentalIconAssets($api),
            'categoryIconChoices' => $this->getCategoryIconChoices($api),
            'setupActionBaseUrl' => url('/admin/plugins/' . $this->getName() . '/actions'),
            'parserConfig' => is_array($settings['parser_config'] ?? null) ? $settings['parser_config'] : $this->runtimeSafeDefaults(),
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
                throw new RuntimeException(__('plugins.tl1-menu.errors.background_invalid_type'));
            }
            $backgroundMediaAssetId = (int)$mediaAsset['id'];
        } elseif ($backgroundMediaAssetId !== null) {
            $mediaAsset = $api->getMediaAsset($backgroundMediaAssetId);
            if (!$mediaAsset) {
                throw new RuntimeException(__('plugins.tl1-menu.errors.background_asset_not_found'));
            }
            if (($mediaAsset['media_kind'] ?? '') !== 'image') {
                throw new RuntimeException(__('plugins.tl1-menu.errors.background_invalid_type'));
            }
        }

        $menuUrl = trim((string)($input['menu_url'] ?? $existingSettings['menu_url'] ?? ''));
        if ($menuUrl !== '' && filter_var($menuUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.invalid_menu_url'));
        }
        $menuUrlScheme = strtolower((string)(parse_url($menuUrl, PHP_URL_SCHEME) ?? ''));
        if ($menuUrl !== '' && !in_array($menuUrlScheme, ['http', 'https'], true)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.invalid_menu_url'));
        }
        $cacheTtl = max(60, (int)($input['cache_ttl'] ?? $existingSettings['cache_ttl'] ?? 1800));

        $normalized = [
            'menu_url' => $menuUrl,
            'cache_ttl' => $cacheTtl,
            'debug_date' => trim((string)($input['debug_date'] ?? $existingSettings['debug_date'] ?? '')) ?: null,
            'default_language' => $this->normalizeLanguage($input['default_language'] ?? $existingSettings['default_language'] ?? 'de'),
            'default_mensa' => trim((string)($input['default_mensa'] ?? $existingSettings['default_mensa'] ?? '')),
            'default_exclude' => $this->normalizeIntCsv(array_key_exists('default_exclude', $input) ? $input['default_exclude'] : []),
            'default_display_co2' => !empty($input['default_display_co2']),
            'default_display_water' => !empty($input['default_display_water']),
            'default_display_animal_welfare' => !empty($input['default_display_animal_welfare']),
            'default_display_rainforest' => !empty($input['default_display_rainforest']),
            'default_show_header' => !empty($input['default_show_header']),
            'background_color' => $backgroundColor,
            'background_media_asset_id' => $backgroundMediaAssetId,
            'environment_display_style' => $environmentDisplayStyle,
            'environment_rating_icons' => $this->normalizeEnvironmentIconInput($input['environment_rating_icons'] ?? $existingSettings['environment_rating_icons'] ?? []),
            'parser_config' => is_array($existingSettings['parser_config'] ?? null) ? $existingSettings['parser_config'] : $this->runtimeSafeDefaults(),
        ];

        if ($menuUrl !== '') {
            $this->refreshMenuXmlCache($normalized, $api);
        }

        return $normalized;
    }

    public function normalizeSettings(array $input, array $existingSettings, PluginApi $api): array
    {
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
        $defaults = $this->defaultSlideSettings($globalSettings);
        $existingSettings = array_replace($defaults, $existingSettings);
        $service = $this->getMenuService($globalSettings);
        $availableMensen = $service->getAvailableMensen();
        $foodTypes = $service->getFoodTypes();

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

        $availablePriceGroups = array_column($service->getPriceGroups(), 'key');
        $rawDisplayPriceGroups = $input['display_price_groups'] ?? $existingSettings['display_price_groups'] ?? [];
        $displayPriceGroups = [];
        if (is_array($rawDisplayPriceGroups)) {
            foreach ($availablePriceGroups as $priceGroupKey) {
                $displayPriceGroups[$priceGroupKey] = !empty($rawDisplayPriceGroups[$priceGroupKey]) || in_array($priceGroupKey, $rawDisplayPriceGroups, true);
            }
        }
        if ($displayPriceGroups === []) {
            foreach ($availablePriceGroups as $priceGroupKey) {
                $legacyKey = 'display_' . ($priceGroupKey === 'staff' ? 'employee' : $priceGroupKey) . '_price';
                $displayPriceGroups[$priceGroupKey] = array_key_exists($legacyKey, $input) ? !empty($input[$legacyKey]) : true;
            }
        }

        if ($availableMensen !== [] && !in_array($mensa, $availableMensen, true)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.invalid_mensa'));
        }
        if (!in_array($language, ['de', 'en'], true)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.invalid_language'));
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
                throw new RuntimeException(__('plugins.tl1-menu.errors.background_invalid_type'));
            }
            $backgroundMediaAssetId = (int)$mediaAsset['id'];
            $backgroundImageMode = 'custom';
        } elseif ($backgroundImageMode === 'custom' && $backgroundMediaAssetId !== null) {
            $mediaAsset = $api->getMediaAsset($backgroundMediaAssetId);
            if (!$mediaAsset) {
                throw new RuntimeException(__('plugins.tl1-menu.errors.background_asset_not_found'));
            }
            if (($mediaAsset['media_kind'] ?? '') !== 'image') {
                throw new RuntimeException(__('plugins.tl1-menu.errors.background_invalid_type'));
            }
        }

        return [
            'display_mode' => $displayMode,
            'mensa' => $mensa,
            'language' => $language,
            'exclude_types' => $excludeTypes,
            'display_price_groups' => $displayPriceGroups,
            'display_student_price' => !empty($displayPriceGroups['student']),
            'display_employee_price' => !empty($displayPriceGroups['staff']),
            'display_guest_price' => !empty($displayPriceGroups['guest']),
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
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
        $settings = array_replace($this->defaultSlideSettings($globalSettings), $settings);
        $language = in_array($settings['language'], ['de', 'en'], true) ? $settings['language'] : 'de';
        $today = $this->resolveEffectiveDate($globalSettings);
        $service = $this->getMenuService($globalSettings);
        $mensaKey = (string)$settings['mensa'];
        $mensaLabel = $service->getMensaLabel($mensaKey);
        $errorMessage = null;
        $resolvedBackground = $this->resolveBackground($settings, $globalSettings, $api);

        try {
            $items = $service->getMenuForDate($mensaKey, $today, is_array($settings['exclude_types'] ?? null) ? $settings['exclude_types'] : [], false, $language);
        } catch (\Throwable $e) {
            $items = [];
            $errorMessage = __('plugins.tl1-menu.frontend.loading_error');
        }

        $view = $this->normalizeDisplayMode($settings['display_mode'] ?? 'card') === 'list'
            ? 'views/render_list.php'
            : 'views/render.php';

        return $this->renderView($view, [
            'plugin' => $this,
            'api' => $api,
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
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
        $settings = array_replace($this->defaultSlideSettings($globalSettings), $settings);
        $assets = ['css' => [$api->pluginAssetUrl($this->getName(), 'assets/tl1menu.css')], 'js' => []];
        if ($this->normalizeDisplayMode($settings['display_mode'] ?? 'card') === 'list') {
            $assets['js'][] = $api->pluginAssetUrl($this->getName(), 'assets/tl1menu-list.js');
        }
        return $assets;
    }

    public function getStateData(array $slide, array $settings, PluginApi $api): array
    {
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
        $settings = array_replace($this->defaultSlideSettings($globalSettings), $settings);
        return [
            'display_mode' => $this->normalizeDisplayMode($settings['display_mode'] ?? 'card'),
            'mensa' => $settings['mensa'],
            'language' => $settings['language'],
            'date' => $this->resolveEffectiveDate($globalSettings),
            'exclude_types' => $settings['exclude_types'],
            'display_student_price' => (bool)$settings['display_student_price'],
            'display_employee_price' => (bool)$settings['display_employee_price'],
            'display_guest_price' => (bool)$settings['display_guest_price'],
            'display_price_groups' => $settings['display_price_groups'] ?? [],
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
            'global' => $globalSettings,
        ];
    }

    public function handleAdminAction(string $action, array $input, PluginApi $api): array
    {
        if ($action === 'upload-category-icon') {
            return $this->storeCategoryIconUpload($api->pluginUploadedFile($this->getName(), 'category_icon_file'), $api);
        }

        $analyzer = new Tl1SetupAnalyzer($api->pluginCachePath($this->getName(), 'speiseplan.xml'));

        if ($action === 'analyze-url') {
            $sourceUrl = trim((string)($input['menu_url'] ?? ''));
            if ($sourceUrl === '') {
                $globalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
                $sourceUrl = (string)($globalSettings['menu_url'] ?? '');
            }
            return $analyzer->analyzeCachedXml($sourceUrl);
        }

        if ($action === 'analyze-mapping') {
            $config = $this->decodeGeneratedConfig((string)($input['config_json'] ?? ''));
            return $analyzer->analyzeCachedWithConfig($config);
        }

        if ($action === 'preview-row') {
            $config = $this->decodeGeneratedConfig((string)($input['config_json'] ?? ''));
            $rowPreview = $analyzer->previewCachedRow($config, (int)($input['row_index'] ?? 0));
            $tmp = tempnam(sys_get_temp_dir(), 'tl1-preview-');
            if ($tmp === false) {
                throw new RuntimeException(__('plugins.tl1-menu.errors.setup_preview_failed'));
            }
            $xml = '<?xml version="1.0"?><DATAPACKET><ROWDATA><ROW';
            foreach ($rowPreview['row'] as $key => $value) {
                $xml .= ' ' . $key . '="' . htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"';
            }
            $xml .= "/></ROWDATA></DATAPACKET>";
            file_put_contents($tmp, $xml);
            try {
                $parser = new MensaXmlParser(array_replace($this->runtimeConfig($api->loadGlobalSettings($this->getName())), $config));
                $items = $parser->parseFile($tmp, ['language' => (string)($input['language'] ?? 'de')]);
            } finally {
                @unlink($tmp);
            }
            $item = $items[0] ?? null;
            return array_replace($rowPreview, ['item' => $item ? $this->menuItemToArray($item) : null]);
        }

        if ($action === 'save-config') {
            $config = $this->decodeGeneratedConfig((string)($input['config_json'] ?? ''));
            $config['setup']['generated_at'] = date('c');
            $globalSettingsJson = (string)($input['global_settings_json'] ?? '');
            $existingGlobal = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
            $normalizedGlobal = $existingGlobal;
            if ($globalSettingsJson !== '') {
                $globalInput = json_decode($globalSettingsJson, true);
                if (is_array($globalInput)) {
                    $normalizedGlobal = $this->normalizeGlobalSettings($globalInput, $existingGlobal, $api);
                }
            }
            $normalizedGlobal['parser_config'] = $config;
            $api->saveGlobalSettings($this->getName(), $normalizedGlobal);
            $this->service = null;
            return ['saved' => ['storage' => 'database', 'parser_config' => true]];
        }

        return parent::handleAdminAction($action, $input, $api);
    }

    /** @return array<string, mixed> */
    private function decodeGeneratedConfig(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.config_invalid_generated'));
        }
        $this->assertGeneratedConfig($decoded);
        return $decoded;
    }

    private function menuItemToArray(\Plugins\Tl1Menu\Menu\MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'date' => $item->date,
            'mensa_key' => $item->mensaKey,
            'mensa_name' => $item->mensaName,
            'location_id' => $item->locationId,
            'location_name' => $item->locationName,
            'type_id' => $item->typeId,
            'type_name' => $item->typeName,
            'spalte' => $item->spalte,
            'title_de' => $item->titleDe,
            'title_en' => $item->titleEn,
            'description_de' => $item->descriptionDe,
            'description_en' => $item->descriptionEn,
            'prices' => $item->prices,
            'allergens' => $item->allergens,
            'additives' => $item->additives,
            'categories' => $item->categories,
            'classification' => $item->classification,
            'environment' => $item->environment,
        ];
    }

    /** @param array<string, mixed> $globalSettings */
    public function getMenuService(array $globalSettings = []): MenuService
    {
        $config = $this->runtimeConfig($globalSettings);
        if ($globalSettings !== []) {
            $parser = new MensaXmlParser($config);
            $repository = new MenuRepository($parser, $config);
            return new MenuService($repository, $config);
        }

        if ($this->service === null) {
            $parser = new MensaXmlParser($config);
            $repository = new MenuRepository($parser, $config);
            $this->service = new MenuService($repository, $config);
        }
        return $this->service;
    }

    /** @param array<string, mixed> $globalSettings */
    private function refreshMenuXmlCache(array $globalSettings, PluginApi $api): void
    {
        $config = $this->runtimeConfig($globalSettings);
        $parser = new MensaXmlParser($config);
        $repository = new MenuRepository($parser, $config, $api->pluginCachePath($this->getName(), 'speiseplan.xml'));

        try {
            $repository->refreshCache(false);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'write XML cache file') || str_contains($message, 'cache directory')) {
                throw new RuntimeException(__('plugins.tl1-menu.errors.setup_cache_failed'), 0, $e);
            }
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_download_failed'), 0, $e);
        }
    }

    /** @param array<string, mixed> $globalSettings @return array<string, mixed> */
    private function runtimeConfig(array $globalSettings = []): array
    {
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $globalSettings);
        $parserConfig = is_array($globalSettings['parser_config'] ?? null) ? $globalSettings['parser_config'] : [];
        $config = array_replace($this->runtimeSafeDefaults(), $parserConfig);
        foreach ([
            'menu_url', 'cache_ttl', 'debug_date', 'default_language', 'default_mensa', 'default_exclude',
            'default_display_co2', 'default_display_water', 'default_display_animal_welfare', 'default_display_rainforest',
            'default_show_header', 'environment_rating_icons',
        ] as $key) {
            if (array_key_exists($key, $globalSettings)) {
                $config[$key] = $globalSettings[$key];
            }
        }
        $config['default_background_color'] = $globalSettings['background_color'] ?? '#f1f5f9';
        $config['default_environment_display_style'] = $globalSettings['environment_display_style'] ?? 'symbols';
        return $config;
    }

    /** @return array<string, mixed> */
    private function runtimeSafeDefaults(): array
    {
        return [
            'schema_version' => 2,
            'field_definitions' => [],
            'field_mapping' => [],
            'price_groups' => [],
            'mensen' => [],
            'standort_namen' => [],
            'food_types' => [],
            'categories' => [],
            'token_catalog' => [],
            'setup' => [
                'source_url' => '',
                'generated_at' => null,
            ],
        ];
    }

    /** @param array<string, mixed> $config */
    private function assertGeneratedConfig(array $config): void
    {
        if ((int)($config['schema_version'] ?? 0) !== 2) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.config_invalid_generated'));
        }
        foreach (['field_definitions', 'field_mapping', 'price_groups', 'mensen', 'food_types', 'categories', 'token_catalog'] as $key) {
            if (!is_array($config[$key] ?? null)) {
                throw new RuntimeException(__('plugins.tl1-menu.errors.config_invalid_generated'));
            }
        }
    }

    /** @return array<int, array{key: string, setting: string, label: string, value: string, rating: string}> */
    public function getEnvironmentPreviewMetrics(string $language = 'de'): array
    {
        $language = in_array($language, ['de', 'en'], true) ? $language : 'de';
        $service = $this->getMenuService();

        return [
            [
                'key' => 'co2',
                'setting' => 'display_co2',
                'label' => __('plugins.tl1-menu.frontend.co2'),
                'value' => $service->formatEnvironmentalValue(620.0, 'co2', $language) ?? '620',
                'rating' => 'B',
            ],
            [
                'key' => 'water',
                'setting' => 'display_water',
                'label' => __('plugins.tl1-menu.frontend.water'),
                'value' => $service->formatEnvironmentalValue(1.8, 'water', $language) ?? '1.80',
                'rating' => 'C',
            ],
            [
                'key' => 'animal_welfare',
                'setting' => 'display_animal_welfare',
                'label' => __('plugins.tl1-menu.frontend.animal_welfare'),
                'value' => '-',
                'rating' => 'A',
            ],
            [
                'key' => 'rainforest',
                'setting' => 'display_rainforest',
                'label' => __('plugins.tl1-menu.frontend.rainforest'),
                'value' => '-',
                'rating' => 'B',
            ],
        ];
    }

    /** @param array<string, mixed> $globalSettings */
    private function resolveEffectiveDate(array $globalSettings = []): string
    {
        $config = $this->runtimeConfig($globalSettings);
        $debugDate = trim((string)($config['debug_date'] ?? ''));
        if ($debugDate !== '') {
            $timestamp = strtotime($debugDate);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        return date('Y-m-d');
    }

    private function normalizeLanguage(mixed $value): string
    {
        $language = strtolower(trim((string)$value));
        return in_array($language, ['de', 'en'], true) ? $language : 'de';
    }

    /** @return list<int> */
    private function normalizeIntCsv(mixed $value): array
    {
        $rawValues = is_array($value) ? $value : preg_split('/\s*,\s*/', (string)$value);
        $ids = [];
        foreach ($rawValues ?: [] as $rawValue) {
            $rawValue = trim((string)$rawValue);
            if ($rawValue !== '' && is_numeric($rawValue)) {
                $ids[] = (int)$rawValue;
            }
        }
        sort($ids);
        return array_values(array_unique($ids));
    }

    /** @return array<string, string> */
    private function normalizeEnvironmentIconInput(mixed $value): array
    {
        $defaults = is_array($this->getDefaultGlobalSettings()['environment_rating_icons'] ?? null)
            ? $this->getDefaultGlobalSettings()['environment_rating_icons']
            : [];
        $input = is_array($value) ? $value : [];
        $icons = [];
        foreach (['co2', 'water', 'animal_welfare', 'rainforest'] as $key) {
            $icon = trim((string)($input[$key] ?? $defaults[$key] ?? ''));
            if ($icon !== '') {
                $icons[$key] = $icon;
            }
        }
        return $icons;
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
        $globalSettings = array_replace($this->getDefaultGlobalSettings(), $api->loadGlobalSettings($this->getName()));
        $configured = is_array($globalSettings['environment_rating_icons'] ?? null) ? $globalSettings['environment_rating_icons'] : [];
        $map = [
            'leaf' => 'co2',
            'drop' => 'water',
            'heart' => 'animal_welfare',
            'tree' => 'rainforest',
        ];
        $assets = [];
        foreach ($map as $icon => $settingKey) {
            $configuredPath = trim((string)($configured[$settingKey] ?? ''));
            $assets[$icon] = $this->resolveEnvironmentalIconPair($api, $icon, $configuredPath);
        }
        return $assets;
    }

    /** @return list<array{path: string, label: string, url: string}> */
    private function getCategoryIconChoices(PluginApi $api): array
    {
        $directory = __DIR__ . '/assets/img/categories';
        $files = is_dir($directory) ? glob($directory . '/*') : false;
        if (!is_array($files)) {
            return [];
        }

        $choices = [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($extension, ['png', 'webp', 'svg'], true)) {
                continue;
            }

            $filename = basename($file);
            $path = 'assets/img/categories/' . $filename;
            $choices[] = [
                'path' => $path,
                'label' => $filename,
                'url' => $api->pluginAssetUrl($this->getName(), $path),
            ];
        }

        usort($choices, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return $choices;
    }

    /** @param array<string, mixed>|null $file @return array{uploaded: array{path: string, label: string, url: string}, category_icons: list<array{path: string, label: string, url: string}>} */
    private function storeCategoryIconUpload(?array $file, PluginApi $api): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_missing'));
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_invalid_upload'));
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_invalid_upload'));
        }

        $filename = $this->normalizeCategoryIconFilename((string)($file['name'] ?? ''));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $this->assertUploadedCategoryIcon($tmpName, $extension);

        $directory = __DIR__ . '/assets/img/categories';
        if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_not_writable'));
        }
        if (!is_writable($directory)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_not_writable'));
        }

        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_save_failed'));
        }
        @chmod($destination, 0644);

        $path = 'assets/img/categories/' . $filename;
        return [
            'uploaded' => [
                'path' => $path,
                'label' => $filename,
                'url' => $api->pluginAssetUrl($this->getName(), $path),
            ],
            'category_icons' => $this->getCategoryIconChoices($api),
        ];
    }

    private function normalizeCategoryIconFilename(string $name): string
    {
        $filename = basename(str_replace('\\', '/', trim($name)));
        if (
            $filename === ''
            || $filename === '.'
            || $filename === '..'
            || str_starts_with($filename, '.')
            || preg_match('/[\x00-\x1f\x7f]/', $filename) === 1
        ) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_invalid_filename'));
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['svg', 'png', 'webp'], true)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_invalid_type'));
        }

        return $filename;
    }

    private function assertUploadedCategoryIcon(string $tmpName, string $extension): void
    {
        if ($extension === 'svg') {
            $dimensions = $this->readSvgDimensions($tmpName);
            if ($dimensions === null || !$this->isSquareRatio($dimensions['width'], $dimensions['height'])) {
                throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_not_square'));
            }
            return;
        }

        $imageSize = @getimagesize($tmpName);
        if (!is_array($imageSize)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_invalid_type'));
        }

        $expectedType = $extension === 'png' ? IMAGETYPE_PNG : IMAGETYPE_WEBP;
        if ((int)($imageSize[2] ?? 0) !== $expectedType) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_invalid_type'));
        }

        if (!$this->isSquareRatio((float)($imageSize[0] ?? 0), (float)($imageSize[1] ?? 0))) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_not_square'));
        }
    }

    /** @return array{width: float, height: float}|null */
    private function readSvgDimensions(string $tmpName): ?array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->load($tmpName, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$dom->documentElement || strtolower($dom->documentElement->localName) !== 'svg') {
            throw new RuntimeException(__('plugins.tl1-menu.errors.category_icon_invalid_type'));
        }

        $width = $this->parseSvgLength($dom->documentElement->getAttribute('width'));
        $height = $this->parseSvgLength($dom->documentElement->getAttribute('height'));
        if ($width !== null && $height !== null) {
            return ['width' => $width, 'height' => $height];
        }

        $viewBox = trim($dom->documentElement->getAttribute('viewBox'));
        if ($viewBox === '') {
            return null;
        }

        $parts = preg_split('/[\s,]+/', $viewBox, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || count($parts) !== 4) {
            return null;
        }

        return ['width' => (float)$parts[2], 'height' => (float)$parts[3]];
    }

    private function parseSvgLength(string $value): ?float
    {
        $value = trim($value);
        if ($value === '' || str_ends_with($value, '%')) {
            return null;
        }

        if (preg_match('/\A([0-9]+(?:\.[0-9]+)?)/', $value, $matches) !== 1) {
            return null;
        }

        return (float)$matches[1];
    }

    private function isSquareRatio(float $width, float $height): bool
    {
        return $width > 0 && $height > 0 && abs($width - $height) <= 0.01;
    }

    /** @return array{filled: string, outline: string} */
    private function resolveEnvironmentalIconPair(PluginApi $api, string $icon, string $configuredPath): array
    {
        $defaultPaths = $this->defaultEnvironmentalIconPaths($icon);
        $normalizedPath = ltrim(str_replace('\\', '/', $configuredPath), '/');
        $pathOnly = explode('#', $normalizedPath, 2)[0];

        if (
            $configuredPath === ''
            || $pathOnly === $defaultPaths['filled']
            || $pathOnly === $defaultPaths['outline']
            || $this->isLegacyEnvironmentalIconPath($icon, $normalizedPath)
        ) {
            return [
                'filled' => $api->pluginAssetUrl($this->getName(), $defaultPaths['filled']),
                'outline' => $api->pluginAssetUrl($this->getName(), $defaultPaths['outline']),
            ];
        }

        $url = $this->resolvePluginAssetPath($api, $configuredPath);
        return ['filled' => $url, 'outline' => $url];
    }

    /** @return array{filled: string, outline: string} */
    private function defaultEnvironmentalIconPaths(string $icon): array
    {
        return [
            'filled' => 'assets/img/environment/' . $icon . '-filled.svg',
            'outline' => 'assets/img/environment/' . $icon . '-outline.svg',
        ];
    }

    private function isLegacyEnvironmentalIconPath(string $icon, string $path): bool
    {
        $legacyPaths = [
            'leaf' => ['assets/img/eco-leaf.svg', 'assets/img/leaf-filled.svg', 'assets/img/leaf-outline.svg'],
            'drop' => ['assets/img/eco-drop.svg', 'assets/img/drop-filled.svg', 'assets/img/drop-outline.svg'],
            'heart' => ['assets/img/eco-heart.svg', 'assets/img/heart-filled.svg', 'assets/img/heart-outline.svg'],
            'tree' => ['assets/img/eco-tree.svg', 'assets/img/tree-filled.svg', 'assets/img/tree-outline.svg'],
        ];
        $pathOnly = explode('#', $path, 2)[0];
        return in_array($pathOnly, $legacyPaths[$icon] ?? [], true);
    }

    private function resolvePluginAssetPath(PluginApi $api, string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }
        return $api->pluginAssetUrl($this->getName(), $path);
    }

    /** @return array{type: string, value: string} */
    public function categoryIconDisplayData(string $icon, PluginApi $api): array
    {
        $icon = trim($icon);
        if ($icon === '') {
            $icon = 'assets/img/categories/default.svg';
        }

        $pathOnly = explode('#', str_replace('\\', '/', $icon), 2)[0];
        $extension = strtolower(pathinfo($pathOnly, PATHINFO_EXTENSION));
        if (in_array($extension, ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif'], true) || str_contains($pathOnly, '/')) {
            return ['type' => 'image', 'value' => $this->resolvePluginAssetPath($api, $icon)];
        }

        return ['type' => 'text', 'value' => $icon];
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
            throw new RuntimeException(__('plugins.tl1-menu.errors.background_invalid_type'));
        }
    }
}
