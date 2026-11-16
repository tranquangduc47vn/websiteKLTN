<?php

namespace App\Support\Storefront;

/**
 * B4 - Bang mau danh muc san pham.
 *
 * Port nguyen van tu CI3 application/helpers/product_helper.php:
 *   product_default_colors() + product_master_colors()
 *
 * Uu tien bang colors (status = active, sort_order ASC, name ASC) neu co du lieu;
 * neu khong thi dung bang mac dinh ben duoi.
 */
class ColorPalette
{
    /** Mau mac dinh - trung ten voi CI3 product_default_colors(). */
    public const DEFAULTS = [
        "Den"        => "#000000",
        "Trang"      => "#ffffff",
        "Do"         => "#dd021b",
        "Vang"       => "#fcd535",
        "Xanh duong" => "#2196f3",
        "Xanh la"    => "#4caf50",
        "Hong"       => "#ffb6c1",
        "Xam"        => "#808080",
        "Nau"        => "#8b5a2b",
        "Kem"        => "#f5f5dc",
        "Cam"        => "#ff9800",
        "Tim"        => "#9c27b0",
    ];

    /**
     * Ten mau dang de doc cho nguoi dung (dung trong b? loc catalog).
     *
     * @return array<int,string> [1 => "Den", 2 => "Trang", ...]
     */
    public static function names(): array
    {
        return array_keys(self::fromMaster());
    }

    /**
     * Danh sach mau hien thi o bo loc: [ten => hex].
     * Uu tien master_colors; neu bang trong thi tra bang mac dinh.
     *
     * @return array<string,string>
     */
    public static function fromMaster(): array
    {
        $rows = \App\Models\MasterColor::query()
            ->where("status", \App\Models\MasterColor::STATUS_ACTIVE)
            ->orderBy("sort_order")
            ->orderBy("name")
            ->get();

        if ($rows->isEmpty()) {
            return self::DEFAULTS;
        }

        $colors = [];
        foreach ($rows as $row) {
            $colors[(string) $row->name] = (string) $row->hex_code;
        }

        return $colors;
    }

    /**
     * Mau sac cho JS/thuoc tinh: luon tra bang mac dinh (CI3 cung co mot ban
     * mac dinh rieng dung de render mau trong view).
     *
     * @return array<string,string>
     */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }


    /**
     * Doan mau sang hex. Port chuoi if/elseif tu CI3
     * application/views/site/product/view.php, giu them ten khong dau.
     */
    public static function hexFor(string $color): string
    {
        $c = mb_strtolower(trim($color), "UTF-8");

        if (str_contains($c, "đen") || str_contains($c, "den") || str_contains($c, "black")) { return "#000000"; }
        if (str_contains($c, "trắng") || str_contains($c, "trang") || str_contains($c, "white")) { return "#ffffff"; }
        if (str_contains($c, "đỏ") || str_contains($c, "do") || str_contains($c, "red")) { return "#ff0000"; }
        if (str_contains($c, "vàng") || str_contains($c, "vang") || str_contains($c, "yellow")) { return "#ffcc00"; }
        if (str_contains($c, "cam") || str_contains($c, "orange")) { return "#ff781f"; }
        if (str_contains($c, "xanh dương") || str_contains($c, "xanh duong") || str_contains($c, "xanh lam") || str_contains($c, "blue")) { return "#0066cc"; }
        if (str_contains($c, "xanh lá") || str_contains($c, "xanh la") || str_contains($c, "green")) { return "#00a651"; }
        if (str_contains($c, "hồng") || str_contains($c, "hong") || str_contains($c, "pink")) { return "#ffb6c1"; }
        if (str_contains($c, "xám") || str_contains($c, "xam") || str_contains($c, "ghi") || str_contains($c, "gray")) { return "#808080"; }
        if (str_contains($c, "nâu") || str_contains($c, "nau") || str_contains($c, "brown")) { return "#8b5a2b"; }
        if (str_contains($c, "kem") || str_contains($c, "be") || str_contains($c, "beige") || str_contains($c, "cream")) { return "#f5f5dc"; }

        return "#e0e0e0";
    }

    /** Mau nen dung khi khong nhan dang duoc ten mau. */
    public static function fallbackHex(): string
    {
        return "#cccccc";
    }
}
