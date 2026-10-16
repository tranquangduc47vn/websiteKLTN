<?php

namespace App\Support\Storefront;

/** Nhãn/class trạng thái đơn — khớp views/site/user/index.php + order_detail.php. */
final class OrderStatus
{
    /**
     * @return array{label: string, class: string}
     */
    public static function display(mixed $status, bool $listWaitLabel = false): array
    {
        return match ((string) $status) {
            '0' => ['label' => 'Chờ xử lý', 'class' => 'jm-account-status--wait'],
            '1' => ['label' => 'Đã xác nhận', 'class' => 'jm-account-status--ok'],
            '2' => ['label' => 'Đang giao', 'class' => 'jm-account-status--ship'],
            '3' => ['label' => 'Hoàn thành', 'class' => 'jm-account-status--done'],
            '4' => ['label' => 'Đã hủy', 'class' => 'jm-account-status--cancel'],
            default => [
                'label' => $listWaitLabel ? 'Đang chờ' : 'Chờ xử lý',
                'class' => 'jm-account-status--wait',
            ],
        };
    }

    public static function formatCreated(mixed $unix): string
    {
        $ts = (int) $unix;

        return $ts > 0 ? date('H:i d/m/Y', $ts) : '';
    }
}
