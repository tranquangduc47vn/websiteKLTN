<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\Transaction;
use App\Support\Payment\BankTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Xác nhận chuyển khoản từ webhook SePay.
 *
 * Không cộng điểm, không ghi voucher, không trừ kho. Những việc đó đã xong lúc tạo đơn.
 * Webhook trùng chỉ được ghi nhận một lần.
 */
class SepayWebhookService
{
    /**
     * @return array{http: int, body: array{ok: bool, result?: string}}
     */
    public function handle(Request $request): array
    {
        if (! $this->authorized($request)) {
            $this->log('rejected', ['reason' => 'auth']);

            return $this->fail(401);
        }

        $payload = $request->json()->all();
        if ($payload === []) {
            $payload = $request->all();
        }
        if (! is_array($payload)) {
            $this->log('rejected', ['reason' => 'payload']);

            return $this->fail(422);
        }

        $gatewayId = $this->gatewayId($payload);
        $amountRaw = $payload['transferAmount'] ?? null;
        $type = strtolower(trim((string) ($payload['transferType'] ?? '')));
        $memo = trim((string) ($payload['content'] ?? ''));
        if ($memo === '') {
            $memo = trim((string) ($payload['description'] ?? ''));
        }
        $code = trim((string) ($payload['code'] ?? ''));

        if ($gatewayId === '' || ! is_numeric($amountRaw) || $type === '' || ($memo === '' && $code === '')) {
            $this->log('rejected', ['reason' => 'payload', 'gateway_id' => $gatewayId]);

            return $this->fail(422);
        }

        if ($type !== 'in') {
            $this->log('rejected', [
                'reason' => 'transfer_type',
                'gateway_id' => $gatewayId,
            ]);

            return $this->fail(422);
        }

        if (! $this->accountMatches($payload)) {
            $this->log('rejected', ['reason' => 'account', 'gateway_id' => $gatewayId]);

            return $this->fail(422);
        }

        $reference = BankTransfer::referenceFromMemo($code !== '' ? $code : $memo);
        if ($reference === null && $code !== '' && $memo !== '') {
            $reference = BankTransfer::referenceFromMemo($memo);
        }
        $received = BankTransfer::amountVnd($amountRaw);
        $paidAt = $this->paidAt($payload);

        if ($reference === null) {
            $this->log('ignored', [
                'reason' => 'reference',
                'gateway_id' => $gatewayId,
                'amount' => $received,
            ]);

            return $this->ok('ignored');
        }

        $result = 'ignored';
        DB::transaction(function () use ($reference, $gatewayId, $received, $paidAt, &$result) {
            $payment = Payment::query()->where('reference', $reference)->lockForUpdate()->first();
            if (! $payment && $gatewayId !== '') {
                $payment = Payment::query()->where('gateway_id', $gatewayId)->lockForUpdate()->first();
            }
            $order = $payment ? Transaction::query()->where('id', $payment->order_id)->lockForUpdate()->first() : null;
            if (! $payment || ! $order || $payment->method !== Payment::METHOD_BANK) {
                $result = 'unknown';

                return;
            }

            if ((string) $payment->gateway_id === $gatewayId || (string) $payment->status === BankTransfer::PAID) {
                $result = 'duplicate';

                return;
            }

            if ($order->status === Transaction::STATUS_CANCELLED) {
                $this->record($payment, BankTransfer::REVIEW, $gatewayId, $received, $paidAt);
                $result = 'review';

                return;
            }

            if (BankTransfer::amountVnd($order->amount) !== $received) {
                $this->record($payment, BankTransfer::MISMATCH, $gatewayId, $received, $paidAt);
                $result = 'mismatch';

                return;
            }

            if (in_array((string) $payment->status, [BankTransfer::REVIEW, BankTransfer::MISMATCH], true)) {
                $result = 'ignored';

                return;
            }

            $this->record($payment, BankTransfer::PAID, $gatewayId, $received, $paidAt);
            $result = 'paid';
        });

        $this->log($result, [
            'reason' => $result,
            'gateway_id' => $gatewayId,
            'reference' => $reference,
            'amount' => $received,
        ]);

        return $this->ok($result);
    }

    private function authorized(Request $request): bool
    {
        if (! config('sepay.enabled')) {
            return false;
        }

        $secret = (string) config('sepay.webhook_secret');
        if ($secret === '') {
            return false;
        }

        $header = trim((string) $request->header('Authorization', ''));
        if (! preg_match('/^Apikey\s+(\S+)$/i', $header, $match)) {
            return false;
        }

        return hash_equals($secret, $match[1]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function accountMatches(array $payload): bool
    {
        $expected = preg_replace('/\s+/', '', (string) config('sepay.account_number')) ?? '';
        if ($expected === '') {
            return true;
        }

        $given = preg_replace('/\s+/', '', (string) ($payload['accountNumber'] ?? '')) ?? '';

        return $given !== '' && hash_equals($expected, $given);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function gatewayId(array $payload): string
    {
        $id = $payload['id'] ?? null;
        if (! is_scalar($id)) {
            return '';
        }

        $id = trim((string) $id);

        return ctype_digit($id) ? $id : '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function paidAt(array $payload): int
    {
        $raw = trim((string) ($payload['transactionDate'] ?? ''));
        if ($raw !== '') {
            $stamp = strtotime($raw);
            if ($stamp !== false) {
                return $stamp;
            }
        }

        return time();
    }

    private function record(Payment $payment, string $status, string $gatewayId, int $amount, int $paidAt): void
    {
        $payment->status = $status;
        $payment->gateway_id = $gatewayId;
        $payment->received_amount = $amount;
        if ($status === BankTransfer::PAID) {
            $payment->paid_at = date('Y-m-d H:i:s', $paidAt);
        }
        $payment->save();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $result, array $context): void
    {
        Log::info('payment.webhook', array_merge(['provider' => 'sepay', 'result' => $result], $context));
    }

    /**
     * @return array{http: int, body: array{ok: bool}}
     */
    private function fail(int $http): array
    {
        return ['http' => $http, 'body' => ['ok' => false]];
    }

    /**
     * @return array{http: int, body: array{ok: bool, result: string}}
     */
    private function ok(string $result): array
    {
        return ['http' => 200, 'body' => ['ok' => true, 'result' => $result]];
    }
}
