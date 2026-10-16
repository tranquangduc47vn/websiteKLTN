<?php

namespace App\Support\Storefront;

/**
 * B4 - Slug tieng Viet, port nguyen van tu CI3:
 *   common_helper.php::covert_vi_to_en()
 *   seo_url_helper.php::seo_slugify()
 *
 * Giu NGUYEN thuat toan de URL cu khong bi hong (DB da chua san URL slug cu).
 */
class Slug
{
    /** Bang doi ky tu giong tuyet doi CI3 (co dau -> khong dau). */
    private const UNICODE_MAP = [
        'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
        'd' => 'đ',
        'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
        'i' => 'í|ì|ỉ|ĩ|ị',
        'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
        'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
        'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
        'A' => 'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
        'D' => 'Đ',
        'E' => 'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
        'I' => 'Í|Ì|Ỉ|Ĩ|Ị',
        'O' => 'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
        'U' => 'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
        'Y' => 'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
    ];

    /** covert_vi_to_en() cua CI3. */
    public static function viToEn(mixed $str): string
    {
        if (! $str) {
            return '';
        }

        foreach (self::UNICODE_MAP as $nonUnicode => $uni) {
            $str = preg_replace('/(' . $uni . ')/i', $nonUnicode, (string) $str);
        }

        $str = trim((string) $str);
        $str = str_replace(' ', '-', $str);
        $str = str_replace(',', '', $str);

        return $str;
    }

    /** seo_slugify() cua CI3. */
    public static function make(mixed $str): string
    {
        if ($str === null || $str === '') {
            return '';
        }

        $str = self::viToEn((string) $str);
        $str = strtolower($str);
        $str = preg_replace('/[^a-z0-9-]+/', '-', $str);
        $str = preg_replace('/-+/', '-', $str);

        return trim((string) $str, '-');
    }

    /** Sinh slug cho noi dung khi thieu (CI3: Content_page_model::slugify). */
    public static function fallbackContent(mixed $text): string
    {
        $text = trim((string) $text);

        return $text === '' ? 'noi-dung' : (self::make($text) ?: 'noi-dung');
    }
}
