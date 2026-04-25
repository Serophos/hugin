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
            'display_co2' => (bool)($config['default_display_co2'] ?? true),
            'display_water' => (bool)($config['default_display_water'] ?? false),
            'display_animal_welfare' => (bool)($config['default_display_animal_welfare'] ?? false),
            'display_rainforest' => (bool)($config['default_display_rainforest'] ?? false),
            'show_header' => (bool)($config['default_show_header'] ?? true),
            'background_color' => (string)($config['default_background_color'] ?? '#f1f5f9'),
            'background_image' => '',
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
            'backgroundImageUrl' => $this->getBackgroundImageUrl((string)($settings['background_image'] ?? ''), $api),
        ]);
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

        $backgroundColor = $this->normalizeColor((string)($input['background_color'] ?? $defaults['background_color']));
        $backgroundImage = (string)($existingSettings['background_image'] ?? '');
        if (!empty($input['remove_background_image'])) {
            $this->deleteBackgroundImage($backgroundImage);
            $backgroundImage = '';
        }
        $uploadedBackground = $_FILES['plugin_settings']['name'][$this->getName()]['background_image_file'] ?? null;
        if ($uploadedBackground !== null) {
            $backgroundImage = $this->storeBackgroundImageFromRequest($backgroundImage);
        }

        return [
            'mensa' => $mensa,
            'language' => $language,
            'exclude_types' => $excludeTypes,
            'display_co2' => !empty($input['display_co2']),
            'display_water' => !empty($input['display_water']),
            'display_animal_welfare' => !empty($input['display_animal_welfare']),
            'display_rainforest' => !empty($input['display_rainforest']),
            'show_header' => !empty($input['show_header']),
            'background_color' => $backgroundColor,
            'background_image' => $backgroundImage,
        ];
    }

    public function renderFrontend(array $slide, array $settings, PluginApi $api): string
    {
        $settings = array_replace($this->getDefaultSettings(), $settings);
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
            'backgroundImageUrl' => $this->getBackgroundImageUrl((string)($settings['background_image'] ?? ''), $api),
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
            'display_co2' => (bool)$settings['display_co2'],
            'display_water' => (bool)$settings['display_water'],
            'display_animal_welfare' => (bool)$settings['display_animal_welfare'],
            'display_rainforest' => (bool)$settings['display_rainforest'],
            'show_header' => (bool)$settings['show_header'],
            'background_color' => (string)$settings['background_color'],
            'background_image' => (string)$settings['background_image'],
        ];
    }

    /** @return array<string, mixed> */
    private function getPluginConfig(): array
    {
        if ($this->config === null) {
            $loaded = require __DIR__ . '/config.php';
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
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
            return strtolower($value);
        }

        return (string)($this->getPluginConfig()['default_background_color'] ?? '#f1f5f9');
    }

    private function getBackgroundImageUrl(string $relativePath, PluginApi $api): ?string
    {
        $relativePath = ltrim(trim($relativePath), '/');
        if ($relativePath === '') {
            return null;
        }

        return $api->pluginAssetUrl($this->getName(), $relativePath);
    }

    private function storeBackgroundImageFromRequest(string $existingPath = ''): string
    {
        $file = [
            'name' => $_FILES['plugin_settings']['name'][$this->getName()]['background_image_file'] ?? '',
            'type' => $_FILES['plugin_settings']['type'][$this->getName()]['background_image_file'] ?? '',
            'tmp_name' => $_FILES['plugin_settings']['tmp_name'][$this->getName()]['background_image_file'] ?? '',
            'error' => $_FILES['plugin_settings']['error'][$this->getName()]['background_image_file'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES['plugin_settings']['size'][$this->getName()]['background_image_file'] ?? 0,
        ];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $existingPath;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(__('plugins.tl-1menu.errors.background_upload_failed'));
        }
        if (!is_uploaded_file((string)$file['tmp_name'])) {
            throw new RuntimeException(__('plugins.tl-1menu.errors.background_upload_failed'));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = is_resource($finfo) ? (string)finfo_file($finfo, (string)$file['tmp_name']) : '';
        if (is_resource($finfo)) {
            finfo_close($finfo);
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mimeType])) {
            throw new RuntimeException(__('plugins.tl-1menu.errors.background_invalid_type'));
        }

        $targetDir = __DIR__ . '/assets/uploads';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException(__('plugins.tl-1menu.errors.background_upload_failed'));
        }

        $filename = 'bg-' . bin2hex(random_bytes(12)) . '.' . $allowed[$mimeType];
        $targetFile = $targetDir . '/' . $filename;
        if (!move_uploaded_file((string)$file['tmp_name'], $targetFile)) {
            throw new RuntimeException(__('plugins.tl-1menu.errors.background_upload_failed'));
        }

        $this->deleteBackgroundImage($existingPath);
        return 'assets/uploads/' . $filename;
    }

    private function deleteBackgroundImage(string $relativePath): void
    {
        $relativePath = ltrim(trim($relativePath), '/');
        if ($relativePath === '' || !str_starts_with($relativePath, 'assets/uploads/')) {
            return;
        }

        $absolutePath = __DIR__ . '/' . $relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
