<?php

namespace App\Services\Admin;

use App\Support\Admin\UploadImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Logo website. Ghi cùng file CI3 đang đọc:
 * upload/branding/site-logo.*, application/config/branding.local.php, và bản copy upload/logo.png.
 */
class SiteLogoService
{
    public function __construct(private readonly UploadImage $images) {}

    public function relativePath(): string
    {
        $configured = $this->configuredRelativePath();
        if ($this->fileExists($configured)) {
            return $configured;
        }

        // Storefront (Shop::logoUrl / SHOP_LOGO) vẫn đọc upload/logo.png — dùng làm fallback preview.
        if ($configured !== 'logo.png' && $this->fileExists('logo.png')) {
            return 'logo.png';
        }

        return $configured;
    }

    public function exists(): bool
    {
        return $this->fileExists($this->relativePath());
    }

    private function configuredRelativePath(): string
    {
        $config = dirname(base_path()) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'branding.local.php';
        if (! is_file($config)) {
            return 'logo.png';
        }

        $raw = (string) file_get_contents($config);
        if (! preg_match('/\$site_logo\s*=\s*\'((?:\\\\\'|[^\'])*)\'/', $raw, $match)) {
            return 'logo.png';
        }
        $path = str_replace(["\\'", '\\'], ["'", '/'], $match[1]);
        $path = trim($path);
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            return 'logo.png';
        }

        return $path;
    }

    private function fileExists(string $relative): bool
    {
        $path = UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        return is_file($path) && filesize($path) > 0;
    }

    /**
     * @return array{ok: bool, message: string, path?: string}
     */
    public function save(UploadedFile $file): array
    {
        $dir = UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . 'branding';
        try {
            $name = $this->images->store($file, $dir, 'site-logo-', 5 * 1024 * 1024);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $relative = 'branding/' . $name;
        $previous = $this->configuredRelativePath();
        if (! $this->writeConfig($relative)) {
            $this->images->remove($dir, $name);

            return ['ok' => false, 'message' => 'Đã upload ảnh nhưng không ghi được cấu hình logo.'];
        }

        $source = $dir . DIRECTORY_SEPARATOR . $name;
        $legacy = UploadImage::uploadRoot() . DIRECTORY_SEPARATOR . 'logo.png';
        if (! is_file($source) || ! @copy($source, $legacy)) {
            return ['ok' => false, 'message' => 'Không ghi được bản copy logo.png.'];
        }

        if ($previous !== $relative && str_starts_with($previous, 'branding/')) {
            $this->images->remove($dir, basename($previous));
        }

        return ['ok' => true, 'message' => 'Cập nhật logo thành công.', 'path' => $relative];
    }

    private function writeConfig(string $relative): bool
    {
        $config = dirname(base_path()) . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'branding.local.php';
        $content = "<?php\n"
            . "defined('BASEPATH') OR exit('No direct script access allowed');\n\n"
            . '$site_logo = ' . var_export($relative, true) . ";\n";

        return file_put_contents($config, $content, LOCK_EX) !== false;
    }
}
