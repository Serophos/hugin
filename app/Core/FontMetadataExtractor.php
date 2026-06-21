<?php
namespace App\Core;

use FontLib\Font;
use RuntimeException;
use Throwable;

class FontMetadataExtractor
{
    public const SUPPORTED_FORMATS = ['woff2', 'woff', 'ttf', 'otf'];
    private const WOFF2_KNOWN_TAGS = [
        'cmap', 'head', 'hhea', 'hmtx', 'maxp', 'name', 'OS/2', 'post',
        'cvt ', 'fpgm', 'glyf', 'loca', 'prep', 'CFF ', 'VORG', 'EBDT',
        'EBLC', 'gasp', 'hdmx', 'kern', 'LTSH', 'PCLT', 'VDMX', 'vhea',
        'vmtx', 'BASE', 'GDEF', 'GPOS', 'GSUB', 'EBSC', 'JSTF', 'MATH',
        'CBDT', 'CBLC', 'COLR', 'CPAL', 'SVG ', 'sbix', 'acnt', 'avar',
        'bdat', 'bloc', 'bsln', 'cvar', 'fdsc', 'feat', 'fmtx', 'fvar',
        'gvar', 'hsty', 'just', 'lcar', 'mort', 'morx', 'opbd', 'prop',
        'trak', 'Zapf', 'Silf', 'Glat', 'Gloc', 'Feat', 'Sill',
    ];

    public function extract(string $path, string $format): array
    {
        $format = strtolower(trim($format));
        if (!in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }
        if (!is_file($path) || (int)filesize($path) <= 0) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }

        $this->validateSignature($path, $format);

        if ($format === 'woff2') {
            $this->validateWoff2Header($path);
            return $this->emptyMetadata($format);
        }

        if (!class_exists(Font::class)) {
            throw new RuntimeException(__('media.font_parser_unavailable', [], 'Font metadata extraction is not available.'));
        }

        $font = null;
        try {
            $font = Font::load($path);
            if ($font === null) {
                throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
            }

            $font->parse();
            $metadata = [
                'font_family_name' => $this->cleanText($this->fontValue($font, 'getFontName'), 190),
                'font_full_name' => $this->cleanText($this->fontValue($font, 'getFontFullName'), 190),
                'font_subfamily' => $this->cleanText($this->fontValue($font, 'getFontSubfamily'), 120),
                'font_weight' => $this->normalizeWeight($this->fontValue($font, 'getFontWeight'), $this->fontValue($font, 'getFontSubfamily')),
                'font_postscript_name' => $this->cleanText($this->fontValue($font, 'getFontPostscriptName'), 190),
                'font_version' => $this->cleanText($this->fontValue($font, 'getFontVersion'), 190),
                'font_format' => $format,
            ];
        } catch (Throwable $e) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'), 0, $e);
        } finally {
            if (is_object($font) && method_exists($font, 'close')) {
                $font->close();
            }
        }

        return $metadata;
    }

    private function emptyMetadata(string $format): array
    {
        return [
            'font_family_name' => '',
            'font_full_name' => '',
            'font_subfamily' => '',
            'font_weight' => null,
            'font_postscript_name' => '',
            'font_version' => '',
            'font_format' => $format,
        ];
    }

    private function validateSignature(string $path, string $format): void
    {
        $header = (string)file_get_contents($path, false, null, 0, 4);
        $valid = match ($format) {
            'woff2' => $header === 'wOF2',
            'woff' => $header === 'wOFF',
            'ttf' => $header === "\x00\x01\x00\x00" || $header === 'true',
            'otf' => $header === 'OTTO',
            default => false,
        };

        if (!$valid) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }
    }

    private function validateWoff2Header(string $path): void
    {
        $bytes = file_get_contents($path);
        if (!is_string($bytes) || strlen($bytes) < 48) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }

        $data = unpack(
            'Nsignature/Nflavor/Nlength/nnumTables/nreserved/NtotalSfntSize/NtotalCompressedSize/nmajorVersion/nminorVersion/NmetaOffset/NmetaLength/NmetaOrigLength/NprivOffset/NprivLength',
            substr($bytes, 0, 48)
        );
        if (!is_array($data)) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }

        $fileSize = (int)filesize($path);
        $flavor = (int)($data['flavor'] ?? 0);
        $length = (int)($data['length'] ?? 0);
        $numTables = (int)($data['numTables'] ?? 0);
        $totalSfntSize = (int)($data['totalSfntSize'] ?? 0);
        $totalCompressedSize = (int)($data['totalCompressedSize'] ?? 0);
        $metaOffset = (int)($data['metaOffset'] ?? 0);
        $metaLength = (int)($data['metaLength'] ?? 0);
        $metaOrigLength = (int)($data['metaOrigLength'] ?? 0);
        $privOffset = (int)($data['privOffset'] ?? 0);
        $privLength = (int)($data['privLength'] ?? 0);

        if (!in_array($flavor, [0x00010000, 0x4F54544F, 0x74727565], true)) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }
        if ($length !== $fileSize || $numTables < 1 || $numTables > 512 || $totalSfntSize <= 0 || $totalCompressedSize <= 0) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }

        $offset = 48;
        $tags = [];
        $hasHead = false;
        $hasName = false;
        $hasGlyf = false;
        $hasHmtxTransform = false;
        $glyfTransformed = null;
        $locaTransformed = null;
        $declaredDataSize = 0;
        for ($i = 0; $i < $numTables; $i++) {
            if ($offset >= $fileSize) {
                throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
            }

            $flags = ord($bytes[$offset]);
            $offset++;
            $tagIndex = $flags & 0x3f;
            $transformVersion = ($flags >> 6) & 0x03;
            if ($tagIndex === 0x3f) {
                if ($offset + 4 > $fileSize) {
                    throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
                }
                $tag = substr($bytes, $offset, 4);
                $offset += 4;
            } else {
                $tag = self::WOFF2_KNOWN_TAGS[$tagIndex] ?? '';
            }

            if (!$this->isValidWoff2Tag($tag) || isset($tags[$tag])) {
                throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
            }
            $tags[$tag] = true;

            $originalLength = $this->readWoff2Base128($bytes, $offset, $fileSize);
            $dataLength = $originalLength;
            $usesTransformLength = $this->woff2TableUsesTransformLength($tag, $transformVersion);
            if ($usesTransformLength) {
                $transformLength = $this->readWoff2Base128($bytes, $offset, $fileSize);
                if ($tag === 'loca') {
                    if ($transformLength !== 0) {
                        throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
                    }
                } elseif ($transformLength <= 0) {
                    throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
                }
                $dataLength = $transformLength;
            }
            if (in_array($tag, ['head', 'name'], true) && $originalLength <= 0) {
                throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
            }
            $declaredDataSize += $dataLength;

            $hasHead = $hasHead || $tag === 'head';
            $hasName = $hasName || $tag === 'name';
            $hasGlyf = $hasGlyf || $tag === 'glyf';
            $hasHmtxTransform = $hasHmtxTransform || ($tag === 'hmtx' && $usesTransformLength);
            if ($tag === 'glyf') {
                $glyfTransformed = $usesTransformLength;
            } elseif ($tag === 'loca') {
                $locaTransformed = $usesTransformLength;
            }
        }

        if (!$hasHead || !$hasName) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }
        if (($glyfTransformed === null) !== ($locaTransformed === null) || ($glyfTransformed !== null && $glyfTransformed !== $locaTransformed)) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }
        if ($hasHmtxTransform && !$hasGlyf) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }
        if ($declaredDataSize <= 0) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }

        $compressedOffset = $offset;
        $compressedEnd = $compressedOffset + $totalCompressedSize;
        if ($compressedEnd > $fileSize) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }

        $lastEnd = $compressedEnd;
        if ($metaOffset > 0) {
            if ($metaLength <= 0 || $metaOrigLength <= 0 || $metaOffset !== $this->align4($lastEnd) || $metaOffset + $metaLength > $fileSize) {
                throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
            }
            $lastEnd = $metaOffset + $metaLength;
        } elseif ($metaLength !== 0 || $metaOrigLength !== 0) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }

        if ($privOffset > 0) {
            if ($privLength <= 0 || $privOffset !== $this->align4($lastEnd) || $privOffset + $privLength > $fileSize) {
                throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
            }
            $lastEnd = $privOffset + $privLength;
        } elseif ($privLength !== 0) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }

        if ($lastEnd !== $fileSize) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }
    }

    private function readWoff2Base128(string $bytes, int &$offset, int $fileSize): int
    {
        $value = 0;
        for ($i = 0; $i < 5; $i++) {
            if ($offset >= $fileSize) {
                throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
            }

            $byte = ord($bytes[$offset]);
            $offset++;
            if (($i === 0 && $byte === 0x80) || ($value & 0xFE000000) !== 0) {
                throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
            }

            $value = ($value << 7) | ($byte & 0x7f);
            if (($byte & 0x80) === 0) {
                return $value;
            }
        }

        throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
    }

    private function woff2TableUsesTransformLength(string $tag, int $transformVersion): bool
    {
        if ($tag === 'glyf' || $tag === 'loca') {
            if (!in_array($transformVersion, [0, 3], true)) {
                throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
            }

            return $transformVersion === 0;
        }

        if ($tag === 'hmtx') {
            if (!in_array($transformVersion, [0, 1], true)) {
                throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
            }

            return $transformVersion === 1;
        }

        if ($transformVersion !== 0) {
            throw new RuntimeException(__('media.font_invalid', [], 'The uploaded file is not a supported font.'));
        }

        return false;
    }

    private function isValidWoff2Tag(string $tag): bool
    {
        return strlen($tag) === 4 && preg_match('/^[\x20-\x7E]{4}$/', $tag) === 1;
    }

    private function align4(int $value): int
    {
        return ($value + 3) & ~3;
    }

    private function fontValue(object $font, string $method): mixed
    {
        if (!method_exists($font, $method)) {
            return null;
        }

        try {
            return $font->{$method}();
        } catch (Throwable) {
            return null;
        }
    }

    private function cleanText(mixed $value, int $maxLength): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = trim((string)$value);
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }

    private function normalizeWeight(mixed $value, mixed $subfamily): ?int
    {
        if (is_numeric($value)) {
            $weight = (int)$value;
            if ($weight >= 1 && $weight <= 1000) {
                return $weight;
            }
        }

        $name = strtolower((string)$subfamily);
        return match (true) {
            str_contains($name, 'thin') => 100,
            str_contains($name, 'extra light'), str_contains($name, 'extralight'), str_contains($name, 'ultra light'), str_contains($name, 'ultralight') => 200,
            str_contains($name, 'light') => 300,
            str_contains($name, 'medium') => 500,
            str_contains($name, 'semi bold'), str_contains($name, 'semibold'), str_contains($name, 'demi bold'), str_contains($name, 'demibold') => 600,
            str_contains($name, 'extra bold'), str_contains($name, 'extrabold'), str_contains($name, 'ultra bold'), str_contains($name, 'ultrabold') => 800,
            str_contains($name, 'black'), str_contains($name, 'heavy') => 900,
            str_contains($name, 'bold') => 700,
            str_contains($name, 'regular'), str_contains($name, 'normal'), str_contains($name, 'book') => 400,
            default => null,
        };
    }
}
