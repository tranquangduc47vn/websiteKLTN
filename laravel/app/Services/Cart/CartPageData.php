<?php

namespace App\Services\Cart;

/**
 * Dữ liệu trang /gio-hang sau khi giỏ đã được làm sạch + tính lại giá/tồn kho.
 *
 * `items` là các dòng giỏ kèm khóa chỉ dùng để hiển thị (`variants`, `product_url`),
 * KHÔNG được ghi ngược vào session.
 */
final class CartPageData
{
    /**
     * @param  array<string, array<string, mixed>>  $items
     * @param  list<string>  $notices  thông báo lỗi cần flash (theo thứ tự CI3)
     */
    public function __construct(
        public readonly array $items,
        public readonly int $totalItems,
        public readonly float $totalPrice,
        public readonly array $notices,
    ) {
    }
}
