<?php

declare(strict_types=1);

namespace Plugins\Tl1Menu\Menu;

use DateTimeImmutable;
use DateTimeInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;

final class MensaXmlParser
{
    /** @var array<string, mixed> */
    private array $config;
    /** @var array<int, string> */
    private array $standortNames = [];
    /** @var array<string, list<int>> */
    private array $mensaLocations = [];
    /** @var list<int> */
    private array $defaultExcludedTypes = [];

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->standortNames = $this->normalizeStandortNames($config['standort_namen'] ?? []);
        $this->mensaLocations = $this->normalizeMensaMappings($config['mensen'] ?? []);
        $this->defaultExcludedTypes = array_map('intval', is_array($config['default_exclude'] ?? null) ? $config['default_exclude'] : []);
    }

    /** @param array<string, mixed> $filters @return list<MenuItem> */
    public function parseFile(string $xmlFile, array $filters = []): array
    {
        if (!is_file($xmlFile) || !is_readable($xmlFile)) {
            throw new InvalidArgumentException('XML file not found or not readable: ' . $xmlFile);
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->load($xmlFile, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new RuntimeException('Could not load XML file: ' . $xmlFile);
        }

        return $this->parseDom($dom, $filters);
    }

    /** @return list<MenuItem> */
    public function getMenuForDay(string $xmlFile, string $mensa, DateTimeInterface|string $date, bool $excludeDefaultTypes = true, string $language = 'de'): array
    {
        return $this->parseFile($xmlFile, [
            'mensa' => $mensa,
            'date' => $date,
            'language' => $language,
            'exclude_types' => $excludeDefaultTypes ? $this->defaultExcludedTypes : [],
            'sort' => true,
        ]);
    }

    /** @param array<string, mixed> $filters @return list<MenuItem> */
    private function parseDom(DOMDocument $dom, array $filters): array
    {
        if ((int)($this->config['schema_version'] ?? 0) !== 2) {
            throw new RuntimeException('TL1 parser configuration has not been generated yet. Open the TL1 Menu plugin settings and run setup.');
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('/DATAPACKET/ROWDATA/ROW');
        if ($nodes === false) {
            throw new RuntimeException('Could not query ROW nodes from XML.');
        }

        $normalizedFilters = $this->normalizeFilters($filters);
        $items = [];

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $item = $this->mapRow($node, (string)$normalizedFilters['language']);
            if ($this->matchesFilters($item, $normalizedFilters)) {
                $items[] = $item;
            }
        }

        if ($normalizedFilters['sort'] === true) {
            usort($items, [$this, 'sortItems']);
        }

        return $items;
    }

    private function mapRow(DOMElement $row, string $language): MenuItem
    {
        $attributes = [];
        foreach ($row->attributes as $attribute) {
            $attributes[$attribute->nodeName] = trim((string)$attribute->nodeValue);
        }

        $mapping = $this->fieldMapping();
        $locationId = $this->toNullableInt($this->mappedValue($attributes, 'location_id'));
        $typeId = $this->toNullableInt($this->mappedValue($attributes, 'type_id'));
        $parsedDate = $this->parseDate($this->mappedValue($attributes, 'date'));
        $tokenInfo = $this->parseTokens($attributes, $language);
        $categories = $this->detectCategories($typeId, $tokenInfo['categories']);
        $classification = $this->classifyDish($categories);
        $locationName = $this->mappedValue($attributes, 'location_name');
        if ($locationName === '' && $locationId !== null) {
            $locationName = $this->standortNames[$locationId] ?? '';
        }

        return MenuItem::fromArray([
            'id' => $this->mappedValue($attributes, 'id'),
            'date' => $parsedDate?->format('Y-m-d') ?? '',
            'mensa_key' => $this->resolveMensaKey($locationId),
            'mensa_name' => $this->mappedValue($attributes, 'mensa_name'),
            'location_id' => $locationId,
            'location_name' => $locationName,
            'type_id' => $typeId,
            'type_name' => $this->resolveTypeName($typeId, $attributes),
            'spalte' => $this->toNullableInt($this->mappedValue($attributes, 'spalte')) ?? 0,
            'title_de' => $this->buildText($attributes, $mapping['title']['de'] ?? []),
            'title_en' => $this->buildText($attributes, $mapping['title']['en'] ?? []),
            'description_de' => $this->buildText($attributes, $mapping['description']['de'] ?? []),
            'description_en' => $this->buildText($attributes, $mapping['description']['en'] ?? []),
            'prices' => $this->prices($attributes),
            'allergens' => $tokenInfo['allergens'],
            'additives' => $tokenInfo['additives'],
            'categories' => $categories,
            'classification' => $classification,
            'environment' => $this->environment($attributes),
            'raw' => $attributes,
        ]);
    }

    /** @return array<string, mixed> */
    private function fieldMapping(): array
    {
        return is_array($this->config['field_mapping'] ?? null) ? $this->config['field_mapping'] : [];
    }

    /** @param array<string,string> $attributes */
    private function mappedValue(array $attributes, string $key): string
    {
        $mapping = $this->fieldMapping();
        $field = trim((string)($mapping[$key] ?? ''));
        return $field !== '' ? trim((string)($attributes[$field] ?? '')) : '';
    }

    /** @param array<string,string> $attributes @param mixed $fields */
    private function buildText(array $attributes, mixed $fields): string
    {
        $lines = [];
        foreach ($this->normalizeStringList($fields) as $field) {
            $value = $this->stripInlineMeta(trim((string)($attributes[$field] ?? '')));
            if ($value !== '') {
                $lines[] = $value;
            }
        }
        return trim(implode(' ', $lines));
    }

    /** @param array<string,string> $attributes @return array<string,float|null> */
    private function prices(array $attributes): array
    {
        $prices = [];
        $groups = is_array($this->config['price_groups'] ?? null) ? $this->config['price_groups'] : [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $key = trim((string)($group['key'] ?? ''));
            $field = trim((string)($group['field'] ?? ''));
            if ($key === '' || $field === '') {
                continue;
            }
            $prices[$key] = $this->parseDecimal((string)($attributes[$field] ?? ''));
        }
        return $prices;
    }

    /** @param array<string,string> $attributes @return array<string,mixed> */
    private function environment(array $attributes): array
    {
        $mapping = $this->fieldMapping();
        $environment = is_array($mapping['environment'] ?? null) ? $mapping['environment'] : [];
        $result = [];
        foreach (['co2_value', 'co2_saving', 'water_value'] as $key) {
            $field = trim((string)($environment[$key] ?? ''));
            $result[$key] = $field !== '' ? $this->parseDecimal((string)($attributes[$field] ?? '')) : null;
        }
        foreach (['co2_rating', 'water_rating', 'animal_welfare', 'rainforest'] as $key) {
            $field = trim((string)($environment[$key] ?? ''));
            $result[$key] = $field !== '' ? $this->normalizeRating((string)($attributes[$field] ?? '')) : null;
        }
        return $result;
    }

    /** @param array<string,string> $attributes @return array{allergens:array<string,string>,additives:array<string,string>,categories:list<string>} */
    private function parseTokens(array $attributes, string $language): array
    {
        $mapping = $this->fieldMapping();
        $catalog = is_array($this->config['token_catalog'] ?? null) ? $this->config['token_catalog'] : [];
        $allergens = [];
        $additives = [];
        $categories = [];

        $codeField = trim((string)($mapping['allergen_codes'] ?? ''));
        $nameFields = is_array($mapping['allergen_names'] ?? null) ? $mapping['allergen_names'] : [];
        $nameField = trim((string)($nameFields[$language] ?? $nameFields['de'] ?? $nameFields['en'] ?? reset($nameFields) ?: ''));
        foreach ($this->pairedTokens((string)($attributes[$codeField] ?? ''), (string)($attributes[$nameField] ?? '')) as $code => $fallbackLabel) {
            $this->assignToken((string)$code, (string)$fallbackLabel, $catalog, $language, $allergens, $additives, $categories);
        }

        foreach ($this->normalizeStringList($mapping['category_token_fields'] ?? []) as $field) {
            foreach ($this->inlineTokens((string)($attributes[$field] ?? '')) as $code) {
                $this->assignToken($code, $code, $catalog, $language, $allergens, $additives, $categories, true);
            }
        }

        return [
            'allergens' => $allergens,
            'additives' => $additives,
            'categories' => array_values(array_unique($categories)),
        ];
    }

    /** @param array<string,mixed> $catalog @param array<string,string> $allergens @param array<string,string> $additives @param list<string> $categories */
    private function assignToken(string $code, string $fallbackLabel, array $catalog, string $language, array &$allergens, array &$additives, array &$categories, bool $inlineOnly = false): void
    {
        $code = trim($code);
        if ($code === '') {
            return;
        }
        $entry = $catalog[$code] ?? $catalog[strtoupper($code)] ?? null;
        $kind = is_array($entry) ? trim((string)($entry['kind'] ?? '')) : '';
        $label = $this->localizedLabel($entry, $language) ?? ($fallbackLabel !== '' ? $fallbackLabel : $code);
        if ($kind === 'ignore') {
            return;
        }
        if ($kind === 'category') {
            $category = trim((string)($entry['category'] ?? ''));
            if ($category !== '') {
                $categories[] = $category;
            }
            return;
        }
        if ($inlineOnly && $kind === '') {
            return;
        }
        if ($kind === 'additive' || ($kind === '' && preg_match('/^\d+[a-z]?$/i', $code) === 1)) {
            $additives[$code] = $label;
            return;
        }
        $allergens[$code] = $label;
    }

    /** @return array<string,string> */
    private function pairedTokens(string $codesRaw, string $namesRaw): array
    {
        $codes = array_map('trim', explode(',', $codesRaw));
        $names = array_map('trim', explode(',', $namesRaw));
        $tokens = [];
        $max = max(count($codes), count($names));
        for ($i = 0; $i < $max; $i++) {
            $code = trim((string)($codes[$i] ?? ''));
            $name = trim((string)($names[$i] ?? ''));
            if ($code === '' && $name === '') {
                continue;
            }
            if ($code === '') {
                $code = $name;
            }
            $tokens[$code] = $name !== '' ? $name : $code;
        }
        return $tokens;
    }

    /** @return list<string> */
    private function inlineTokens(string $value): array
    {
        preg_match_all('/\(([^)]{1,160})\)/u', $value, $matches);
        $tokens = [];
        foreach ($matches[1] ?? [] as $group) {
            foreach (preg_split('/\s*,\s*/', (string)$group) ?: [] as $token) {
                $token = trim($token);
                if ($token !== '' && preg_match('/^[\p{L}\d_-]{1,32}$/u', $token) === 1) {
                    $tokens[] = $token;
                }
            }
        }
        return array_values(array_unique($tokens));
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $locationIds = [];
        if (isset($filters['mensa'])) {
            $locationIds = $this->resolveLocationFilter((string)$filters['mensa']);
        }
        if (isset($filters['location_ids']) && is_array($filters['location_ids'])) {
            foreach ($filters['location_ids'] as $id) {
                if (is_numeric((string)$id)) {
                    $locationIds[] = (int)$id;
                }
            }
        }
        $dates = [];
        if (isset($filters['date'])) {
            $singleDate = $this->normalizeDate($filters['date']);
            if ($singleDate !== null) {
                $dates[] = $singleDate;
            }
        }
        $excludeTypes = [];
        if (isset($filters['exclude_types']) && is_array($filters['exclude_types'])) {
            $excludeTypes = array_values(array_unique(array_map('intval', $filters['exclude_types'])));
        }
        $language = trim((string)($filters['language'] ?? $this->config['default_language'] ?? 'de'));
        return [
            'location_ids' => array_values(array_unique($locationIds)),
            'dates' => array_values(array_unique($dates)),
            'exclude_types' => $excludeTypes,
            'sort' => !empty($filters['sort']),
            'language' => $language !== '' ? $language : 'de',
        ];
    }

    /** @param array<string, mixed> $filters */
    private function matchesFilters(MenuItem $item, array $filters): bool
    {
        if ($filters['location_ids'] !== [] && !in_array((int)$item->locationId, $filters['location_ids'], true)) {
            return false;
        }
        if ($filters['dates'] !== [] && !in_array($item->date, $filters['dates'], true)) {
            return false;
        }
        if ($item->typeId !== null && in_array($item->typeId, $filters['exclude_types'], true)) {
            return false;
        }
        return true;
    }

    private function sortItems(MenuItem $a, MenuItem $b): int
    {
        $aSpalte = $a->spalte > 0 ? $a->spalte : PHP_INT_MAX;
        $bSpalte = $b->spalte > 0 ? $b->spalte : PHP_INT_MAX;
        return [$a->date, $aSpalte, $a->locationId ?? 0, $a->titleDe] <=> [$b->date, $bSpalte, $b->locationId ?? 0, $b->titleDe];
    }

    /** @return array<int, string> */
    private function normalizeStandortNames(mixed $names): array
    {
        $normalized = [];
        if (!is_array($names)) {
            return $normalized;
        }
        foreach ($names as $id => $name) {
            if (is_numeric((string)$id)) {
                $normalized[(int)$id] = trim((string)$name);
            }
        }
        return $normalized;
    }

    /** @return array<string, list<int>> */
    private function normalizeMensaMappings(mixed $mensen): array
    {
        $normalized = [];
        if (!is_array($mensen)) {
            return $normalized;
        }
        foreach ($mensen as $key => $value) {
            $normalized[(string)$key] = $this->normalizeLocationIds($value);
        }
        return $normalized;
    }

    /** @return list<int> */
    private function normalizeLocationIds(mixed $mensaConfig): array
    {
        if (is_array($mensaConfig)) {
            foreach (['locations', 'location_ids', 'standorte', 'ids', 'id', 'location_id'] as $key) {
                if (array_key_exists($key, $mensaConfig)) {
                    return $this->normalizeIntList($mensaConfig[$key]);
                }
            }
            return [];
        }
        return $this->normalizeIntList($mensaConfig);
    }

    /** @return list<int> */
    private function normalizeIntList(mixed $value): array
    {
        $rawValues = is_array($value) ? $value : explode(',', (string)$value);
        $ids = [];
        foreach ($rawValues as $rawValue) {
            $rawValue = trim((string)$rawValue);
            if ($rawValue !== '' && is_numeric($rawValue)) {
                $ids[] = (int)$rawValue;
            }
        }
        return array_values(array_unique($ids));
    }

    /** @return list<string> */
    private function normalizeStringList(mixed $value): array
    {
        $rawValues = is_array($value) ? $value : explode(',', (string)$value);
        $strings = [];
        foreach ($rawValues as $rawValue) {
            $string = trim((string)$rawValue);
            if ($string !== '') {
                $strings[] = $string;
            }
        }
        return array_values(array_unique($strings));
    }

    /** @return list<int> */
    private function resolveLocationFilter(string $mensa): array
    {
        if (isset($this->mensaLocations[$mensa])) {
            return $this->mensaLocations[$mensa];
        }
        if (is_numeric($mensa)) {
            return [(int)$mensa];
        }
        return [];
    }

    private function resolveMensaKey(?int $locationId): ?string
    {
        if ($locationId === null) {
            return null;
        }
        foreach ($this->mensaLocations as $key => $ids) {
            if (in_array($locationId, $ids, true)) {
                return $key;
            }
        }
        return null;
    }

    /** @param array<string,string> $attributes */
    private function resolveTypeName(?int $typeId, array $attributes): ?string
    {
        if ($typeId === null) {
            return null;
        }
        $foodTypes = is_array($this->config['food_types'] ?? null) ? $this->config['food_types'] : [];
        $typeConfig = $foodTypes[$typeId] ?? null;
        $label = $this->localizedLabel($typeConfig, (string)($this->config['default_language'] ?? 'de'));
        if ($label !== null) {
            return $label;
        }
        $mapped = $this->mappedValue($attributes, 'type_name');
        return $mapped !== '' ? $mapped : 'Type ' . $typeId;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd.m.Y', 'd.m.y', DateTimeInterface::ATOM] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }
        $timestamp = strtotime($value);
        return $timestamp !== false ? (new DateTimeImmutable())->setTimestamp($timestamp) : null;
    }

    private function normalizeDate(DateTimeInterface|string $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $date = $this->parseDate((string)$value);
        return $date?->format('Y-m-d');
    }

    private function parseDecimal(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_contains($value, ',') && (!str_contains($value, '.') || strrpos($value, ',') > strrpos($value, '.'))) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        return is_numeric($value) ? (float)$value : null;
    }

    private function toNullableInt(string $value): ?int
    {
        $value = trim($value);
        return $value !== '' && is_numeric($value) ? (int)$value : null;
    }

    private function normalizeRating(string $value): ?string
    {
        $value = strtoupper(trim($value));
        return in_array($value, ['A', 'B', 'C', 'D', 'E', 'F'], true) ? $value : null;
    }

    private function stripInlineMeta(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        do {
            $before = $value;
            $value = preg_replace('/\s*\(([[:alnum:]_-]{1,32}(?:\s*,\s*[[:alnum:]_-]{1,32})*)\)\s*$/u', '', $value) ?? $value;
            $value = trim($value);
        } while ($value !== $before);
        return $value;
    }

    /** @param list<string> $tokenCategories @return list<string> */
    private function detectCategories(?int $typeId, array $tokenCategories): array
    {
        $categories = [];
        $foodTypes = is_array($this->config['food_types'] ?? null) ? $this->config['food_types'] : [];
        $typeConfig = $typeId !== null ? ($foodTypes[$typeId] ?? null) : null;
        if (is_array($typeConfig)) {
            foreach ($this->normalizeStringList($typeConfig['categories'] ?? []) as $category) {
                $categories[] = $category;
            }
        }
        foreach ($tokenCategories as $category) {
            $categories[] = $category;
        }
        return array_values(array_unique($categories));
    }

    /** @param list<string> $categories */
    private function classifyDish(array $categories): string
    {
        if (in_array('vegan', $categories, true)) {
            return 'vegan';
        }
        if (in_array('vegetarian', $categories, true)) {
            return 'vegetarian';
        }
        if (count(array_intersect($categories, ['fish', 'fish_higher_welfare'])) > 0) {
            return 'fish';
        }
        return 'meat';
    }

    private function localizedLabel(mixed $config, string $language): ?string
    {
        if (is_string($config)) {
            $value = trim($config);
            return $value !== '' ? $value : null;
        }
        if (!is_array($config)) {
            return null;
        }
        $labels = is_array($config['labels'] ?? null) ? $config['labels'] : [];
        foreach (array_values(array_unique([$language, current_locale(), (string)($this->config['default_language'] ?? ''), 'de', 'en'])) as $locale) {
            $base = preg_split('/[_-]/', $locale)[0] ?? $locale;
            foreach ([$locale, $base] as $candidate) {
                $label = trim((string)($labels[$candidate] ?? ''));
                if ($label !== '') {
                    return $label;
                }
            }
        }
        foreach (['label', 'name', 'key'] as $key) {
            $label = trim((string)($config[$key] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }
        return null;
    }
}
