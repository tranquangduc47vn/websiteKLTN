<?php

namespace App\Support\Payment;

use App\Models\Transaction;

/**
 * Mã nội dung chuyển khoản. Không chứa tên, điện thoại hay email.
 */
class BankTransfer
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const REVIEW = 'review';
    public const MISMATCH = 'mismatch';

    public static function referenceFor(int $transactionId): string
    {
        return 'WEB' . $transactionId;
    }

    public static function referenceFromMemo(string $memo): ?string
    {
        $memo = strtoupper($memo);
        if (! preg_match('/WEB[0-9]+/', $memo, $match)) {
            return null;
        }

        return $match[0];
    }

    public static function amountVnd(mixed $amount): int
    {
        return (int) round((float) $amount);
    }

    public static function isTransfer(Transaction $transaction): bool
    {
        return $transaction->isBankTransfer();
    }
}
