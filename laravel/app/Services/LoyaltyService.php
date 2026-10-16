<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPointLog;
use App\Support\Storefront\Loyalty;

/**
 * Port application/models/Loyalty_model.php — B6 chỉ cần on_order_cancelled
 * (điểm được cộng khi admin chuyển status 3, không phải lúc checkout).
 */
class LoyaltyService
{
    public function __construct(private readonly VoucherService $vouchers)
    {
    }

    /**
     * CI3 Loyalty_model::get_profile() — tự cập nhật hạng nếu tính lại khác hạng đang lưu.
     *
     * @return object{points: int, lifetime_spend: int, completed_orders: int, tier: string, tier_label: string}|null
     */
    public function getProfile(User $user): ?object
    {
        $tier = (string) ($user->loyalty_tier ?: 'member');
        $completed = (int) $user->loyalty_completed_orders;
        $spend = (int) $user->loyalty_lifetime_spend;
        $computed = Loyalty::computeTier($completed, $spend);
        if ($computed !== $tier) {
            $user->loyalty_tier = $computed;
            $user->save();
            $tier = $computed;
        }

        return (object) [
            'points' => (int) $user->loyalty_points,
            'lifetime_spend' => $spend,
            'completed_orders' => $completed,
            'tier' => $tier,
            'tier_label' => Loyalty::tierLabel($tier),
        ];
    }

    /** CI3 Loyalty_model::credit_order_completed() — gọi khi admin chuyển đơn sang status 3. */
    public function creditOrderCompleted(Transaction $transaction): void
    {
        $userId = (int) $transaction->user_id;
        $txId = (int) $transaction->id;
        if ($userId <= 0 || $this->logExists($txId, UserPointLog::TYPE_EARN)) {
            return;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        $finalAmount = (int) $transaction->amount;
        $tier = (string) ($user->loyalty_tier ?: 'member');
        $points = Loyalty::pointsForAmount($finalAmount, $tier);
        $completed = (int) $user->loyalty_completed_orders + 1;
        $spend = (int) $user->loyalty_lifetime_spend + $finalAmount;

        $user->loyalty_completed_orders = $completed;
        $user->loyalty_lifetime_spend = $spend;
        $user->loyalty_points = (int) $user->loyalty_points + $points;
        $user->loyalty_tier = Loyalty::computeTier($completed, $spend);
        $user->save();

        if ($points > 0) {
            UserPointLog::query()->create([
                'user_id' => $userId,
                'points' => $points,
                'type' => UserPointLog::TYPE_EARN,
                'transaction_id' => $txId,
                'note' => 'Tích điểm đơn hoàn thành #' . $txId,
                'created' => time(),
            ]);
        }
    }

    public function onOrderCancelled(Transaction $transaction, bool $wasCompleted): void
    {
        if ($wasCompleted) {
            $this->reverseOrderCompleted($transaction);
        }

        $this->vouchers->releaseForTransaction((int) $transaction->id);
    }

    public function reverseOrderCompleted(Transaction $transaction): void
    {
        $userId = (int) $transaction->user_id;
        $txId = (int) $transaction->id;
        if ($userId <= 0) {
            return;
        }
        if (! $this->logExists($txId, UserPointLog::TYPE_EARN)) {
            return;
        }
        if ($this->logExists($txId, UserPointLog::TYPE_REVERSE)) {
            return;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        $earn = UserPointLog::query()
            ->where('transaction_id', $txId)
            ->where('type', UserPointLog::TYPE_EARN)
            ->first();
        $points = $earn ? (int) $earn->points : 0;
        $finalAmount = (int) $transaction->amount;
        $completed = max(0, (int) $user->loyalty_completed_orders - 1);
        $spend = max(0, (int) $user->loyalty_lifetime_spend - $finalAmount);
        $newTier = Loyalty::computeTier($completed, $spend);

        $user->loyalty_completed_orders = $completed;
        $user->loyalty_lifetime_spend = $spend;
        $user->loyalty_points = max(0, (int) $user->loyalty_points - $points);
        $user->loyalty_tier = $newTier;
        $user->save();

        UserPointLog::query()->create([
            'user_id' => $userId,
            'points' => -$points,
            'type' => UserPointLog::TYPE_REVERSE,
            'transaction_id' => $txId,
            'note' => 'Hoàn tích điểm do hủy đơn #' . $txId,
            'created' => time(),
        ]);
    }

    private function logExists(int $transactionId, string $type): bool
    {
        return UserPointLog::query()
            ->where('transaction_id', $transactionId)
            ->where('type', $type)
            ->exists();
    }
}
