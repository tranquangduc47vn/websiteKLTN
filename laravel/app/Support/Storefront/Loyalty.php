<?php

namespace App\Support\Storefront;

/**
 * Port application/helpers/loyalty_helper.php — quy tắc cố định, không đọc config.
 */
final class Loyalty
{
    /** @return array<string, int> */
    public static function tierOrder(): array
    {
        return ['member' => 0, 'silver' => 1, 'gold' => 2, 'diamond' => 3];
    }

    public static function tierLabel(mixed $tier): string
    {
        $labels = [
            'member' => 'Thành viên',
            'silver' => 'Bạc',
            'gold' => 'Vàng',
            'diamond' => 'Kim cương',
        ];
        $tier = strtolower((string) $tier);

        return $labels[$tier] ?? 'Thành viên';
    }

    public static function computeTier(int $completedOrders, int $lifetimeSpend): string
    {
        if ($completedOrders >= 25 || $lifetimeSpend >= 50000000) {
            return 'diamond';
        }
        if ($completedOrders >= 10 || $lifetimeSpend >= 20000000) {
            return 'gold';
        }
        if ($completedOrders >= 3 || $lifetimeSpend >= 5000000) {
            return 'silver';
        }

        return 'member';
    }

    public static function tierMeets(mixed $userTier, mixed $requiredTier): bool
    {
        $order = self::tierOrder();
        $userTier = $userTier === 'vip' ? 'diamond' : (string) $userTier;
        $requiredTier = $requiredTier === 'vip' ? 'diamond' : (string) $requiredTier;
        $userTier = isset($order[$userTier]) ? $userTier : 'member';
        $requiredTier = isset($order[$requiredTier]) ? $requiredTier : 'member';

        return $order[$userTier] >= $order[$requiredTier];
    }

    /** 1 điểm / 10.000đ thanh toán (sau giảm giá). */
    public static function pointsPerVnd(): int
    {
        return 10000;
    }

    public static function tierPointMultiplier(mixed $tier): float
    {
        return match (strtolower((string) $tier)) {
            'silver' => 1.1,
            'gold' => 1.25,
            'diamond' => 1.5,
            default => 1.0,
        };
    }

    public static function pointsForAmount(int $finalAmount, mixed $tier): int
    {
        if ($finalAmount <= 0) {
            return 0;
        }

        $base = (int) floor($finalAmount / self::pointsPerVnd());

        return (int) max(0, floor($base * self::tierPointMultiplier($tier)));
    }

    public static function normalizeVoucherCode(mixed $code): string
    {
        $code = strtoupper(trim((string) $code));
        $code = preg_replace('/\s+/', '', $code) ?? '';

        return $code;
    }

    /**
     * CI3 loyalty_calc_voucher_discount(): percent mới áp max_discount; luôn cắt theo cart_total.
     */
    public static function voucherDiscount(object $voucher, int $cartTotal): int
    {
        if ($cartTotal <= 0) {
            return 0;
        }

        $type = (string) ($voucher->discount_type ?? 'fixed');
        $value = (int) ($voucher->discount_value ?? 0);

        if ($type === 'percent') {
            $discount = (int) round($cartTotal * $value / 100);
            $cap = (int) ($voucher->max_discount ?? 0);
            if ($cap > 0 && $discount > $cap) {
                $discount = $cap;
            }
        } else {
            $discount = $value;
        }

        if ($discount > $cartTotal) {
            $discount = $cartTotal;
        }

        return max(0, $discount);
    }

    public static function moneyFmt(int|float $amount): string
    {
        return number_format((int) $amount, 0, ',', '.') . ' ₫';
    }
}
