<?php

namespace App\Support\Admin;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Lưu ảnh sản phẩm vào thư mục CI3 upload/product.
 * Chỉ nhận ảnh, tên file do server sinh, không ghi đè file đang có.
 */
class ProductImageStore
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
        $configured = (string) config('shop.product_upload_path', '');
        if ($configured !== '') {
            return rtrim($configured, '/\\');
        }

        return dirname(base_path()) . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'product';
    }

    public function store(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'jpe' || $ext === 'jfif') {
            $ext = 'jpg';
        }
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            throw new RuntimeException('Chỉ hỗ trợ JPG, PNG, GIF, WEBP, BMP (tối đa 5MB).');
        }
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Chỉ hỗ trợ JPG, PNG, GIF, WEBP, BMP (tối đa 5MB).');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException('Ảnh vượt quá 5MB.');
        }

        $dir = $this->directory();
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('Không tạo được thư mục ảnh sản phẩm.');
        }

        do {
            $name = 'p' . date('YmdHis') . bin2hex(random_bytes(4)) . '.' . $ext;
            $path = $dir . DIRECTORY_SEPARATOR . $name;
        } while (is_file($path));

        $file->move($dir, $name);

        return $name;
    }

    public function sanitize(mixed $name): string
    {
        $name = basename(str_replace('\\', '/', (string) $name));
        if ($name === '' || str_contains($name, '..')) {
            return '';
        }

        return $name;
    }

    public function delete(string $filename): void
    {
        $filename = $this->sanitize($filename);
        if ($filename === '') {
            return;
        }
        $path = $this->directory() . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
