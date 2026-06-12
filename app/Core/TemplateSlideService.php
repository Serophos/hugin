<?php
namespace App\Core;

use RuntimeException;

class TemplateSlideService
{
    private const SPEC_VERSION = 1;
    private const FIELD_TYPES = ['text', 'multiline', 'url', 'media_image', 'media_video', 'qr_url', 'color'];
    private const ELEMENT_TYPES = ['background', 'text', 'media', 'qr', 'shape'];
    private const ENTRANCE_ANIMATIONS = ['none', 'fade-in', 'fade-up', 'fade-down', 'slide-left', 'slide-right', 'zoom-in', 'pop-in', 'blur-in'];
    private const CONTINUOUS_ANIMATIONS = ['none', 'pulse', 'float', 'slow-zoom', 'wiggle', 'glow', 'rotate-slow'];
    private const ANIMATION_EASINGS = ['ease', 'ease-out', 'ease-in-out', 'linear'];
    private const ANIMATION_DIRECTIONS = ['normal', 'alternate', 'reverse'];

    public function __construct(private Database $db)
    {
    }

    public function emptySpec(string $orientation = 'landscape'): array
    {
        $isPortrait = $orientation === 'portrait';

        return [
            'version' => self::SPEC_VERSION,
            'canvas' => [
                'width' => $isPortrait ? 1080 : 1920,
                'height' => $isPortrait ? 1920 : 1080,
            ],
            'fields' => [
                [
                    'key' => 'headline',
                    'label' => __('templates.default_headline_field', [], 'Headline'),
                    'type' => 'text',
                    'required' => false,
                    'default' => '',
                ],
            ],
            'elements' => [
                [
                    'id' => 'background',
                    'type' => 'background',
                    'x' => 0,
                    'y' => 0,
                    'w' => 1,
                    'h' => 1,
                    'z' => 0,
                    'style' => [
                        'backgroundColor' => '#0f172a',
                    ],
                ],
                [
                    'id' => 'headline',
                    'type' => 'text',
                    'field' => 'headline',
                    'x' => 0.12,
                    'y' => 0.36,
                    'w' => 0.76,
                    'h' => 0.22,
                    'z' => 10,
                    'style' => [
                        'color' => '#ffffff',
                        'backgroundColor' => 'rgba(15, 23, 42, 0.52)',
                        'fontSize' => 8,
                        'fontWeight' => '700',
                        'align' => 'center',
                        'radius' => 2,
                    ],
                ],
            ],
        ];
    }

    public function decodeSpec(?string $json, string $orientation = 'landscape'): array
    {
        if (!is_string($json) || trim($json) === '') {
            return $this->emptySpec($orientation);
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $this->emptySpec($orientation);
        }

        return $this->normalizeSpec($decoded, $orientation);
    }

    public function encodeSpec(array $spec, string $orientation = 'landscape'): string
    {
        $normalized = $this->normalizeSpec($spec, $orientation);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new RuntimeException(__('templates.invalid_spec'));
        }

        return $json;
    }

    public function normalizeSpec(array $spec, string $orientation = 'landscape'): array
    {
        $isPortrait = $orientation === 'portrait';
        $canvas = is_array($spec['canvas'] ?? null) ? $spec['canvas'] : [];
        $width = $this->intRange($canvas['width'] ?? ($isPortrait ? 1080 : 1920), $isPortrait ? 1080 : 1920, 320, 7680);
        $height = $this->intRange($canvas['height'] ?? ($isPortrait ? 1920 : 1080), $isPortrait ? 1920 : 1080, 320, 7680);
        $fields = $this->normalizeFields($spec['fields'] ?? []);
        $fieldKeys = array_fill_keys(array_map(static fn(array $field): string => (string)$field['key'], $fields), true);
        $elements = $this->normalizeElements($spec['elements'] ?? [], $fieldKeys);

        if ($elements === []) {
            $elements = $this->emptySpec($orientation)['elements'];
        }

        return [
            'version' => self::SPEC_VERSION,
            'canvas' => [
                'width' => $width,
                'height' => $height,
            ],
            'fields' => $fields,
            'elements' => $elements,
        ];
    }

    public function decodeValues(?string $json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function encodeValues(array $values): string
    {
        $json = json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : '{}';
    }

    public function normalizeValues(array $template, array $input, array $existingValues = []): array
    {
        $spec = $this->decodeSpec((string)($template['landscape_spec_json'] ?? ''), 'landscape');
        $portrait = trim((string)($template['portrait_spec_json'] ?? '')) !== ''
            ? $this->decodeSpec((string)$template['portrait_spec_json'], 'portrait')
            : null;
        $fields = $this->fieldsForSpecs($spec, $portrait);
        $values = [];

        foreach ($fields as $field) {
            $key = (string)$field['key'];
            $type = (string)$field['type'];
            $raw = array_key_exists($key, $input) ? $input[$key] : ($existingValues[$key] ?? ($field['default'] ?? ''));

            if ($type === 'media_image' || $type === 'media_video') {
                $assetId = trim((string)$raw) === '' ? null : (int)$raw;
                if ($assetId !== null && $assetId > 0) {
                    $asset = $this->db->one('SELECT id, media_kind FROM media_assets WHERE id = ?', [$assetId]);
                    $expected = $type === 'media_image' ? 'image' : 'video';
                    if (!$asset || ($asset['media_kind'] ?? '') !== $expected) {
                        throw new RuntimeException(__('templates.invalid_media_value'));
                    }
                    $values[$key] = $assetId;
                } else {
                    $values[$key] = null;
                }
            } elseif ($type === 'url' || $type === 'qr_url') {
                $url = trim((string)$raw);
                if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException(__('templates.invalid_url_value'));
                }
                $values[$key] = substr($url, 0, 1024);
            } elseif ($type === 'color') {
                $values[$key] = normalize_css_rgba_color((string)$raw, (string)($field['default'] ?? 'rgba(255, 255, 255, 1)'));
            } else {
                $values[$key] = substr(trim((string)$raw), 0, $type === 'multiline' ? 4000 : 500);
            }

            if (!empty($field['required'])) {
                $value = $values[$key] ?? null;
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    throw new RuntimeException(__('templates.required_value_missing', ['field' => (string)$field['label']]));
                }
            }
        }

        return $values;
    }

    public function fieldsForTemplate(array $template): array
    {
        $landscape = $this->decodeSpec((string)($template['landscape_spec_json'] ?? ''), 'landscape');
        $portrait = trim((string)($template['portrait_spec_json'] ?? '')) !== ''
            ? $this->decodeSpec((string)$template['portrait_spec_json'], 'portrait')
            : null;

        return $this->fieldsForSpecs($landscape, $portrait);
    }

    public function render(array $template, array $values, string $orientation = 'landscape'): string
    {
        $landscape = $this->decodeSpec((string)($template['landscape_spec_json'] ?? ''), 'landscape');
        $portraitJson = trim((string)($template['portrait_spec_json'] ?? ''));
        $spec = $orientation === 'vertical' && $portraitJson !== ''
            ? $this->decodeSpec($portraitJson, 'portrait')
            : $landscape;

        $canvas = $spec['canvas'];
        $ratio = max(0.1, ((int)$canvas['width']) / max(1, (int)$canvas['height']));
        $fields = $this->fieldsForSpecs($spec, null);
        $fieldMap = [];
        foreach ($fields as $field) {
            $fieldMap[(string)$field['key']] = $field;
        }

        $html = '<div class="template-slide" style="--template-stage-ratio: ' . e((string)$ratio) . ';">';
        $html .= '<div class="template-slide__stage">';

        $elements = $spec['elements'];
        usort($elements, static fn(array $a, array $b): int => ((int)($a['z'] ?? 0)) <=> ((int)($b['z'] ?? 0)));
        foreach ($elements as $element) {
            $html .= $this->renderElement($element, $values, $fieldMap);
        }

        $html .= '</div></div>';
        return $html;
    }

    public function mediaAssetIdsForTemplateSlide(array $template, array $values): array
    {
        $ids = [];
        foreach (['landscape', 'portrait'] as $orientation) {
            $json = $orientation === 'portrait' ? (string)($template['portrait_spec_json'] ?? '') : (string)($template['landscape_spec_json'] ?? '');
            if (trim($json) === '' && $orientation === 'portrait') {
                continue;
            }
            $spec = $this->decodeSpec($json, $orientation);
            foreach ($spec['elements'] as $element) {
                $style = is_array($element['style'] ?? null) ? $element['style'] : [];
                foreach (['mediaAssetId', 'backgroundMediaAssetId'] as $key) {
                    $id = (int)($style[$key] ?? 0);
                    if ($id > 0) {
                        $ids[$id] = true;
                    }
                }
                $field = (string)($element['field'] ?? '');
                if ($field !== '') {
                    $id = (int)($values[$field] ?? 0);
                    if ($id > 0) {
                        $ids[$id] = true;
                    }
                }
            }
        }

        return array_keys($ids);
    }

    private function fieldsForSpecs(array $landscape, ?array $portrait): array
    {
        $fields = [];
        foreach ([$landscape, $portrait] as $spec) {
            if (!is_array($spec)) {
                continue;
            }
            foreach (($spec['fields'] ?? []) as $field) {
                $key = (string)($field['key'] ?? '');
                if ($key !== '' && !isset($fields[$key])) {
                    $fields[$key] = $field;
                }
            }
        }

        return array_values($fields);
    }

    private function normalizeFields(mixed $fields): array
    {
        $normalized = [];
        $seen = [];
        foreach (is_array($fields) ? $fields : [] as $field) {
            if (!is_array($field)) {
                continue;
            }
            $key = preg_replace('/[^a-zA-Z0-9_]+/', '_', strtolower((string)($field['key'] ?? ''))) ?: '';
            $key = trim($key, '_');
            if ($key === '') {
                continue;
            }
            if (isset($seen[$key])) {
                throw new RuntimeException(__('templates.duplicate_field_key'));
            }
            $type = (string)($field['type'] ?? 'text');
            if (!in_array($type, self::FIELD_TYPES, true)) {
                $type = 'text';
            }
            $seen[$key] = true;
            $normalized[] = [
                'key' => $key,
                'label' => substr(trim((string)($field['label'] ?? $key)), 0, 120) ?: $key,
                'type' => $type,
                'required' => !empty($field['required']),
                'default' => is_scalar($field['default'] ?? null) ? (string)$field['default'] : '',
            ];
        }

        return $normalized;
    }

    private function normalizeElements(mixed $elements, array $fieldKeys): array
    {
        $normalized = [];
        foreach (is_array($elements) ? $elements : [] as $index => $element) {
            if (!is_array($element)) {
                continue;
            }
            $type = (string)($element['type'] ?? 'text');
            if (!in_array($type, self::ELEMENT_TYPES, true)) {
                throw new RuntimeException(__('templates.invalid_spec'));
            }
            $field = (string)($element['field'] ?? '');
            if ($field !== '' && !isset($fieldKeys[$field])) {
                $field = '';
            }

            foreach (['x', 'y', 'w', 'h'] as $coordinateKey) {
                if (array_key_exists($coordinateKey, $element) && !$this->isCoordinateInCanvas($element[$coordinateKey])) {
                    throw new RuntimeException(__('templates.invalid_coordinates'));
                }
            }

            $normalizedElement = [
                'id' => preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)($element['id'] ?? ('element-' . $index))) ?: ('element-' . $index),
                'type' => $type,
                'field' => $field,
                'x' => $this->floatRange($element['x'] ?? 0.1, 0.1, 0, 1),
                'y' => $this->floatRange($element['y'] ?? 0.1, 0.1, 0, 1),
                'w' => $this->floatRange($element['w'] ?? 0.3, 0.3, 0.02, 1),
                'h' => $this->floatRange($element['h'] ?? 0.2, 0.2, 0.02, 1),
                'z' => $this->intRange($element['z'] ?? $index, $index, 0, 999),
                'style' => $this->normalizeStyle(is_array($element['style'] ?? null) ? $element['style'] : []),
            ];

            if ($type !== 'background') {
                $normalizedElement['animation'] = $this->normalizeAnimation(is_array($element['animation'] ?? null) ? $element['animation'] : []);
            }

            $normalized[] = $normalizedElement;
        }

        return $normalized;
    }

    private function normalizeStyle(array $style): array
    {
        $out = [];
        foreach (['color', 'backgroundColor', 'borderColor'] as $key) {
            if (isset($style[$key]) && is_scalar($style[$key])) {
                $out[$key] = substr(trim((string)$style[$key]), 0, 40);
            }
        }
        foreach (['fontWeight', 'align'] as $key) {
            if (isset($style[$key]) && is_scalar($style[$key])) {
                $out[$key] = substr(trim((string)$style[$key]), 0, 24);
            }
        }
        if (isset($style['fit']) && is_scalar($style['fit'])) {
            $fit = substr(trim((string)$style['fit']), 0, 24);
            $out['fit'] = in_array($fit, ['cover', 'contain', 'contain-blur'], true) ? $fit : 'cover';
        }
        foreach (['fontSize', 'radius', 'opacity', 'borderWidth'] as $key) {
            if (isset($style[$key])) {
                $out[$key] = $this->floatRange($style[$key], 0, 0, 100);
            }
        }
        foreach (['mediaAssetId', 'backgroundMediaAssetId'] as $key) {
            if (isset($style[$key])) {
                $out[$key] = max(0, (int)$style[$key]);
            }
        }

        return $out;
    }

    private function normalizeAnimation(array $animation): array
    {
        $entrance = (string)($animation['entrance'] ?? 'none');
        if (!in_array($entrance, self::ENTRANCE_ANIMATIONS, true)) {
            $entrance = 'none';
        }

        $continuous = (string)($animation['continuous'] ?? 'none');
        if (!in_array($continuous, self::CONTINUOUS_ANIMATIONS, true)) {
            $continuous = 'none';
        }

        $easing = (string)($animation['easing'] ?? 'ease-out');
        if (!in_array($easing, self::ANIMATION_EASINGS, true)) {
            $easing = 'ease-out';
        }

        $direction = (string)($animation['direction'] ?? 'normal');
        if (!in_array($direction, self::ANIMATION_DIRECTIONS, true)) {
            $direction = 'normal';
        }

        return [
            'entrance' => $entrance,
            'continuous' => $continuous,
            'entranceDelayMs' => $this->intRange($animation['entranceDelayMs'] ?? 0, 0, 0, 30000),
            'entranceDurationMs' => $this->intRange($animation['entranceDurationMs'] ?? 600, 600, 100, 10000),
            'continuousDurationMs' => $this->intRange($animation['continuousDurationMs'] ?? 3000, 3000, 500, 30000),
            'easing' => $easing,
            'direction' => $direction,
        ];
    }

    private function renderElement(array $element, array $values, array $fieldMap): string
    {
        $type = (string)$element['type'];
        $style = is_array($element['style'] ?? null) ? $element['style'] : [];
        $field = (string)($element['field'] ?? '');
        $value = $field !== '' ? ($values[$field] ?? ($fieldMap[$field]['default'] ?? '')) : '';
        $classes = 'template-slide__element template-slide__element--' . $type . $this->animationClasses($element);
        $styleAttr = $this->elementStyle($element, $style);

        if ($type === 'background') {
            return '<div class="' . e($classes) . '" style="' . e($styleAttr) . '">' . $this->renderMediaById((int)($style['backgroundMediaAssetId'] ?? 0), (string)($style['fit'] ?? 'cover')) . '</div>';
        }
        if ($type === 'media') {
            $assetId = (int)($style['mediaAssetId'] ?? 0);
            if ($field !== '') {
                $assetId = (int)$value;
            }
            return '<div class="' . e($classes) . '" style="' . e($styleAttr) . '">' . $this->renderMediaById($assetId, (string)($style['fit'] ?? 'cover')) . '</div>';
        }
        if ($type === 'qr') {
            $qrValue = trim((string)$value);
            if ($qrValue === '') {
                return '';
            }
            return '<div class="' . e($classes) . '" style="' . e($styleAttr) . '" data-qr-url="' . e($qrValue) . '" data-qr-foreground="' . e((string)($style['color'] ?? 'rgba(15, 23, 42, 1)')) . '" data-qr-background="' . e((string)($style['backgroundColor'] ?? 'rgba(255, 255, 255, 1)')) . '"><canvas class="template-slide__qr-canvas" width="1" height="1"></canvas><div class="template-slide__qr-fallback">' . e($qrValue) . '</div></div>';
        }
        if ($type === 'shape') {
            return '<div class="' . e($classes) . '" style="' . e($styleAttr) . '"></div>';
        }

        $content = (string)$value;
        $fieldType = (string)($fieldMap[$field]['type'] ?? 'text');
        $html = $fieldType === 'multiline' ? nl2br(e($content)) : e($content);
        if ($html === '') {
            $html = '&nbsp;';
        }

        return '<div class="' . e($classes) . '" style="' . e($styleAttr) . '"><div class="template-slide__text-content">' . $html . '</div></div>';
    }

    private function animationClasses(array $element): string
    {
        if (($element['type'] ?? '') === 'background' || !is_array($element['animation'] ?? null)) {
            return '';
        }

        $animation = $this->normalizeAnimation($element['animation']);
        $classes = [];
        if ($animation['entrance'] !== 'none') {
            $classes[] = 'template-slide__element--entrance-' . $animation['entrance'];
        }
        if ($animation['continuous'] !== 'none') {
            $classes[] = 'template-slide__element--continuous-' . $animation['continuous'];
        }

        return $classes === [] ? '' : ' ' . implode(' ', $classes);
    }

    private function elementStyle(array $element, array $style): string
    {
        $css = [
            'left:' . (((float)$element['x']) * 100) . '%',
            'top:' . (((float)$element['y']) * 100) . '%',
            'width:' . (((float)$element['w']) * 100) . '%',
            'height:' . (((float)$element['h']) * 100) . '%',
            'z-index:' . (int)$element['z'],
        ];
        if (!empty($style['color'])) {
            $css[] = 'color:' . (string)$style['color'];
        }
        if (!empty($style['backgroundColor']) && ($element['type'] ?? '') !== 'qr') {
            $css[] = 'background:' . (string)$style['backgroundColor'];
        }
        if (isset($style['opacity'])) {
            $css[] = 'opacity:' . max(0, min(1, (float)$style['opacity']));
        }
        if (isset($style['radius'])) {
            $css[] = 'border-radius:' . max(0, (float)$style['radius']) . 'cqw';
        }
        if (isset($style['fontSize'])) {
            $css[] = 'font-size:clamp(0.8rem, ' . max(0.5, (float)$style['fontSize']) . 'cqw, 8rem)';
        }
        if (!empty($style['fontWeight'])) {
            $css[] = 'font-weight:' . (string)$style['fontWeight'];
        }
        if (!empty($style['align'])) {
            $css[] = 'text-align:' . (string)$style['align'];
        }
        if (($element['type'] ?? '') !== 'background' && is_array($element['animation'] ?? null)) {
            $animation = $this->normalizeAnimation($element['animation']);
            $css[] = '--template-entrance-delay:' . $animation['entranceDelayMs'] . 'ms';
            $css[] = '--template-entrance-duration:' . $animation['entranceDurationMs'] . 'ms';
            $css[] = '--template-continuous-duration:' . $animation['continuousDurationMs'] . 'ms';
            $css[] = '--template-animation-easing:' . $animation['easing'];
            $css[] = '--template-animation-direction:' . $animation['direction'];
        }

        return implode(';', $css) . ';';
    }

    private function renderMediaById(int $assetId, string $fit): string
    {
        if ($assetId <= 0) {
            return '';
        }

        $asset = $this->db->one('SELECT file_path, media_kind, name FROM media_assets WHERE id = ?', [$assetId]);
        if (!$asset || empty($asset['file_path'])) {
            return '';
        }

        $fit = in_array($fit, ['cover', 'contain', 'contain-blur'], true) ? $fit : 'cover';
        if (($asset['media_kind'] ?? '') === 'video') {
            if ($fit === 'contain-blur') {
                $fit = 'contain';
            }
            return '<video class="template-slide__media" data-src="' . e(url((string)$asset['file_path'])) . '" muted playsinline loop preload="metadata" style="object-fit:' . e($fit) . '"></video>';
        }

        if ($fit === 'contain-blur') {
            $url = e(url((string)$asset['file_path']));
            $alt = e((string)($asset['name'] ?? ''));
            return '<span class="template-slide__media-stack"><img class="template-slide__media template-slide__media--blurred" data-src="' . $url . '" alt="" aria-hidden="true" decoding="async"><img class="template-slide__media template-slide__media--contained" data-src="' . $url . '" alt="' . $alt . '" decoding="async"></span>';
        }

        return '<img class="template-slide__media" data-src="' . e(url((string)$asset['file_path'])) . '" alt="' . e((string)($asset['name'] ?? '')) . '" decoding="async" style="object-fit:' . e($fit) . '">';
    }

    private function intRange(mixed $value, int $default, int $min, int $max): int
    {
        $number = $this->numericValue($value);
        if ($number === null) {
            return $default;
        }
        return max($min, min($max, (int)$number));
    }

    private function floatRange(mixed $value, float $default, float $min, float $max): float
    {
        $number = $this->numericValue($value);
        if ($number === null) {
            return $default;
        }
        return round(max($min, min($max, $number)), 4);
    }

    private function isCoordinateInCanvas(mixed $value): bool
    {
        $number = $this->numericValue($value);
        return $number !== null && $number >= 0 && $number <= 1;
    }

    private function numericValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = trim(str_replace(',', '.', $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return (float)$normalized;
    }
}
