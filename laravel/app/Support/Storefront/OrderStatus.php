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
            '0', 'pending' => ['label' => 'Chờ xử lý', 'class' => 'jm-account-status--wait'],
            '1', 'confirmed' => ['label' => 'Đã xác nhận', 'class' => 'jm-account-status--ok'],
            '2', 'packed' => ['label' => 'Đang giao', 'class' => 'jm-account-status--ship'],
            '3', 'completed' => ['label' => 'Hoàn thành', 'class' => 'jm-account-status--done'],
            '4', 'cancelled' => ['label' => 'Đã hủy', 'class' => 'jm-account-status--cancel'],
            default => [
                'label' => $listWaitLabel ? 'Đang chờ' : 'Chờ xử lý',
                'class' => 'jm-account-status--wait',
            ],
        };
    }

    public static function formatCreated(mixed $unix): string
    {
        if (is_string($unix) && ! is_numeric($unix)) {
            $parsed = strtotime($unix);

            return $parsed ? date('H:i d/m/Y', $parsed) : '';
        }
        $ts = (int) $unix;

        return $ts > 0 ? date('H:i d/m/Y', $ts) : '';
    }
}
