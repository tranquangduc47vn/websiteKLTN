<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\Admin\AdminPermission;
use App\Support\LegacyPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * BƯỚC 3 — AUTHENTICATION (admin staff module)
 *
 * Hiện chỉ implement `logout` (dịch từ `application/controllers/admin/Admin.php`).
 * Các action `index`, `handlePath`, `__call` giữ stub 501 — thuộc module
 * Quản lý nhân sự admin, sẽ làm ở bước riêng.
 */
class AdminController extends Controller
{
    /**
     * Session keys giỏ hàng / checkout của khách.
     *
     * CI3 logout chỉ `unset_userdata('login')` nên các key này được giữ nguyên.
     * Laravel `invalidate()` xóa TOÀN BỘ session — nên phải chụp lại trước khi
     * invalidate và khôi phục sau, nếu không giỏ hàng của khách đang chọn sẽ mất.
     *
     * @var list<string>
     */
    private const PRESERVED_SESSION_KEYS = [
        'custom_cart',
        'checkout_voucher',
    ];

    /**
     * POST /admin/logout thực hiện đăng xuất, có CSRF của nhóm web.
     * GET /admin/logout chỉ chuyển về trang đăng nhập, không đụng session.
     */
    public function logout(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post') || ! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        // 1. Chụp key giỏ hàng trước khi session bị xóa.
        $preserved = [];

        foreach (self::PRESERVED_SESSION_KEYS as $key) {
            if ($request->session()->has($key)) {
                $preserved[$key] = $request->session()->get($key);
            }
        }

        // 2. Xóa session đăng nhập của admin (tương đương `unset_userdata('login')`).
        Auth::guard('admin')->logout();

        // 3. Vô hiệu hoá session + rotate CSRF token (session fixation defence).
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // 4. Khôi phục giỏ hàng — đăng xuất admin không được xóa giỏ của khách.
        foreach ($preserved as $key => $value) {
            $request->session()->put($key, $value);
        }

        // 5. Flash message tương đương `admin_flash_success(...)` của CI3.
        $response = redirect()
            ->route('admin.login')
            ->with('message_success', 'Bạn đã đăng xuất.');

        // 6. `admin_no_cache_headers()` — không cho trình duyệt cache trang admin sau logout.
        return $response
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
    }

    public function index(Request $request)
    {
        return view('admin.staff.index', [
            'list' => Admin::query()->orderBy('id')->get(),
            'canDelete' => AdminPermission::can($request->user('admin'), 'staff.delete'),
        ]);
    }

    public function handlePath(Request $request, string $path = '')
    {
        $parts = array_values(array_filter(explode('/', trim($path, '/')), fn ($part) => $part !== ''));
        $action = $parts[0] ?? '';
        $id = (int) ($parts[1] ?? 0);

        return match ($action) {
            'add' => $this->saveNew($request),
            'edit' => $this->saveEdit($request, $id),
            'del' => $this->destroy($request, $id),
            default => abort(404),
        };
    }

    private function saveNew(Request $request)
    {
        if (! $request->isMethod('post')) {
            return view('admin.staff.form', ['staff' => null]);
        }

        $data = $this->validated($request, true);
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        if (! AdminPermission::can($request->user('admin'), 'staff.grant_admin') && (int) $data['level'] === Admin::ROLE_ADMIN) {
            return redirect('/admin/admin')->with('message_fail', 'Bạn không thể cấp quyền ADMIN cho người khác!');
        }
        if (Admin::query()->where('email', $data['email'])->exists()) {
            return back()->withInput()->with('message_fail', 'Thêm thành viên thất bại');
        }

        Admin::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => LegacyPassword::hash($data['password']),
            'level' => $data['level'],
        ]);

        return redirect('/admin/admin')->with('message_success', 'Thêm thành viên thành công');
    }

    private function saveEdit(Request $request, int $id)
    {
        $staff = Admin::query()->find($id);
        if (! $staff) {
            return redirect('/admin/admin')->with('message_fail', 'Thành viên không tồn tại');
        }
        if ((int) $staff->level === Admin::ROLE_ADMIN && ! AdminPermission::can($request->user('admin'), 'staff.edit_admin')) {
            return redirect('/admin/admin')->with('message_fail', 'Bạn không có quyền chỉnh sửa tài khoản của Quản trị tối cao (ADMIN)!');
        }
        if (! $request->isMethod('post')) {
            return view('admin.staff.form', ['staff' => $staff]);
        }

        $data = $this->validated($request, false);
        if ($data instanceof RedirectResponse) {
            return $data;
        }
        if (! AdminPermission::can($request->user('admin'), 'staff.grant_admin') && (int) $data['level'] === Admin::ROLE_ADMIN) {
            return redirect('/admin/admin')->with('message_fail', 'Bạn không được phép nâng quyền lên ADMIN!');
        }
        $emailTaken = Admin::query()->where('email', $data['email'])->where('id', '!=', $staff->id)->exists();
        if ($emailTaken) {
            return back()->withInput()->with('message_fail', 'Thay đổi thông tin thành viên thất bại');
        }

        $staff->name = $data['name'];
        $staff->email = $data['email'];
        $staff->level = $data['level'];
        if ($data['password'] !== '') {
            $staff->password_hash = LegacyPassword::hash($data['password']);
        }
        $staff->save();

        return redirect('/admin/admin')->with('message_success', 'Thay đổi thông tin thành viên thành công');
    }

    private function destroy(Request $request, int $id)
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        if (! AdminPermission::can($request->user('admin'), 'staff.delete')) {
            return redirect('/admin/admin')->with('message_fail', 'Bạn không có đủ quyền hạn để xóa thành viên!');
        }
        $staff = Admin::query()->find($id);
        if (! $staff) {
            return redirect('/admin/admin')->with('message_fail', 'Admin không tồn tại');
        }
        if ((int) $staff->level === Admin::ROLE_ADMIN) {
            return redirect('/admin/admin')->with('message_fail', 'Hệ thống bảo vệ: Không thể xóa tài khoản Quản trị tối cao (ADMIN)!');
        }
        $staff->delete();

        return redirect('/admin/admin')->with('message_success', 'Xóa admin thành công');
    }

    /**
     * @return array{name: string, email: string, password: string, level: int}|RedirectResponse
     */
    private function validated(Request $request, bool $passwordRequired)
    {
        $name = trim((string) $request->input('name', ''));
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $again = (string) $request->input('re_password', '');
        $level = $request->input('level');

        if ($name === '' || mb_strlen($name) > 100) {
            return back()->withInput()->with('message_fail', $name === '' ? 'Họ tên không được để trống.' : 'Họ tên tối đa 100 ký tự.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            return back()->withInput()->with('message_fail', 'Email không hợp lệ.');
        }
        if (! is_scalar($level) || ! is_numeric($level) || ! in_array((int) $level, [Admin::ROLE_ADMIN, Admin::ROLE_MOD, Admin::ROLE_USER], true)) {
            return back()->withInput()->with('message_fail', 'Phân quyền không hợp lệ.');
        }
        if ($passwordRequired || $password !== '') {
            $strength = LegacyPassword::strengthMessage($password);
            if ($strength !== '') {
                return back()->withInput()->with('message_fail', $strength);
            }
            if ($password !== $again) {
                return back()->withInput()->with('message_fail', 'Mật khẩu nhập lại không khớp.');
            }
        }

        return [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'level' => (int) $level,
        ];
    }
}
