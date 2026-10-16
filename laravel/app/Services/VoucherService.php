<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUse;
use App\Support\Storefront\Loyalty;
use Illuminate\Support\Facades\DB;

/**
 * Port application/models/Voucher_model.php (validate_for_checkout / record_use / release_for_transaction).
 */
class VoucherService
{
    /**
     * @return array{ok: bool, message: string, voucher: ?Voucher, discount: int}
     */
    public function validateForCheckout(string $code, int $userId, int $cartTotal, bool $lock = false): array
    {
        $code = Loyalty::normalizeVoucherCode($code);
        $fail = fn (string $message) => ['ok' => false, 'message' => $message, 'voucher' => null, 'discount' => 0];

        if ($code === '') {
            return $fail('Vui lòng nhập mã voucher.');
        }
        if ($userId <= 0) {
            return $fail('Bạn cần đăng nhập để sử dụng mã giảm giá.');
        }
        if ($cartTotal <= 0) {
            return $fail('Giỏ hàng không hợp lệ.');
        }

        $query = Voucher::query()->where('code', $code);
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();
        if (! $row) {
            return $fail('Mã voucher không tồn tại hoặc đã hết hiệu lực.');
        }
        if ((int) $row->is_active !== 1) {
            return $fail('Mã voucher đang tạm khóa.');
        }

        $now = time();
        if ((int) $row->valid_from > 0 && $now < (int) $row->valid_from) {
            return $fail('Mã voucher chưa đến thời gian áp dụng.');
        }
        if ((int) $row->valid_to > 0 && $now > (int) $row->valid_to) {
            return $fail('Mã voucher đã hết hạn.');
        }
        if ((int) $row->user_id > 0 && (int) $row->user_id !== $userId) {
            return $fail('Mã voucher không dành cho tài khoản của bạn.');
        }
        if ((int) $row->min_order_amount > 0 && $cartTotal < (int) $row->min_order_amount) {
            return $fail('Đơn hàng tối thiểu ' . number_format((int) $row->min_order_amount, 0, ',', '.') . ' ₫ để dùng mã này.');
        }

        $limit = (int) $row->usage_limit;
        if ($limit > 0 && (int) $row->used_count >= $limit) {
            return $fail('Mã voucher đã hết lượt sử dụng.');
        }

        $user = User::query()->find($userId);
        $userTier = $user && isset($user->loyalty_tier) ? (string) $user->loyalty_tier : 'member';
        if (! Loyalty::tierMeets($userTier, $row->tier_min)) {
            return $fail('Mã này dành cho hạng ' . Loyalty::tierLabel($row->tier_min) . ' trở lên.');
        }

        $perUser = (int) $row->per_user_limit;
        if ($perUser > 0) {
            $usedByUser = VoucherUse::query()
                ->where('voucher_id', (int) $row->id)
                ->where('user_id', $userId)
                ->count();
            if ($usedByUser >= $perUser) {
                return $fail('Bạn đã sử dụng hết lượt cho mã này.');
            }
        }

        $discount = Loyalty::voucherDiscount($row, $cartTotal);
        if ($discount <= 0) {
            return $fail('Mã voucher không áp dụng được cho đơn này.');
        }

        return ['ok' => true, 'message' => '', 'voucher' => $row, 'discount' => $discount];
    }

    /**
     * CI3 Voucher_model::list_available_for_user() — gợi ý mã trên trang tài khoản.
     *
     * @return \Illuminate\Support\Collection<int, Voucher>
     */
    public function listAvailableForUser(int $userId): \Illuminate\Support\Collection
    {
        if ($userId <= 0) {
            return collect();
        }

        $user = User::query()->find($userId);
        $userTier = $user && isset($user->loyalty_tier) ? (string) $user->loyalty_tier : 'member';
        $now = time();

        $rows = Voucher::query()
            ->where('is_active', 1)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', 0)->orWhere('user_id', $userId);
            })
            ->orderByDesc('id')
            ->get();

        return $rows->filter(function (Voucher $row) use ($now, $userTier) {
            if ((int) $row->valid_from > 0 && $now < (int) $row->valid_from) {
                return false;
            }
            if ((int) $row->valid_to > 0 && $now > (int) $row->valid_to) {
                return false;
            }
            $limit = (int) $row->usage_limit;
            if ($limit > 0 && (int) $row->used_count >= $limit) {
                return false;
            }

            return Loyalty::tierMeets($userTier, $row->tier_min);
        })->values();
    }

    public function recordUse(int $voucherId, int $userId, int $transactionId, int $discountAmount): void
    {
        VoucherUse::query()->create([
            'voucher_id' => $voucherId,
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'discount_amount' => $discountAmount,
            'created' => time(),
        ]);

        DB::table('voucher')->where('id', $voucherId)->update([
            'used_count' => DB::raw('used_count + 1'),
        ]);
    }

    /** CI3 Voucher_model::release_for_transaction(). */
    public function releaseForTransaction(int $transactionId): void
    {
        if ($transactionId <= 0) {
            return;
        }

        $uses = VoucherUse::query()->where('transaction_id', $transactionId)->get();
        foreach ($uses as $use) {
            $voucher = Voucher::query()->find((int) $use->voucher_id);
            if ($voucher) {
                $voucher->used_count = max(0, (int) $voucher->used_count - 1);
                $voucher->save();
            }
        }

        VoucherUse::query()->where('transaction_id', $transactionId)->delete();
    }
}
