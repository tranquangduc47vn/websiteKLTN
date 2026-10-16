<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Checkout\CheckoutResult;
use App\Services\CheckoutService;
use App\Support\Storefront\Csrf;
use App\Support\Storefront\Flash;
use App\Support\Storefront\Urls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * B6 — Checkout / đặt hàng. Port CI3 application/controllers/Order.php.
 *
 * URL giữ nguyên: /thanh-toan, apply_voucher, checkout_qr/{id}, cancel/{id}.
 * Auth::guard('web') là nguồn user; khách vẫn xem form (CI3 không bắt login lúc checkout).
 */
class OrderController extends Controller
{
    private const PATH_ACTIONS = [
        'index' => 'index',
        'apply_voucher' => 'applyVoucher',
        'checkout_qr' => 'checkoutQr',
        'cancel' => 'cancel',
    ];

    public function __construct(private readonly CheckoutService $checkout)
    {
    }

    /** GET|POST /thanh-toan */
    public function index(Request $request): View|RedirectResponse
    {
        $session = $request->session();
        $user = Auth::guard('web')->user();
        $sanitize = $this->checkout->sanitizeCart($session);

        if ($sanitize['removed'] !== []) {
            Flash::store(
                Flash::FAIL,
                'Một số sản phẩm không còn bán (' . implode(', ', $sanitize['removed']) . ') và đã được xóa khỏi giỏ hàng.'
            );
            if ($sanitize['cart'] === []) {
                return redirect()->to(Urls::cart());
            }
        }

        if ($request->isMethod('post')) {
            if ($sanitize['cart'] === []) {
                Flash::store(Flash::FAIL, CheckoutService::MSG_EMPTY_CART);

                return redirect()->to(Urls::cart());
            }

            $validated = $request->validate((new CheckoutRequest())->rules(), [], (new CheckoutRequest())->attributes());
            $result = $this->checkout->place($session, $user instanceof User ? $user : null, $validated);

            return $this->follow($result);
        }

        $totalAmount = $sanitize['subtotal'];
        $checkoutVoucher = $this->checkout->refreshVoucher(
            $session,
            $user ? (int) $user->id : 0,
            $totalAmount
        );

        return view('site.order.index', [
            'sf_temp' => 'site/order/index',
            'total_amount' => $totalAmount,
            'checkout_voucher' => $checkoutVoucher,
            'checkout_apply_url' => url('thanh-toan/apply_voucher'),
            'checkout_user' => $user,
        ]);
    }

    /** GET|POST /thanh-toan/apply_voucher */
    public function applyVoucher(Request $request): JsonResponse
    {
        $user = Auth::guard('web')->user();
        $payload = $this->checkout->applyVoucher(
            $request->session(),
            $user instanceof User ? $user : null,
            $this->stringInput($request, 'voucher_code')
        );

        return response()->json(
            array_merge($payload, Csrf::jsonMeta()),
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /** GET /thanh-toan/checkout_qr/{id} */
    public function checkoutQr(Request $request, $id = null): View|RedirectResponse
    {
        $id = (int) ($id ?: $request->route('id'));
        if ($id <= 0) {
            return redirect()->to(url('/'));
        }

        $transaction = Transaction::query()->find($id);
        if (! $transaction) {
            return redirect()->to(url('/'));
        }

        $user = Auth::guard('web')->user();
        $user = $user instanceof User ? $user : null;

        if ($transaction->payment !== Transaction::PAYMENT_TRANSFER || (string) $transaction->status !== '0') {
            Flash::store(Flash::FAIL, CheckoutService::MSG_QR_DENIED);

            return redirect()->to(Urls::cart());
        }

        if (! $this->checkout->canAccess($request->session(), $transaction, $user)) {
            Flash::store(Flash::FAIL, CheckoutService::MSG_QR_FORBIDDEN);

            return redirect()->to(url('/'));
        }

        $this->checkout->clearCart($request->session());

        return view('site.order.checkout_qr', [
            'sf_temp' => 'site/order/checkout_qr',
            'qr_image_url' => $this->checkout->qrImageUrl($transaction),
            'transaction' => $transaction,
        ]);
    }

    /** POST /thanh-toan/cancel/{id} — GET = 404 như CI3. */
    public function cancel(Request $request, $id = null): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }

        $transactionId = (int) ($request->input('transaction_id') ?: ($id ?: $request->route('id')));
        $user = Auth::guard('web')->user();
        $result = $this->checkout->cancel(
            $request->session(),
            $user instanceof User ? $user : null,
            $transactionId,
            trim($this->stringInput($request, 'reason'))
        );

        return $this->follow($result, true);
    }

    /** GET|POST /thanh-toan/{path} — default routing CI3. */
    public function handlePath(Request $request, string $path = ''): View|JsonResponse|RedirectResponse
    {
        $segments = explode('/', trim($path, '/'));
        $action = self::PATH_ACTIONS[strtolower((string) ($segments[0] ?? ''))] ?? null;
        if ($action === null) {
            abort(404);
        }

        return match ($action) {
            'applyVoucher' => $this->applyVoucher($request),
            'checkoutQr' => $this->checkoutQr($request, $segments[1] ?? null),
            'cancel' => $this->cancel($request, $segments[1] ?? null),
            default => $this->index($request),
        };
    }

    private function follow(CheckoutResult $result, bool $authFlash = false): RedirectResponse
    {
        if ($result->message !== '') {
            if ($authFlash) {
                Flash::auth($result->flash === 'success' ? Flash::SUCCESS : Flash::FAIL, $result->message);
            } elseif ($result->flash === 'success') {
                Flash::store(Flash::SUCCESS, $result->message);
            } else {
                Flash::store(Flash::FAIL, $result->message);
            }
        }

        return redirect()->to(match ($result->redirect) {
            CheckoutResult::REDIRECT_CART => Urls::cart(),
            CheckoutResult::REDIRECT_QR => Urls::checkoutQr((int) $result->transaction?->id),
            CheckoutResult::REDIRECT_HOME => url('/'),
            CheckoutResult::REDIRECT_USER => Urls::account(),
            default => Urls::checkout(),
        });
    }

    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
