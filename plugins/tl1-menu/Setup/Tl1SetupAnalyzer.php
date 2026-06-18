<?php

declare(strict_types=1);

namespace Plugins\Tl1Menu\Setup;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

final class Tl1SetupAnalyzer
{
    private const MAX_DOWNLOAD_BYTES = 12582912;
    private const SAMPLE_ROW_LIMIT = 1200;

    public function __construct(private readonly string $cacheFile)
    {
    }

    /** @return array<string, mixed> */
    public function downloadAndAnalyze(string $url): array
    {
        $xml = $this->downloadXml($url);
        $this->writeCache($xml);
        return $this->analyzeXml($xml, $url);
    }

    /** @return array<string, mixed> */
    public function analyzeCachedXml(string $sourceUrl = ''): array
    {
        return $this->analyzeXml($this->readCache(), $sourceUrl);
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    public function analyzeCachedWithConfig(array $config): array
    {
        return $this->analyzeRows($this->readCache(), $config);
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    public function previewCachedRow(array $config, int $rowIndex): array
    {
        $dom = $this->loadXml($this->readCache());
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('/DATAPACKET/ROWDATA/ROW');
        if ($nodes === false || $nodes->length === 0) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_no_rows'));
        }
        $rowIndex = max(0, min($rowIndex, $nodes->length - 1));
        $row = $nodes->item($rowIndex);
        if (!$row instanceof DOMElement) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_no_rows'));
        }

        return [
            'row_index' => $rowIndex,
            'row_count' => $nodes->length,
            'row' => $this->rowAttributes($row),
        ];
    }

    private function readCache(): string
    {
        if (!is_file($this->cacheFile)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_xml_missing'));
        }
        $xml = file_get_contents($this->cacheFile);
        if (!is_string($xml) || trim($xml) === '') {
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_xml_missing'));
        }
        return $xml;
    }

    /** @return array<string, mixed> */
    private function analyzeXml(string $xml, string $url): array
    {
        $dom = $this->loadXml($xml);
        $fields = $this->metadataFields($dom);
        $rows = $this->sampleRows($dom);
        $mapping = $this->suggestMapping($fields, $rows);
        $config = $this->buildGeneratedConfig($url, $fields, $rows, $mapping);
        $analysis = $this->summarizeConfig($rows, $config);

        return [
            'metadata' => $fields,
            'sample_rows' => array_slice($rows, 0, 25),
            'mapping' => $mapping,
            'generated_config' => $config,
            'analysis' => $analysis,
        ];
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    private function analyzeRows(string $xml, array $config): array
    {
        $dom = $this->loadXml($xml);
        $rows = $this->sampleRows($dom);
        return [
            'analysis' => $this->summarizeConfig($rows, $config),
            'sample_rows' => array_slice($rows, 0, 25),
        ];
    }

    private function downloadXml(string $url): string
    {
        $url = trim($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.invalid_menu_url'));
        }
        $scheme = strtolower((string)(parse_url($url, PHP_URL_SCHEME) ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.invalid_menu_url'));
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'header' => "User-Agent: Hugin TL1 Menu Setup\r\nAccept: application/xml,text/xml,*/*\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $handle = @fopen($url, 'rb', false, $context);
        if (!is_resource($handle)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_download_failed'));
        }

        $xml = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 1048576);
            if (!is_string($chunk)) {
                break;
            }
            $xml .= $chunk;
            if (strlen($xml) > self::MAX_DOWNLOAD_BYTES) {
                fclose($handle);
                throw new RuntimeException(__('plugins.tl1-menu.errors.setup_xml_too_large'));
            }
        }
        fclose($handle);

        if (trim($xml) === '') {
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_download_failed'));
        }

        return $xml;
    }

    private function writeCache(string $xml): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_cache_failed'));
        }
        if (file_put_contents($this->cacheFile, $xml) === false) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_cache_failed'));
        }
    }

    private function loadXml(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_invalid_xml'));
        }
        return $dom;
    }

    /** @return list<array{name:string,label:string,type:string,class:string}> */
    private function metadataFields(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('/DATAPACKET/METADATA/FIELDS/FIELD');
        $fields = [];
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (!$node instanceof DOMElement) {
                    continue;
                }
                $name = trim($node->getAttribute('FieldName'));
                if ($name === '') {
                    continue;
                }
                $fields[] = [
                    'name' => $name,
                    'label' => trim($node->getAttribute('DisplayLabel')) ?: $name,
                    'type' => trim($node->getAttribute('FieldType')),
                    'class' => trim($node->getAttribute('FieldClass')),
                ];
            }
        }
        if ($fields === []) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_no_metadata'));
        }
        return $fields;
    }

    /** @return list<array<string,string>> */
    private function sampleRows(DOMDocument $dom): array
    {
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('/DATAPACKET/ROWDATA/ROW');
        if ($nodes === false || $nodes->length === 0) {
            throw new RuntimeException(__('plugins.tl1-menu.errors.setup_no_rows'));
        }
        $rows = [];
        $limit = min($nodes->length, self::SAMPLE_ROW_LIMIT);
        for ($i = 0; $i < $limit; $i++) {
            $node = $nodes->item($i);
            if ($node instanceof DOMElement) {
                $rows[] = $this->rowAttributes($node);
            }
        }
        return $rows;
    }

    /** @return array<string,string> */
    private function rowAttributes(DOMElement $row): array
    {
        $attributes = [];
        foreach ($row->attributes as $attribute) {
            $attributes[$attribute->nodeName] = trim((string)$attribute->nodeValue);
        }
        return $attributes;
    }

    /** @param list<array{name:string,label:string,type:string,class:string}> $fields @param list<array<string,string>> $rows @return array<string,mixed> */
    private function suggestMapping(array $fields, array $rows): array
    {
        $names = array_map(static fn(array $field): string => $field['name'], $fields);
        $pick = fn(array $candidates): ?string => $this->pickField($names, $candidates);
        $textDe = $this->sequentialFields($names, ['TEXTL', 'TEXT1L']);
        $textEn = $this->sequentialFields($names, ['TEXT3L', 'TEXTEN', 'TEXT2L']);

        return [
            'id' => $pick(['DISPO_ID', 'ID', 'ARTIKEL_ID', 'ITEM_ID']),
            'date' => $pick(['DATUM', 'DATE', 'TAG']),
            'mensa_name' => $pick(['MENSA', 'MENSA_NAME', 'BETRIEB', 'AUSGABESTELLE']),
            'location_id' => $pick(['VERBRAUCHSORT', 'ORT', 'STANDORT', 'LOCATION_ID', 'KOSTENSTELLE']),
            'location_name' => $pick(['ORT_NAME', 'STANDORT_NAME', 'LOCATION_NAME']),
            'type_id' => $pick(['TYP', 'TYPE', 'SPEISETYP', 'ART']),
            'type_name' => $pick(['SPEISE', 'TYPE_NAME', 'TYP_NAME', 'KATEGORIE']),
            'spalte' => $pick(['SPALTESPEISEPLAN', 'SPALTE', 'SORTIERUNG', 'SORT']),
            'title' => [
                'de' => array_slice($textDe, 0, 1),
                'en' => array_slice($textEn, 0, 1),
            ],
            'description' => [
                'de' => array_slice($textDe, 1),
                'en' => array_slice($textEn, 1),
            ],
            'allergen_codes' => $pick(['ZSNUMMERN', 'ALLERGENE', 'ALLERGEN_CODES', 'ZUSATZSTOFFE']),
            'allergen_names' => ['de' => $pick(['ZSNAMEN', 'ALLERGENNAMEN', 'ALLERGEN_NAMES'])],
            'category_token_fields' => array_values(array_filter([$pick(['AUSGABETEXT']), ...$textDe, ...$textEn])),
            'prices' => $this->suggestPriceGroups($names),
            'environment' => [
                'co2_value' => $pick(['EXTINFO_CO2_WERT', 'CO2_WERT', 'CO2']),
                'co2_rating' => $pick(['EXTINFO_CO2_BEWERTUNG', 'CO2_BEWERTUNG', 'CO2_RATING']),
                'co2_saving' => $pick(['EXTINFO_CO2_EINSPARUNG', 'CO2_EINSPARUNG']),
                'water_value' => $pick(['EXTINFO_WASSER_WERT', 'WASSER_WERT', 'WATER']),
                'water_rating' => $pick(['EXTINFO_WASSER_BEWERTUNG', 'WASSER_BEWERTUNG', 'WATER_RATING']),
                'animal_welfare' => $pick(['EXTINFO_TIERWOHL', 'TIERWOHL', 'ANIMAL_WELFARE']),
                'rainforest' => $pick(['EXTINFO_REGENWALD', 'REGENWALD', 'RAINFOREST']),
            ],
        ];
    }

    /** @param list<string> $names @return list<array{key:string,field:string,labels:array<string,string>}> */
    private function suggestPriceGroups(array $names): array
    {
        $candidates = [
            ['student', ['STUDIERENDE', 'STUDENT', 'STUDENTS'], ['de' => 'Studierende', 'en' => 'Students']],
            ['staff', ['BEDIENSTETE', 'MITARBEITER', 'EMPLOYEE', 'STAFF'], ['de' => 'Bedienstete', 'en' => 'Staff']],
            ['guest', ['GAESTE', 'GÄSTE', 'GUEST', 'GUESTS'], ['de' => 'Gäste', 'en' => 'Guests']],
        ];
        $groups = [];
        foreach ($candidates as [$key, $fieldCandidates, $labels]) {
            $field = $this->pickField($names, $fieldCandidates);
            if ($field !== null) {
                $groups[] = ['key' => $key, 'field' => $field, 'labels' => $labels];
            }
        }
        return $groups;
    }

    /** @param list<string> $names @param list<string> $candidates */
    private function pickField(array $names, array $candidates): ?string
    {
        $upperMap = [];
        foreach ($names as $name) {
            $upperMap[strtoupper($name)] = $name;
        }
        foreach ($candidates as $candidate) {
            $upper = strtoupper($candidate);
            if (isset($upperMap[$upper])) {
                return $upperMap[$upper];
            }
        }
        foreach ($candidates as $candidate) {
            $needle = strtoupper($candidate);
            foreach ($upperMap as $upperName => $name) {
                if (str_contains($upperName, $needle)) {
                    return $name;
                }
            }
        }
        return null;
    }

    /** @param list<string> $names @param list<string> $prefixes @return list<string> */
    private function sequentialFields(array $names, array $prefixes): array
    {
        $fields = [];
        foreach ($prefixes as $prefix) {
            for ($i = 1; $i <= 12; $i++) {
                $field = $this->pickField($names, [$prefix . $i]);
                if ($field !== null) {
                    $fields[] = $field;
                }
            }
            if ($fields !== []) {
                return array_values(array_unique($fields));
            }
        }
        return [];
    }

    /** @param list<array{name:string,label:string,type:string,class:string}> $fields @param list<array<string,string>> $rows @param array<string,mixed> $mapping @return array<string,mixed> */
    private function buildGeneratedConfig(string $url, array $fields, array $rows, array $mapping): array
    {
        $config = [
            'schema_version' => 2,
            'field_definitions' => $fields,
            'field_mapping' => $mapping,
            'price_groups' => $mapping['prices'] ?? [],
            'mensen' => [],
            'standort_namen' => [],
            'food_types' => [],
            'categories' => $this->defaultCategories(),
            'token_catalog' => [],
            'setup' => [
                'source_url' => $url,
                'generated_at' => date('c'),
            ],
        ];

        $summary = $this->summarizeConfig($rows, $config);
        $config['mensen'] = $summary['mensen'];
        $config['standort_namen'] = $summary['standort_namen'];
        $config['food_types'] = $summary['food_types'];
        $config['token_catalog'] = $summary['token_catalog'];

        return $config;
    }

    /** @param list<array<string,string>> $rows @param array<string,mixed> $config @return array<string,mixed> */
    private function summarizeConfig(array $rows, array $config): array
    {
        $mapping = is_array($config['field_mapping'] ?? null) ? $config['field_mapping'] : [];
        $locationField = (string)($mapping['location_id'] ?? '');
        $mensaNameField = (string)($mapping['mensa_name'] ?? '');
        $typeField = (string)($mapping['type_id'] ?? '');
        $typeNameField = (string)($mapping['type_name'] ?? '');
        $allergenCodeField = (string)($mapping['allergen_codes'] ?? '');
        $allergenNames = is_array($mapping['allergen_names'] ?? null) ? $mapping['allergen_names'] : [];
        $allergenNameField = (string)($allergenNames['de'] ?? reset($allergenNames) ?: '');
        $categoryTokenFields = is_array($mapping['category_token_fields'] ?? null) ? $mapping['category_token_fields'] : [];

        $locations = [];
        $types = [];
        $tokens = [];
        foreach ($rows as $row) {
            $locationId = $this->toNullableInt($row[$locationField] ?? '');
            if ($locationId !== null) {
                $locationName = trim((string)($row[$mensaNameField] ?? '')) ?: ('Location ' . $locationId);
                $locations[$locationId] = $locationName;
            }
            $typeId = $this->toNullableInt($row[$typeField] ?? '');
            if ($typeId !== null) {
                $typeName = trim((string)($row[$typeNameField] ?? '')) ?: ('Type ' . $typeId);
                $types[$typeId] = $typeName;
            }
            foreach ($this->pairedTokens($row[$allergenCodeField] ?? '', $row[$allergenNameField] ?? '') as $code => $name) {
                $tokens[$code] = $name;
            }
            foreach ($categoryTokenFields as $field) {
                foreach ($this->inlineTokens($row[(string)$field] ?? '') as $token) {
                    $tokens[$token] = $tokens[$token] ?? $token;
                }
            }
        }
        ksort($locations);
        ksort($types);
        ksort($tokens, SORT_NATURAL | SORT_FLAG_CASE);

        $mensen = [];
        $standortNames = [];
        foreach ($locations as $id => $label) {
            $key = $this->slugify($label !== '' ? $label : ('location-' . $id));
            $mensen[$key] = ['label' => $label, 'locations' => [(int)$id]];
            $standortNames[(int)$id] = $label;
        }

        $foodTypes = [];
        foreach ($types as $id => $label) {
            $key = $this->slugify($label !== '' ? $label : ('type-' . $id));
            $foodTypes[(int)$id] = [
                'key' => $key,
                'labels' => ['de' => $label, 'en' => $label],
                'categories' => $this->guessCategories($label),
            ];
        }

        $tokenCatalog = [];
        foreach ($tokens as $code => $label) {
            $code = (string)$code;
            $label = (string)$label;
            $category = $this->guessTokenCategory($code, $label);
            if ($category !== null) {
                $tokenCatalog[$code] = ['kind' => 'category', 'category' => $category, 'labels' => ['de' => $label, 'en' => $label]];
            } elseif (preg_match('/^\d+[a-z]?$/i', $code) === 1) {
                $tokenCatalog[$code] = ['kind' => 'additive', 'labels' => ['de' => $label, 'en' => $label]];
            } else {
                $tokenCatalog[$code] = ['kind' => 'allergen', 'labels' => ['de' => $label, 'en' => $label]];
            }
        }

        return [
            'mensen' => $mensen,
            'standort_namen' => $standortNames,
            'food_types' => $foodTypes,
            'token_catalog' => $tokenCatalog,
            'counts' => [
                'rows' => count($rows),
                'locations' => count($locations),
                'food_types' => count($types),
                'tokens' => count($tokens),
            ],
        ];
    }

    /** @return array<string,array{icon:string,labels:array<string,string>}> */
    private function defaultCategories(): array
    {
        $categories = [
            'vegan' => ['icon' => $this->categoryIconPath('vegan'), 'labels' => ['de' => 'Vegan', 'en' => 'Vegan']],
            'vegetarian' => ['icon' => $this->categoryIconPath('vegetarian'), 'labels' => ['de' => 'Vegetarisch', 'en' => 'Vegetarian']],
            'fish' => ['icon' => $this->categoryIconPath('fish'), 'labels' => ['de' => 'Fisch', 'en' => 'Fish']],
            'fish_higher_welfare' => ['icon' => $this->categoryIconPath('fish_higher_welfare'), 'labels' => ['de' => 'Fisch aus artgerechter Haltung', 'en' => 'Fish from higher-welfare sourcing']],
            'meat' => ['icon' => $this->categoryIconPath('meat'), 'labels' => ['de' => 'Fleisch', 'en' => 'Meat']],
            'pork' => ['icon' => $this->categoryIconPath('pork'), 'labels' => ['de' => 'Schwein', 'en' => 'Pork']],
            'pork_higher_welfare' => ['icon' => $this->categoryIconPath('pork_higher_welfare'), 'labels' => ['de' => 'Schwein aus artgerechter Haltung', 'en' => 'Pork from higher-welfare farming']],
            'beef' => ['icon' => $this->categoryIconPath('beef'), 'labels' => ['de' => 'Rind', 'en' => 'Beef']],
            'beef_higher_welfare' => ['icon' => $this->categoryIconPath('beef_higher_welfare'), 'labels' => ['de' => 'Rind aus artgerechter Haltung', 'en' => 'Beef from higher-welfare farming']],
            'poultry' => ['icon' => $this->categoryIconPath('poultry'), 'labels' => ['de' => 'Geflügel', 'en' => 'Poultry']],
            'poultry_higher_welfare' => ['icon' => $this->categoryIconPath('poultry_higher_welfare'), 'labels' => ['de' => 'Geflügel aus artgerechter Haltung', 'en' => 'Poultry from higher-welfare farming']],
            'lamb' => ['icon' => $this->categoryIconPath('lamb'), 'labels' => ['de' => 'Lamm', 'en' => 'Lamb']],
            'lamb_higher_welfare' => ['icon' => $this->categoryIconPath('lamb_higher_welfare'), 'labels' => ['de' => 'Lamm aus artgerechter Haltung', 'en' => 'Lamb from higher-welfare farming']],
            'dessert' => ['icon' => $this->categoryIconPath('dessert'), 'labels' => ['de' => 'Dessert', 'en' => 'Dessert']],
            'your_favorite' => ['icon' => $this->categoryIconPath('favorite'), 'labels' => ['de' => 'Your Favorite', 'en' => 'Your Favorite']],
            'streetfood' => ['icon' => $this->categoryIconPath('streetfood'), 'labels' => ['de' => 'Streetfood', 'en' => 'Streetfood']],
            'international' => ['icon' => $this->categoryIconPath('international'), 'labels' => ['de' => 'International', 'en' => 'International']],
            'mensa_vital' => ['icon' => $this->categoryIconPath('vital'), 'labels' => ['de' => 'Mensa Vital', 'en' => 'Mensa Vital']],
            'bio' => ['icon' => $this->categoryIconPath('greenfood'), 'labels' => ['de' => 'Bio', 'en' => 'Organic']],
            'soup' => ['icon' => $this->categoryIconPath('soup'), 'labels' => ['de' => 'Suppe', 'en' => 'Soup']],
            'salad' => ['icon' => $this->categoryIconPath('salad'), 'labels' => ['de' => 'Salat', 'en' => 'Salad']],
            'pasta' => ['icon' => $this->categoryIconPath('pasta'), 'labels' => ['de' => 'Pasta', 'en' => 'Pasta']],
            'pizza' => ['icon' => $this->categoryIconPath('pizza'), 'labels' => ['de' => 'Pizza', 'en' => 'Pizza']],
            'burger' => ['icon' => $this->categoryIconPath('burger'), 'labels' => ['de' => 'Burger', 'en' => 'Burger']],
            'sh_teller' => ['icon' => $this->categoryIconPath('lighthouse'), 'labels' => ['de' => 'SH Teller', 'en' => 'SH Teller']],
            'kuechenklassiker' => ['icon' => $this->categoryIconPath('classics'), 'labels' => ['de' => 'Küchenklassiker', 'en' => 'Kitchen classic']],
        ];
        ksort($categories, SORT_NATURAL | SORT_FLAG_CASE);

        return $categories;
    }

    private function categoryIconPath(string $icon): string
    {
        $stem = str_replace('-', '_', $icon);
        foreach (['png', 'webp', 'svg'] as $extension) {
            $filename = $stem . '.' . $extension;
            if (is_file(__DIR__ . '/../assets/img/categories/' . $filename)) {
                return 'assets/img/categories/' . $filename;
            }
        }

        return 'assets/img/categories/' . $stem . '.webp';
    }

    /** @return list<string> */
    private function guessCategories(string $label): array
    {
        $category = $this->guessCategoryFromText('', $label);
        return $category !== null ? [$category] : [];
    }

    private function guessTokenCategory(string $code, string $label): ?string
    {
        return $this->guessCategoryFromText($code, $label);
    }

    private function guessCategoryFromText(string $code, string $label): ?string
    {
        $key = strtoupper(trim($code));
        $text = $this->normalizeSearchText($code . ' ' . $label);
        $map = [
            'VN' => 'vegan', 'VE' => 'vegetarian', 'FI' => 'fish', 'F' => 'fish', 'S' => 'pork', 'R' => 'beef', 'G' => 'poultry', 'L' => 'lamb',
            'AGS' => 'pork_higher_welfare', 'AGF' => 'fish_higher_welfare', 'LGF' => 'lamb_higher_welfare', 'AGR' => 'beef_higher_welfare',
            'STF' => 'streetfood', 'SHT' => 'sh_teller', 'KK' => 'kuechenklassiker', 'YF' => 'your_favorite', 'IN' => 'international', 'MV' => 'mensa_vital',
        ];
        if (isset($map[$key])) {
            return $map[$key];
        }

        $patterns = [
            'vegan' => ['vegan'],
            'vegetarian' => ['vegetar', 'vegetarian'],
            'fish_higher_welfare' => ['fisch aus artgerechter', 'fish from higher-welfare'],
            'fish' => ['fisch', 'fish'],
            'pork_higher_welfare' => ['schwein aus artgerechter', 'pork from higher-welfare'],
            'pork' => ['schwein', 'pork'],
            'beef_higher_welfare' => ['rind aus artgerechter', 'beef from higher-welfare'],
            'beef' => ['rind', 'beef'],
            'poultry_higher_welfare' => ['geflügel aus artgerechter', 'gefluegel aus artgerechter', 'hähnchen aus artgerechter', 'haehnchen aus artgerechter', 'poultry from higher-welfare', 'chicken from higher-welfare'],
            'poultry' => ['geflügel', 'gefluegel', 'huhn', 'hähnchen', 'haehnchen', 'poultry', 'chicken'],
            'lamb_higher_welfare' => ['lamm aus artgerechter', 'lamb from higher-welfare'],
            'lamb' => ['lamm', 'lamb'],
            'dessert' => ['dessert', 'nachtisch', 'süßspeise', 'suessspeise'],
            'your_favorite' => ['your favorite', 'favorit', 'favorite'],
            'streetfood' => ['streetfood', 'street food'],
            'international' => ['international'],
            'mensa_vital' => ['mensa vital', 'vital'],
            'bio' => ['bio', 'organic'],
            'soup' => ['suppe', 'eintopf', 'soup', 'stew'],
            'salad' => ['salat', 'salad'],
            'pasta' => ['pasta', 'nudel'],
            'pizza' => ['pizza'],
            'burger' => ['burger'],
            'meat' => ['fleisch', 'meat'],
        ];

        foreach ($patterns as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function normalizeSearchText(string $value): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        return strtr($value, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
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

    private function toNullableInt(string $value): ?int
    {
        $value = trim($value);
        return $value !== '' && is_numeric($value) ? (int)$value : null;
    }

    private function slugify(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
        $value = trim($value, '-');
        return $value !== '' ? $value : 'item';
    }
}
