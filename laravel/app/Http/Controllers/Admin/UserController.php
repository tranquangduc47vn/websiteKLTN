<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Services\OrderAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * B10 — Khách hàng admin. Port CI3 admin/User.
 * Chỉ cần đăng nhập admin (CI3 không gọi admin_can riêng).
 */
class UserController extends Controller
{
    public function __construct(
        private readonly OrderAdminService $orders,
        private readonly LoyaltyService $loyalty,
    ) {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $query = User::query()->orderByDesc('id');
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(function ($inner) use ($like) {
                $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereIn('id', function ($sub) use ($like) {
                        $sub->select('user_id')->from('user_phones')->where('phone_number', 'like', $like);
                    })
                    ->orWhereIn('id', function ($sub) use ($like) {
                        $sub->select('user_id')->from('user_addresses')->where('address_line', 'like', $like);
                    });
            });
        }

        return view('admin.users.index', [
            'users' => $query->get(),
            'search' => $q,
            'admin_header_title' => 'Khách hàng',
        ]);
    }

    public function handlePath(Request $request, string $path = ''): View|RedirectResponse
    {
        $parts = explode('/', trim($path, '/'));
        $action = strtolower($parts[0] ?? '');
        $id = (int) ($parts[1] ?? 0);

        return match ($action) {
            '', 'index' => $this->index($request),
            'view' => $this->view($id),
            'order' => $this->ordersOf($id),
            'detail' => $this->detail($id),
            'accept' => $this->accept($id),
            'del' => $this->destroy($request, $id),
            'deldetail' => $this->removeLine($request, $id),
            default => abort(404),
        };
    }

    private function view(int $id): View|RedirectResponse
    {
        $user = User::query()->find($id);
        if (! $user) {
            return redirect()->to(url('admin/users'))->with('message_fail', 'Khách hàng không tồn tại');
        }
        $addresses = DB::table('user_addresses')->where('user_id', $id)->orderByDesc('is_default')->orderBy('id')
            ->get([DB::raw('user_addresses.*'), DB::raw('user_addresses.note as address_note')]);
        $phones = DB::table('user_phones')->where('user_id', $id)->orderByDesc('is_default')->orderBy('id')
            ->get([DB::raw('user_phones.*'), DB::raw('user_phones.label as phone_label')]);

        return view('admin.users.view', [
            'customer' => $user,
            'addresses' => $addresses,
            'phones' => $phones,
            'profile' => $this->loyalty->getProfile($user),
            'orderCount' => (int) Transaction::query()->where('user_id', $id)->count(),
            'admin_header_title' => 'Chi tiết khách hàng',
        ]);
    }

    private function ordersOf(int $id): View
    {
        return view('admin.users.orders', [
            'customer' => User::query()->find($id),
            'orders' => Transaction::query()->where('user_id', $id)->orderByDesc('id')->get(),
            'admin_header_title' => 'Đơn đặt hàng khách hàng',
        ]);
    }

    private function detail(int $id): View|RedirectResponse
    {
        $order = Transaction::query()->find($id);
        if (! $order) {
            return redirect()->to(url('admin/users'))->with('message_fail', 'Đơn đặt hàng không tồn tại!');
        }

        return view('admin.users.detail', [
            'order' => $order,
            'lines' => $this->orders->lines($id),
            'admin_header_title' => 'Chi tiết đơn đặt hàng',
        ]);
    }

    private function accept(int $id): RedirectResponse
    {
        $result = $this->orders->accept($id);
        if (! $result['ok'] && ! Transaction::query()->where('id', $id)->exists()) {
            return redirect()->to(url('admin/users'))->with('message_fail', $result['message']);
        }
        $to = Transaction::query()->where('id', $id)->exists()
            ? url('admin/users/detail/' . $id)
            : url('admin/users');

        return redirect()->to($to)->with($result['ok'] ? 'message_success' : 'message_fail', $result['message']);
    }

    private function destroy(Request $request, int $id): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        $user = User::query()->find($id);
        if (! $user) {
            return redirect()->to(url('admin/users'))->with('message_fail', 'user không tồn tại');
        }
        $user->delete();

        return redirect()->to(url('admin/users'))->with('message_success', 'Xóa user thành công');
    }

    private function removeLine(Request $request, int $id): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        $line = DB::table('order_items')->where('id', $id)->first();
        if (! $line) {
            return redirect()->to(url('admin/orders'))->with('message_fail', 'Danh mục không tồn tại');
        }
        DB::table('order_items')->where('id', $id)->delete();

        return redirect()->to(url('admin/users'))->with('message_success', 'Xóa thành công');
    }
}
