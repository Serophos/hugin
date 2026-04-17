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
    public function __construct(private Database $db, private array $config)
    {
    }

    public function storeUploadedFile(?array $file, int $uploadedByUserId, string $label = ''): ?array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(__('errors.upload_failed', [], 'Upload failed.'));
        }

        $maxSize = (int)($this->config['upload']['max_size_bytes'] ?? 52428800);
        if (($file['size'] ?? 0) > $maxSize) {
            throw new RuntimeException(__('errors.uploaded_file_too_large', [], 'Uploaded file is too large.'));
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException(__('errors.invalid_uploaded_file', [], 'Invalid uploaded file.'));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = (string)finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        $allowed = [
            'image/jpeg' => ['kind' => 'image', 'ext' => 'jpg'],
            'image/png' => ['kind' => 'image', 'ext' => 'png'],
            'image/gif' => ['kind' => 'image', 'ext' => 'gif'],
            'image/webp' => ['kind' => 'image', 'ext' => 'webp'],
            'video/mp4' => ['kind' => 'video', 'ext' => 'mp4'],
            'video/webm' => ['kind' => 'video', 'ext' => 'webm'],
            'video/ogg' => ['kind' => 'video', 'ext' => 'ogv'],
        ];

        if (!isset($allowed[$mimeType])) {
            throw new RuntimeException(__('errors.unsupported_file_type', [], 'Unsupported file type. Allowed: JPG, PNG, GIF, WEBP, MP4, WEBM, OGG.'));
        }

        $info = $allowed[$mimeType];
        $subDir = date('Y/m');
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
        $originalName = (string)($file['name'] ?? $filename);
        $name = trim($label) !== '' ? trim($label) : pathinfo($originalName, PATHINFO_FILENAME);

        $this->db->execute(
            'INSERT INTO media_assets (name, original_name, mime_type, file_size, media_kind, file_path, uploaded_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $name,
                $originalName,
                $mimeType,
                (int)($file['size'] ?? 0),
                $info['kind'],
                $publicPath,
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
}
