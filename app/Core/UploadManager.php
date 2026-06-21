<?php
/**
 * Hugin - Digital Signage System
 * Copyright (C) 2026 Thees Winkler
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Source code: https://github.com/Serophos/hugin
 */

namespace App\Core;

use RuntimeException;

class UploadManager
{
    private const FONT_MAX_SIZE_BYTES = 20971520;

    public function __construct(private Database $db, private array $config)
    {
    }

    public function storeUploadedFile(?array $file, int $uploadedByUserId, string $label = '', string $licenseNote = ''): ?array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(__('errors.upload_failed', [], 'Upload failed.'));
        }

        $maxSize = (int)app_core_setting('upload.max_size_bytes', 52428800);
        if (($file['size'] ?? 0) > $maxSize) {
            throw new RuntimeException(__('errors.uploaded_file_too_large', [], 'Uploaded file is too large.'));
        }

        $originalName = $this->sanitizeOriginalFilename((string)($file['name'] ?? 'upload'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException(__('errors.invalid_uploaded_file', [], 'Invalid uploaded file.'));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo !== false ? (string)finfo_file($finfo, $tmpName) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $info = $this->classifyUpload($mimeType, $extension);
        if ($info === null) {
            throw new RuntimeException(__('errors.unsupported_file_type', [], 'Unsupported file type. Allowed: JPG, PNG, GIF, WEBP, MP4, WEBM, OGG, WOFF2, WOFF, TTF, OTF.'));
        }

        $fontMetadata = [
            'font_family_name' => null,
            'font_full_name' => null,
            'font_subfamily' => null,
            'font_weight' => null,
            'font_postscript_name' => null,
            'font_version' => null,
            'font_format' => null,
        ];
        if ($info['kind'] === 'font') {
            if (($file['size'] ?? 0) > min($maxSize, self::FONT_MAX_SIZE_BYTES)) {
                throw new RuntimeException(__('media.font_file_too_large', [], 'Font file is too large.'));
            }

            $fontMetadata = (new FontMetadataExtractor())->extract($tmpName, (string)$info['format']);
        }

        $subDir = $info['kind'] === 'font' ? ('fonts/' . date('Y/m')) : date('Y/m');
        $publicDir = rtrim($this->config['paths']['public'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subDir);

        if (!is_dir($publicDir) && !mkdir($publicDir, 0775, true) && !is_dir($publicDir)) {
            throw new RuntimeException(__('errors.upload_directory_create_failed', [], 'Upload directory could not be created.'));
        }

        $filename = bin2hex(random_bytes(18)) . '.' . $info['ext'];
        $absolutePath = $publicDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException(__('errors.uploaded_file_save_failed', [], 'Uploaded file could not be saved.'));
        }

        $publicPath = '/uploads/' . $subDir . '/' . $filename;
        $previewPublicPath = null;
        if ($info['kind'] === 'video') {
            $previewFilename = pathinfo($filename, PATHINFO_FILENAME) . '.preview.jpg';
            $previewAbsolutePath = $publicDir . DIRECTORY_SEPARATOR . $previewFilename;
            if ($this->generateVideoPreview($absolutePath, $previewAbsolutePath)) {
                $previewPublicPath = '/uploads/' . $subDir . '/' . $previewFilename;
            }
        }

        if ($info['kind'] === 'font') {
            $fallbackName = $this->sanitizeText(pathinfo($originalName, PATHINFO_FILENAME), 190);
            $fontMetadata['font_family_name'] = $fontMetadata['font_family_name'] ?: $fallbackName;
            $fontMetadata['font_full_name'] = $fontMetadata['font_full_name'] ?: $fontMetadata['font_family_name'];
        }
        $defaultName = $info['kind'] === 'font'
            ? (string)($fontMetadata['font_full_name'] ?: $fontMetadata['font_family_name'])
            : pathinfo($originalName, PATHINFO_FILENAME);
        $name = $this->sanitizeText(trim($label) !== '' ? trim($label) : $defaultName, 150);
        if ($name === '') {
            $name = __('media.untitled_asset', [], 'Untitled media');
        }
        $licenseNote = $info['kind'] === 'font' ? $this->sanitizeText($licenseNote, 1000) : '';
        $this->db->execute(
            'INSERT INTO media_assets (name, original_name, mime_type, file_size, media_kind, file_path, preview_file_path, font_family_name, font_full_name, font_subfamily, font_weight, font_postscript_name, font_version, font_format, license_note, uploaded_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $name,
                $originalName,
                $mimeType,
                (int)($file['size'] ?? 0),
                $info['kind'],
                $publicPath,
                $previewPublicPath,
                $fontMetadata['font_family_name'] ?: null,
                $fontMetadata['font_full_name'] ?: null,
                $fontMetadata['font_subfamily'] ?: null,
                $fontMetadata['font_weight'] !== null ? (int)$fontMetadata['font_weight'] : null,
                $fontMetadata['font_postscript_name'] ?: null,
                $fontMetadata['font_version'] ?: null,
                $fontMetadata['font_format'] ?: null,
                $licenseNote !== '' ? $licenseNote : null,
                $uploadedByUserId,
            ]
        );

        return $this->db->one('SELECT * FROM media_assets WHERE id = ?', [(int)$this->db->lastInsertId()]);
    }

    public function deleteMediaFile(string $publicPath): void
    {
        $absolutePath = rtrim($this->config['paths']['public'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $publicPath), DIRECTORY_SEPARATOR);
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function generateVideoPreview(string $videoPath, string $previewPath): bool
    {
        $ffmpeg = $this->ffmpegBinary();
        if ($ffmpeg === null) {
            return false;
        }

        $command = implode(' ', [
            escapeshellarg($ffmpeg),
            '-y',
            '-hide_banner',
            '-loglevel error',
            '-ss 00:00:01',
            '-i ' . escapeshellarg($videoPath),
            '-frames:v 1',
            '-vf ' . escapeshellarg('scale=min(1280\,iw):-2'),
            '-q:v 4',
            escapeshellarg($previewPath),
        ]);
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        return $exitCode === 0 && is_file($previewPath) && filesize($previewPath) > 0;
    }

    private function ffmpegBinary(): ?string
    {
        $configured = trim((string)($this->config['media']['ffmpeg_binary'] ?? getenv('FFMPEG_BINARY') ?: ''));
        $candidates = array_filter([
            $configured,
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            'ffmpeg',
        ]);

        foreach ($candidates as $candidate) {
            if (str_contains($candidate, DIRECTORY_SEPARATOR) && is_executable($candidate)) {
                return $candidate;
            }
            if ($candidate === 'ffmpeg') {
                return $candidate;
            }
        }

        return null;
    }

    private function classifyUpload(string $mimeType, string $extension): ?array
    {
        $fontFormats = [
            'woff2' => ['font/woff2', 'application/font-woff2', 'application/x-font-woff2', 'application/woff2', 'application/x-woff2', 'application/octet-stream'],
            'woff' => ['font/woff', 'application/font-woff', 'application/x-font-woff', 'application/woff', 'application/x-woff', 'application/octet-stream'],
            'ttf' => ['font/ttf', 'font/sfnt', 'application/font-sfnt', 'application/x-font-sfnt', 'application/x-font-ttf', 'application/x-font-truetype', 'application/octet-stream'],
            'otf' => ['font/otf', 'font/sfnt', 'application/font-sfnt', 'application/x-font-sfnt', 'application/x-font-otf', 'application/x-font-opentype', 'application/vnd.ms-opentype', 'application/octet-stream'],
        ];
        if (isset($fontFormats[$extension])) {
            return in_array($mimeType, $fontFormats[$extension], true)
                ? ['kind' => 'font', 'ext' => $extension, 'format' => $extension]
                : null;
        }

        $allowed = [
            'image/jpeg' => ['kind' => 'image', 'ext' => 'jpg'],
            'image/png' => ['kind' => 'image', 'ext' => 'png'],
            'image/gif' => ['kind' => 'image', 'ext' => 'gif'],
            'image/webp' => ['kind' => 'image', 'ext' => 'webp'],
            'video/mp4' => ['kind' => 'video', 'ext' => 'mp4'],
            'video/webm' => ['kind' => 'video', 'ext' => 'webm'],
            'video/ogg' => ['kind' => 'video', 'ext' => 'ogv'],
        ];

        return $allowed[$mimeType] ?? null;
    }

    private function sanitizeOriginalFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = $this->sanitizeText($filename, 255);
        $filename = preg_replace('/[^a-zA-Z0-9._ -]+/', '_', $filename) ?? '';
        $filename = trim($filename, " .\t\n\r\0\x0B");

        return $filename !== '' ? $filename : 'upload';
    }

    private function sanitizeText(string $text, int $maxLength): string
    {
        $text = trim($text);
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }
}
