<?php

declare(strict_types=1);

namespace App\Helpers;

use DomainException;
use finfo;

final class Upload
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    private const MAX_BYTES = 2097152;

    public static function image(array $file, string $directory, string $publicPrefix): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new DomainException('Tải ảnh lên không thành công.');
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new DomainException('Ảnh phải có kích thước tối đa 2MB.');
        }
        $temporary = (string) ($file['tmp_name'] ?? '');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new DomainException('Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new DomainException('Không thể tạo thư mục lưu ảnh.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::MIME_EXTENSIONS[$mime];
        $destination = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($temporary, $destination)) {
            throw new DomainException('Không thể lưu ảnh tải lên.');
        }
        return [
            'path' => rtrim($publicPrefix, '/') . '/' . $name,
            'original_name' => mb_substr(basename((string) ($file['name'] ?? 'image')), 0, 255),
            'mime_type' => $mime,
            'file_size' => $size,
        ];
    }

    public static function images(array $files, string $directory, string $publicPrefix, int $maxFiles = 5): array
    {
        if (!isset($files['name']) || !is_array($files['name'])) {
            return [self::image($files, $directory, $publicPrefix)];
        }
        if (count($files['name']) > $maxFiles) {
            throw new DomainException("Chỉ được tải tối đa {$maxFiles} ảnh.");
        }
        $saved = [];
        try {
            foreach (array_keys($files['name']) as $index) {
                $saved[] = self::image([
                    'name' => $files['name'][$index], 'type' => $files['type'][$index] ?? '',
                    'tmp_name' => $files['tmp_name'][$index], 'error' => $files['error'][$index],
                    'size' => $files['size'][$index],
                ], $directory, $publicPrefix);
            }
        } catch (\Throwable $exception) {
            foreach ($saved as $image) {
                $candidate = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . basename((string) $image['path']);
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
            throw $exception;
        }
        return $saved;
    }
}
