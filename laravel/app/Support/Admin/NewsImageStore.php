<?php

namespace App\Support\Admin;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Ảnh tin tức, cùng thư mục CI3 upload/news.
 */
class NewsImageStore
{
    private const ALLOWED_EXT = ['gif', 'jpg', 'jpeg', 'png', 'webp', 'bmp'];

    private const ALLOWED_MIME = [
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/bmp',
        'image/x-ms-bmp',
    ];

    public function directory(): string
    {
        return dirname(base_path()) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'news';
    }

    public function store(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'jpe' || $ext === 'jfif') {
            $ext = 'jpg';
        }
        if (! in_array($ext, self::ALLOWED_EXT, true) || ! in_array((string) $file->getMimeType(), self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Chỉ hỗ trợ JPG, PNG, GIF, WEBP, BMP (tối đa 5MB).');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException('Ảnh vượt quá 5MB.');
        }

        $dir = $this->directory();
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Không tạo được thư mục ảnh tin tức.');
        }

        do {
            $name = 'n' . date('YmdHis') . bin2hex(random_bytes(4)) . '.' . $ext;
            $path = $dir . DIRECTORY_SEPARATOR . $name;
        } while (is_file($path));

        $file->move($dir, $name);

        return $name;
    }

    public function delete(string $filename): void
    {
        $filename = basename(str_replace('\\', '/', $filename));
        if ($filename === '' || $filename === 'default.jpg' || ! preg_match('/^n\d{14}[a-f0-9]{8}\./', $filename)) {
            return;
        }
        $path = $this->directory() . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
}
