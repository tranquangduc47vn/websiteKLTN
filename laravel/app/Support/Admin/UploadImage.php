<?php

namespace App\Support\Admin;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Ảnh admin ghi vào thư mục upload/ ở gốc webshop, cùng chỗ CI3 đang dùng.
 */
class UploadImage
{
    private const ALLOWED_EXT = ['gif', 'jpg', 'jpeg', 'png', 'webp'];

    private const ALLOWED_MIME = [
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public static function uploadRoot(): string
    {
        return dirname(base_path()) . DIRECTORY_SEPARATOR . 'upload';
    }

    public function store(UploadedFile $file, string $directory, string $prefix, int $maxBytes = 5242880): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'jpe' || $ext === 'jfif') {
            $ext = 'jpg';
        }
        if (! in_array($ext, self::ALLOWED_EXT, true) || ! in_array((string) $file->getMimeType(), self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Chỉ hỗ trợ JPG, PNG, GIF, WEBP.');
        }
        if ($file->getSize() > $maxBytes) {
            throw new RuntimeException('Ảnh vượt quá dung lượng cho phép.');
        }

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Không tạo được thư mục ảnh.');
        }

        do {
            $name = $prefix . date('YmdHis') . bin2hex(random_bytes(4)) . '.' . $ext;
            $path = $directory . DIRECTORY_SEPARATOR . $name;
        } while (is_file($path));

        $file->move($directory, $name);

        return $name;
    }

    public function remove(string $directory, ?string $filename): void
    {
        $filename = basename(str_replace('\\', '/', (string) $filename));
        if ($filename === '' || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,180}\.(?:gif|jpe?g|png|webp)$/i', $filename)) {
            return;
        }
        if (in_array(strtolower($filename), ['logo.png', 'default.jpg', 'no-image.jpg'], true)) {
            return;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        $realDir = realpath($directory);
        $real = is_file($path) ? realpath($path) : false;
        if (! $realDir || ! $real || ! str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            return;
        }

        unlink($real);
    }
}
