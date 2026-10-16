<?php

namespace App\Support\Storefront;

/**
 * Port application/helpers/vn_address_helper.php.
 * Dữ liệu: assets/site/data/vn-hanh-chinh.json (cùng file CI3).
 */
final class VnAddress
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $provinces = null;

    /** @return list<array<string, mixed>> */
    public static function provinces(): array
    {
        if (self::$provinces !== null) {
            return self::$provinces;
        }

        $path = self::dataPath();
        if ($path === '' || ! is_readable($path)) {
            return self::$provinces = [];
        }

        $raw = json_decode((string) file_get_contents($path), true);

        return self::$provinces = is_array($raw) ? $raw : [];
    }

    public static function dataPath(): string
    {
        $configured = (string) config('shop.vn_address_path', '');
        if ($configured !== '' && is_readable($configured)) {
            return $configured;
        }

        $candidates = [
            base_path('../assets/site/data/vn-hanh-chinh.json'),
            dirname(base_path()) . DIRECTORY_SEPARATOR . 'assets/site/data/vn-hanh-chinh.json',
            public_path('assets/site/data/vn-hanh-chinh.json'),
        ];
        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return '';
    }

    public static function jsonUrl(): string
    {
        return Shop::assetUrl('data/vn-hanh-chinh.json');
    }

    /**
     * @return array{province_name: string, district_name: string, ward_name: string}|false
     */
    public static function lookup(mixed $provinceId, mixed $districtId, mixed $wardId): array|false
    {
        $provinceId = trim((string) $provinceId);
        $districtId = trim((string) $districtId);
        $wardId = trim((string) $wardId);
        if ($provinceId === '' || $districtId === '' || $wardId === '') {
            return false;
        }

        foreach (self::provinces() as $province) {
            if ((string) ($province['Id'] ?? '') !== $provinceId) {
                continue;
            }
            foreach ($province['Districts'] ?? [] as $district) {
                if ((string) ($district['Id'] ?? '') !== $districtId) {
                    continue;
                }
                foreach ($district['Wards'] ?? [] as $ward) {
                    if ((string) ($ward['Id'] ?? '') === $wardId) {
                        return [
                            'province_name' => (string) ($province['Name'] ?? ''),
                            'district_name' => (string) ($district['Name'] ?? ''),
                            'ward_name' => (string) ($ward['Name'] ?? ''),
                        ];
                    }
                }

                return false;
            }

            return false;
        }

        return false;
    }

    public static function formatLine(mixed $provinceId, mixed $districtId, mixed $wardId, mixed $note): string
    {
        $lookup = self::lookup($provinceId, $districtId, $wardId);
        if ($lookup === false) {
            return '';
        }

        $note = trim(preg_replace('/\s+/u', ' ', (string) $note) ?? '');
        $parts = [];
        if ($note !== '') {
            $parts[] = $note;
        }
        $parts[] = $lookup['ward_name'];
        $parts[] = $lookup['district_name'];
        $parts[] = $lookup['province_name'];

        return implode(', ', $parts);
    }

    public static function resetCache(): void
    {
        self::$provinces = null;
    }
}
