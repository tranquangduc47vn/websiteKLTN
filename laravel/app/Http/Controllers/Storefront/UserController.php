<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UserForgotPasswordRequest;
use App\Http\Requests\Auth\UserLoginRequest;
use App\Http\Requests\Auth\UserRegisterRequest;
use App\Http\Requests\Auth\UserResetPasswordRequest;
use App\Http\Requests\Storefront\AddAddressRequest;
use App\Http\Requests\Storefront\AddPhoneRequest;
use App\Http\Requests\Storefront\ChangePasswordRequest;
use App\Http\Requests\Storefront\UpdateProfileRequest;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserPhone;
use App\Services\Account\AccountResult;
use App\Services\AccountService;
use App\Services\AuthMailService;
use App\Services\CartSessionService;
use App\Services\CheckoutService;
use App\Services\LoyaltyService;
use App\Services\PasswordResetService;
use App\Services\VoucherService;
use App\Support\LegacyPassword;
use App\Support\Storefront\Flash;
use App\Support\Storefront\VnAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * BƯỚC 3 — AUTHENTICATION phía khách hàng (storefront).
 *
 * Xác thực dựa trên guard `web` + provider `legacy_eloquent`
 * (App\Auth\LegacyPasswordUserProvider) nên:
 *   - bcrypt (Hash) được xác thực bằng Laravel Hash;
 *   - MD5 legacy được xác thực bằng md5() rồi TỰ ĐỘNG rehash sang bcrypt khi login thành công;
 *   - KHÔNG bao giờ lưu plaintext vào session.
 *
 * Session giữ `custom_cart`, `checkout_voucher`, `redirect_back`.
 * Đăng nhập chỉ qua guard `web`. Session key `user` không xác thực.
 *
 * 7 chức năng auth đã migrate: login, logout, register, forgotPassword, resetPassword.
 * B7: trang tài khoản, sổ điện thoại/địa chỉ, đổi mật khẩu, lịch sử + chi tiết đơn.
 */
class UserController extends Controller
{
    /** Khớp rate_limit_helper.php của CI3 (5 lần / 15 phút). */
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 900;

    private const CART_KEY = CartSessionService::SESSION_KEY;

    /** Session key CI3: giữ nguyên cho tương thích. */
    private const LEGACY_REDIRECT_KEY = 'redirect_back';
    private const LEGACY_VOUCHER_KEY = 'checkout_voucher';

    /** CI3 default routing /user/{method}. */
    private const PATH_ACTIONS = [
        'index' => 'index',
        'password_change' => 'passwordChange',
        'profile_update' => 'profileUpdate',
        'phone_add' => 'phoneAdd',
        'phone_delete' => 'phoneDelete',
        'phone_default' => 'phoneDefault',
        'address_add' => 'addressAdd',
        'address_delete' => 'addressDelete',
        'address_default' => 'addressDefault',
        'order_detail' => 'orderDetail',
        'login' => 'login',
        'register' => 'register',
        'forgot_password' => 'forgotPassword',
        'reset_password' => 'resetPassword',
    ];

    public function __construct(
        private readonly CartSessionService $cart,
        private readonly PasswordResetService $passwordResets,
        private readonly AuthMailService $mail,
        private readonly AccountService $account,
        private readonly LoyaltyService $loyalty,
        private readonly VoucherService $vouchers,
    ) {
    }

    /* =====================================================================
     | GET|POST /dang-nhap  (CI3: User::login)
     |====================================================================*/

    public function login(Request $request): View|RedirectResponse
    {
        // Đã đăng nhập -> đảm bảo giỏ hàng, quay lại trang đã định, về giỏ hàng.
        $user = Auth::guard('web')->user();

        if ($user) {
            $this->cart->ensureForLoggedInUser($request->session(), $user);

            return $this->redirectAfterAuth($request);
        }

        if ($request->isMethod('post')) {
            // Resolve FormRequest QUA CONTAINER: Laravel tu dong createFrom(app['request'])
            // + setContainer + setRedirector + validateResolved(). Neu dung optional param co default,
            // ResolvesRouteDependencies inject null -> doi tuong thieu container -> loi 500.
            $form = app(UserLoginRequest::class);
            $email = trim((string) $form->input('email'));
            $throttleKey = $this->throttleKey($email, $request->ip());

            if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
                return $this->loginFailed($request, $this->lockoutMessage($throttleKey));
            }

            // Guard `web` + provider legacy: bcrypt trước, MD5 sau, rehash nếu cần.
            $authenticated = Auth::guard('web')->attempt(
                ['email' => $email, 'password' => (string) $request->input('password')],
                (bool) $request->boolean('remember')
            );

            if (! $authenticated) {
                RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

                return $this->loginFailed($request, 'Sai thông tin email hoặc mật khẩu, vui lòng thử lại!');
            }

            RateLimiter::clear($throttleKey);

            $user = Auth::guard('web')->user();

            // Nạp giỏ đã lưu. Nếu chưa có giỏ lưu, giữ giỏ khách vừa mang sang trang đăng nhập.
            $pending = $request->session()->get(CartSessionService::PENDING_CHECKOUT_KEY);
            $request->session()->forget(self::CART_KEY);
            $this->cart->restoreForUser($request->session(), (int) $user->id);
            if ($this->cart->getSessionCart($request->session()) === [] && is_array($pending) && $pending !== []) {
                $this->cart->putSessionCart($request->session(), $pending);
                $this->cart->syncToUser($request->session(), (int) $user->id, $pending);
            }
            $request->session()->forget(CartSessionService::PENDING_CHECKOUT_KEY);

            $request->session()->regenerate(); // chống session fixation

            return $this->redirectAfterAuth($request);
        }

        return view('auth.user-login');
    }

    private function redirectAfterAuth(Request $request): RedirectResponse
    {
        $added = $this->cart->consumePendingAdd($request->session());
        if ($added !== null) {
            Flash::store($added->ok ? Flash::SUCCESS : Flash::FAIL, $added->message);
            $request->session()->forget(self::LEGACY_REDIRECT_KEY);

            return redirect()->to($this->cart->cartUrl());
        }

        $redirectUrl = (string) ($request->session()->get(self::LEGACY_REDIRECT_KEY)
            ?: $this->cart->cartUrl());
        $request->session()->forget(self::LEGACY_REDIRECT_KEY);

        return redirect($redirectUrl);
    }

    /* =====================================================================
     | POST /user/logout  (CI3: User::logout)
     | GET không đi vào đây. CSRF do middleware web.
     |====================================================================*/

    public function logout(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }

        $user = Auth::guard('web')->user();

        if ($user) {
            // Đồng bộ giỏ hàng vào DB TRƯỚC khi xóa session (CI3: cart_sync_to_user).
            $this->cart->syncToUser($request->session(), (int) $user->id);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home');
    }

    /* =====================================================================
     | GET|POST /dang-ky  (CI3: User::register)
     |====================================================================*/

    public function register(): View|RedirectResponse
    {
        if (request()->isMethod('post')) {
            $validated = app(UserRegisterRequest::class)->validated();

            $addressLine = VnAddress::formatLine(
                $validated['province_id'] ?? '',
                $validated['district_id'] ?? '',
                $validated['ward_id'] ?? '',
                $validated['address_note'] ?? ''
            );
            if ($addressLine === '') {
                $addressLine = trim((string) $validated['address_note']);
            }

            $user = User::query()->create([
                'name' => trim((string) $validated['name']),
                'email' => trim((string) $validated['email']),
                'password_hash' => LegacyPassword::hash((string) $validated['password']),
                'loyalty_points' => 0,
                'loyalty_lifetime_spend' => 0,
                'loyalty_completed_orders' => 0,
                'loyalty_tier' => User::TIER_MEMBER,
                'status' => User::STATUS_ACTIVE,
            ]);

            // Tạo sổ địa chỉ + số điện thoại mặc định (CI3: user_address/user_phone is_default=1).
            if ((int) $user->id > 0) {
                UserAddress::query()->create([
                    'user_id' => (int) $user->id,
                    'address_note' => trim((string) $validated['address_note']),
                    'province_id' => trim((string) $validated['province_id']),
                    'district_id' => trim((string) $validated['district_id']),
                    'ward_id' => trim((string) $validated['ward_id']),
                    'address_line' => $addressLine,
                    'is_default' => 1,
                ]);

                $phoneNumber = preg_replace('/\s+/', '', trim((string) $validated['phone']));

                if ($phoneNumber !== '') {
                    UserPhone::query()->create([
                        'user_id' => (int) $user->id,
                        'phone_label' => '',
                        'phone_number' => $phoneNumber,
                        'is_default' => 1,
                    ]);
                }

                // Gửi email chào mừng (lỗi mail không chặn luồng đăng ký — giống CI3).
                $this->mail->sendWelcome($user);
            }

            return redirect()
                ->route('storefront.user.login')
                ->with('message_success', 'Đăng ký tài khoản thành công! Vui lòng đăng nhập tại đây.');
        }

        return view('auth.user-register');
    }

    /* =====================================================================
     | GET|POST /quen-mat-khau  (CI3: User::forgot_password)
     |====================================================================*/

    public function forgotPassword(Request $request): View|RedirectResponse
    {
        if (request()->isMethod('post')) {
            // Resolve FormRequest QUA CONTAINER + validate ngay khi resolve.
            $form = app(UserForgotPasswordRequest::class);
            $email = trim((string) $form->validated()['email']);

            $user = User::query()->where('email', $email)->first();

            if ($user) {
                $token = $this->passwordResets->createToken($user);

                if (! $this->mail->sendPasswordReset($user, $token)) {
                    \Illuminate\Support\Facades\Log::error('Password reset email failed for user_id=' . (int) $user->id);
                }
            }

            // Luôn trả cùng thông báo — KHÔNG tiết lộ email có tồn tại hay không (giữ đúng CI3).
            return redirect()
                ->route('storefront.user.forgot_password')
                ->with('message_success', 'Nếu email tồn tại trong hệ thống, chúng tôi đã gửi hướng dẫn đặt lại mật khẩu. Vui lòng kiểm tra hộp thư (và cả thư rác).');
        }

        return view('auth.user-forgot-password');
    }

    /* =====================================================================
     | GET|POST /dat-lai-mat-khau[/{token}]  (CI3: User::reset_password)
     |====================================================================*/

    public function resetPassword(Request $request, ?string $token = null): View|RedirectResponse
    {
        // Token lấy từ route {token} trước, sau đó mới tới input (CI3 giống vậy).
        $token = trim((string) ($token ?: $request->input('token', '')));
        $record = $token !== '' ? $this->passwordResets->findValidByToken($token) : null;

        if ($request->isMethod('post')) {
            if (! $record) {
                return redirect()
                    ->route('storefront.user.forgot_password')
                    ->with('message_fail', 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
            }

            // Kiểm tra lại lần nữa trước khi ghi — tránh race double-use.
            $record = $this->passwordResets->findValidByToken($token);

            if (! $record) {
                return redirect()
                    ->route('storefront.user.forgot_password')
                    ->with('message_fail', 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
            }

            // Validation (CI3: form_validation->run()) ngay sau khi token hop le.
            $validated = app(UserResetPasswordRequest::class)->validated();

            User::query()
                ->whereKey((int) $record->user_id)
                ->update(['password_hash' => LegacyPassword::hash((string) $validated['password'])]);

            $this->passwordResets->markUsed((int) $record->id);

            return redirect()
                ->route('storefront.user.login')
                ->with('message_success', 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập.');
        }

        return view('auth.user-reset-password', [
            'token_valid' => $record !== null,
            'reset_token' => $token,
        ]);
    }

    /* =====================================================================
     | B7 — Trang tài khoản / sổ điện thoại / địa chỉ / lịch sử đơn
     |====================================================================*/

    public function index(Request $request): View|RedirectResponse
    {
        $user = $this->accountUser();
        $this->account->ensureLegacyBooks($user);

        $phones = $this->account->phones((int) $user->id);
        $addresses = $this->account->addresses((int) $user->id);

        $orderStatus = trim((string) $request->query('order_status', ''));
        $allowed = ['', 'all', '0', '1', '2', '3', '4', 'pending', 'confirmed', 'packed', 'completed', 'cancelled'];
        if (! in_array($orderStatus, $allowed, true)) {
            $orderStatus = '';
        }
        if ($orderStatus === 'all') {
            $orderStatus = '';
        }

        $orders = $this->account->paginateOrders(
            $user,
            $orderStatus,
            max(1, (int) $request->query('order_page', 1))
        );

        $flash = Flash::all();

        return view('site.user.index', [
            'sf_temp' => 'site/user/index.php',
            'account_user' => $user,
            'user_phones' => $phones,
            'user_addresses' => $addresses,
            'phone_max' => UserPhone::MAX_PER_USER,
            'address_max' => UserAddress::MAX_PER_USER,
            'phone_count' => $phones->count(),
            'address_count' => $addresses->count(),
            'vn_address_json_url' => VnAddress::jsonUrl(),
            'list_transaction' => $orders,
            'order_status_filter' => $orderStatus,
            'order_total' => $orders->total(),
            'order_page' => $orders->currentPage(),
            'order_stt_offset' => ($orders->currentPage() - 1) * AccountService::ORDERS_PER_PAGE,
            'loyalty' => $this->loyalty->getProfile($user),
            'available_vouchers' => $this->vouchers->listAvailableForUser((int) $user->id),
            'message_success' => $flash['success'],
            'message_fail' => $flash['fail'],
        ]);
    }

    public function orderDetail(Request $request, int $id = 0): View
    {
        $user = $this->accountUser();
        $access = $request->session()->get(CheckoutService::SESSION_ACCESS);
        $transaction = $this->account->ownedTransaction($user, $id, is_array($access) ? $access : null);
        if (! $transaction) {
            abort(404);
        }

        return view('site.user.order_detail', [
            'transaction' => $transaction,
            'order_lines' => $this->account->orderLines((int) $transaction->id),
        ]);
    }

    public function profileUpdate(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return redirect()->to(url('user'));
        }

        $form = app(UpdateProfileRequest::class);
        $user = $this->accountUser();
        $result = $this->account->updateName($user, (string) $form->validated()['name']);
        $this->account->refreshSessionUser($request->session(), $user);

        return $this->accountRedirect($result);
    }

    public function passwordChange(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return redirect()->to(url('user'));
        }

        $form = app(ChangePasswordRequest::class);
        $user = $this->accountUser();
        $validated = $form->validated();
        $result = $this->account->changePassword(
            $user,
            (string) $validated['old_password'],
            (string) $validated['password']
        );

        return $this->accountRedirect($result);
    }

    public function phoneAdd(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return redirect()->to(url('user'));
        }

        $form = app(AddPhoneRequest::class);
        $user = $this->accountUser();
        $validated = $form->validated();
        $result = $this->account->addPhone(
            $user,
            (string) $validated['phone_number'],
            (string) ($validated['phone_label'] ?? ''),
            $request->boolean('is_default')
        );
        $this->account->refreshSessionUser($request->session(), $user);

        return $this->accountRedirect($result);
    }

    public function phoneDelete(Request $request, int $id = 0): RedirectResponse
    {
        if (! $request->isMethod('post') || $id <= 0) {
            return redirect()->to(url('user'));
        }

        $user = $this->accountUser();
        $result = $this->account->deletePhone($user, $id);
        $this->account->refreshSessionUser($request->session(), $user);

        return $this->accountRedirect($result);
    }

    public function phoneDefault(Request $request, int $id = 0): RedirectResponse
    {
        if (! $request->isMethod('post') || $id <= 0) {
            return redirect()->to(url('user'));
        }

        $user = $this->accountUser();
        $result = $this->account->defaultPhone($user, $id);
        $this->account->refreshSessionUser($request->session(), $user);

        return $this->accountRedirect($result);
    }

    public function addressAdd(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return redirect()->to(url('user'));
        }

        $form = app(AddAddressRequest::class);
        $user = $this->accountUser();
        $validated = $form->validated();
        $result = $this->account->addAddress(
            $user,
            (string) $validated['province_id'],
            (string) $validated['district_id'],
            (string) $validated['ward_id'],
            (string) $validated['address_note'],
            $request->boolean('is_default')
        );
        $this->account->refreshSessionUser($request->session(), $user);

        return $this->accountRedirect($result);
    }

    public function addressDelete(Request $request, int $id = 0): RedirectResponse
    {
        if (! $request->isMethod('post') || $id <= 0) {
            return redirect()->to(url('user'));
        }

        $user = $this->accountUser();
        $result = $this->account->deleteAddress($user, $id);
        $this->account->refreshSessionUser($request->session(), $user);

        return $this->accountRedirect($result);
    }

    public function addressDefault(Request $request, int $id = 0): RedirectResponse
    {
        if (! $request->isMethod('post') || $id <= 0) {
            return redirect()->to(url('user'));
        }

        $user = $this->accountUser();
        $result = $this->account->defaultAddress($user, $id);
        $this->account->refreshSessionUser($request->session(), $user);

        return $this->accountRedirect($result);
    }

    /** Callback form_validation CI3 — không phải endpoint AJAX của trang tài khoản. */
    public function checkEmail(Request $request)
    {
        abort(404);
    }

    public function validateVnAddress(Request $request)
    {
        abort(404);
    }

    public function validateUserPhone(Request $request)
    {
        abort(404);
    }

    public function getInfoUser(Request $request)
    {
        abort(404);
    }

    public function handlePath(Request $request, string $path = ''): View|RedirectResponse
    {
        $segments = explode('/', trim($path, '/'));
        $action = self::PATH_ACTIONS[strtolower($segments[0] ?? '')] ?? null;
        if ($action === null) {
            abort(404);
        }

        $id = isset($segments[1]) ? (int) $segments[1] : 0;

        return match ($action) {
            'phoneDelete', 'phoneDefault', 'addressDelete', 'addressDefault', 'orderDetail' => $this->{$action}($request, $id),
            'resetPassword' => $this->resetPassword($request, $segments[1] ?? null),
            default => $this->{$action}($request),
        };
    }

    /* =====================================================================
     | Helpers
     |====================================================================*/

    private function throttleKey(string $email, ?string $ip): string
    {
        return 'user-login|' . Str::lower($email) . '|' . ($ip ?? 'unknown');
    }

    private function lockoutMessage(string $throttleKey): string
    {
        $seconds = RateLimiter::availableIn($throttleKey);
        $minutes = max(1, (int) ceil($seconds / 60));

        return 'Quá nhiều lần đăng nhập sai. Vui lòng thử lại sau ' . $minutes . ' phút.';
    }

    private function loginFailed(Request $request, string $message): RedirectResponse
    {
        return back()
            ->withInput($request->only('email'))
            ->with('message_fail', $message);
    }

    private function accountUser(): User
    {
        $user = Auth::guard('web')->user();
        if (! $user instanceof User) {
            request()->session()->put(self::LEGACY_REDIRECT_KEY, request()->fullUrl());

            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                redirect()->route('storefront.user.login')
            );
        }

        return $user;
    }

    private function accountRedirect(AccountResult $result): RedirectResponse
    {
        if ($result->message !== '') {
            Flash::auth($result->ok ? Flash::SUCCESS : Flash::FAIL, $result->message);
        }

        return redirect()->to($result->redirect());
    }
}
