<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Cart\CartActionResult;
use App\Services\CartSessionService;
use App\Support\Storefront\Csrf;
use App\Support\Storefront\Flash;
use App\Support\Storefront\Urls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * B5 — Giỏ hàng storefront. Port từ CI3 application/controllers/Cart.php.
 *
 * Mọi action yêu cầu khách đã đăng nhập (CI3 _check_login / _require_login_for_cart).
 * Request AJAX (X-Requested-With: XMLHttpRequest) nhận JSON đúng format cart-luxury.js đang đọc;
 * form thường nhận flash + redirect về /gio-hang.
 */
class CartController extends Controller
{
    private const MSG_LOGIN_TO_ADD = 'Bạn cần đăng nhập để thêm sản phẩm vào giỏ hàng.';
    private const MSG_LOGIN_REQUIRED = 'Bạn cần đăng nhập tài khoản để thực hiện chức năng này!';

    /** CI3 default routing /gio-hang/{method}: chỉ các action public của Cart. */
    private const PATH_ACTIONS = [
        'index' => 'index',
        'add' => 'add',
        'update_options' => 'updateOptions',
        'update' => 'update',
        'del' => 'del',
    ];

    public function __construct(private readonly CartSessionService $cart)
    {
    }

    /** GET /gio-hang */
    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectGuest($request)) {
            return $redirect;
        }

        // CI3 load_store_flash_to() chạy trước khi làm sạch giỏ: khối thông báo trong trang giỏ
        // chỉ hiện flash của request trước, thông báo phát sinh lúc làm sạch hiện ở layout.
        $session = $request->session();
        $previousSuccess = (string) $session->get('store_message_success', '');
        $previousFail = (string) $session->get('store_message_fail', '');

        $page = $this->cart->preparePage($session);
        foreach ($page->notices as $notice) {
            Flash::store(Flash::FAIL, $notice);
        }

        return view('site.cart.index', [
            'sf_temp' => 'site/cart/index',
            'cart_items' => $page->items,
            'cart_total_items' => $page->totalItems,
            'cart_total_price' => $page->totalPrice,
            'cart_message_success' => $previousSuccess,
            'cart_message_fail' => $previousFail,
        ]);
    }

    /**
     * POST /gio-hang/them/{id} (alias /gio-hang/add/{id}).
     * GET không đổi giỏ. Khách chưa đăng nhập được lưu lựa chọn để login thêm sau.
     */
    public function add(Request $request, $id = null): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }

        if (! Auth::guard('web')->check()) {
            $productId = $request->input('id') ?: $id;
            $this->cart->rememberPendingAdd(
                $request->session(),
                (int) (is_scalar($productId) ? $productId : 0),
                $request->integer('qty'),
                $this->stringInput($request, 'size'),
                $this->stringInput($request, 'color'),
            );
            $request->session()->put('redirect_back', Urls::cart());
            Flash::auth(Flash::FAIL, self::MSG_LOGIN_TO_ADD);

            return redirect()->to(Urls::login());
        }

        // CI3: ưu tiên POST id, rỗng thì lấy segment URL.
        $productId = $request->input('id') ?: $id;

        $result = $this->cart->add(
            $request->session(),
            (int) (is_scalar($productId) ? $productId : 0),
            $request->integer('qty'),
            $this->stringInput($request, 'size'),
            $this->stringInput($request, 'color'),
        );

        if ($result->ok) {
            Flash::store(Flash::SUCCESS, $result->message);

            return redirect()->to(Urls::cart());
        }

        if ($result->code === CartActionResult::OUT_OF_STOCK) {
            Flash::product($result->message);

            return redirect()->to(Urls::product($result->product));
        }

        Flash::store(Flash::FAIL, $result->message);

        return redirect()->to(url('/'));
    }

    /** POST /gio-hang/cap-nhat-thuoc-tinh */
    public function updateOptions(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        if ($denied = $this->denyGuest($request)) {
            return $denied;
        }

        return $this->respond($request, $this->cart->updateOptions(
            $request->session(),
            $this->stringInput($request, 'rowid'),
            $this->stringInput($request, 'size'),
            $this->stringInput($request, 'color'),
        ));
    }

    /** POST /gio-hang/update — action=sum|sub */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        if ($denied = $this->denyGuest($request)) {
            return $denied;
        }

        return $this->respond($request, $this->cart->changeQuantity(
            $request->session(),
            $this->stringInput($request, 'rowid'),
            $this->stringInput($request, 'action'),
        ));
    }

    /** POST /gio-hang/del — rowid rỗng = xóa toàn bộ */
    public function del(Request $request): JsonResponse|RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        if ($denied = $this->denyGuest($request)) {
            return $denied;
        }

        return $this->respond($request, $this->cart->remove(
            $request->session(),
            $this->stringInput($request, 'rowid'),
        ));
    }

    /** GET|POST /gio-hang/{method}/{args...} — mô phỏng default routing của CI3. */
    public function handlePath(Request $request, string $path = ''): View|JsonResponse|RedirectResponse
    {
        $segments = explode('/', trim($path, '/'));
        $action = self::PATH_ACTIONS[strtolower($segments[0])] ?? null;

        if ($action === null) {
            abort(404);
        }

        return $action === 'add'
            ? $this->add($request, $segments[1] ?? null)
            : $this->{$action}($request);
    }

    /* =====================================================================
     | Helpers
     |====================================================================*/

    /** CI3 _check_login(): luôn redirect về trang đăng nhập (kể cả AJAX). */
    private function redirectGuest(Request $request): ?RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return null;
        }

        $request->session()->put('redirect_back', url($request->path()));
        Flash::auth(Flash::FAIL, self::MSG_LOGIN_TO_ADD);

        return redirect()->to(Urls::login());
    }

    /** CI3 _require_login_for_cart(): AJAX nhận 401 JSON, form thường redirect đăng nhập. */
    private function denyGuest(Request $request): JsonResponse|RedirectResponse|null
    {
        if (Auth::guard('web')->check()) {
            return null;
        }

        if ($request->ajax()) {
            return $this->json([
                'ok' => false,
                'message' => self::MSG_LOGIN_REQUIRED,
                'login_required' => true,
            ], 401);
        }

        $request->session()->put('redirect_back', url($request->path()));
        Flash::store(Flash::FAIL, self::MSG_LOGIN_REQUIRED);

        return redirect()->to(Urls::login());
    }

    /** CI3 _cart_finish() / _cart_fail(). */
    private function respond(Request $request, CartActionResult $result): JsonResponse|RedirectResponse
    {
        if ($request->ajax()) {
            $totals = $this->cart->totals($result->cart);

            return $this->json(array_merge([
                'ok' => $result->ok,
                'message' => $result->message,
                'total_items' => $totals['total_items'],
                'total_price' => $totals['total_price'],
            ], $result->data));
        }

        if (! $result->ok) {
            Flash::store(Flash::FAIL, $result->message);
        } elseif ($result->message !== '') {
            Flash::store(Flash::SUCCESS, $result->message);
        }

        return redirect()->to(Urls::cart());
    }

    /**
     * CI3 _json_response(): kèm csrf_name/csrf_hash để window.applyCsrfResponse cập nhật token.
     *
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(
            array_merge($payload, Csrf::jsonMeta()),
            $status,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
