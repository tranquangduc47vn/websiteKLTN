<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVoucherRequest;
use App\Http\Requests\Admin\UpdateVoucherRequest;
use App\Models\Voucher;
use App\Support\Storefront\Loyalty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Quản lý voucher. Port admin/Voucher.
 * Giảm giá và used_count vẫn do VoucherService (checkout / hủy đơn).
 */
class VoucherController extends Controller
{
    public function index(): View
    {
        return view('admin.voucher.index', [
            'list' => Voucher::query()->orderByDesc('id')->get(),
            'admin_header_title' => 'Voucher',
        ]);
    }

    public function handlePath(Request $request, string $path = ''): View|RedirectResponse|JsonResponse
    {
        $parts = explode('/', trim($path, '/'));
        $action = strtolower($parts[0] ?? '');
        $id = (int) ($parts[1] ?? 0);

        return match ($action) {
            '', 'index' => $this->index(),
            'add' => $this->form(null),
            'edit' => $this->editForm($id),
            'delete' => $request->isMethod('post') ? $this->destroy($id) : abort(404),
            'toggle_active' => $this->toggle($request, $id),
            default => abort(404),
        };
    }

    public function store(StoreVoucherRequest $request): RedirectResponse
    {
        $data = $request->voucherAttributes();
        $data['created'] = time();
        $data['used_count'] = 0;
        Voucher::query()->create($data);

        return redirect()->to(url('admin/voucher'))->with('message_success', 'Tạo voucher thành công.');
    }

    public function update(UpdateVoucherRequest $request, int $id): RedirectResponse
    {
        $voucher = Voucher::query()->find($id);
        if (! $voucher) {
            return redirect()->to(url('admin/voucher'))->with('message_fail', 'Voucher không tồn tại.');
        }

        $voucher->fill($request->voucherAttributes());
        $voucher->save();

        return redirect()->to(url('admin/voucher'))->with('message_success', 'Cập nhật voucher thành công.');
    }

    public function destroy(int $id): RedirectResponse
    {
        if (Voucher::query()->where('id', $id)->delete()) {
            return redirect()->to(url('admin/voucher'))->with('message_success', 'Đã xóa voucher.');
        }

        return redirect()->to(url('admin/voucher'));
    }

    public function toggle(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $voucher = Voucher::query()->find($id);
        if (! $voucher) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'Voucher không tồn tại.']);
            }

            return redirect()->to(url('admin/voucher'))->with('message_fail', 'Voucher không tồn tại.');
        }

        $newActive = (int) $voucher->is_active === 1 ? 0 : 1;
        $voucher->is_active = $newActive;
        $voucher->save();

        if ($request->ajax()) {
            return response()->json([
                'ok' => true,
                'is_active' => $newActive,
                'label' => $newActive ? 'Đang bật' : 'Tắt',
            ]);
        }

        return redirect()->to(url('admin/voucher'))->with(
            'message_success',
            $newActive ? 'Đã bật voucher.' : 'Đã tắt voucher.'
        );
    }

    private function editForm(int $id): View|RedirectResponse
    {
        $voucher = Voucher::query()->find($id);
        if (! $voucher) {
            return redirect()->to(url('admin/voucher'))->with('message_fail', 'Voucher không tồn tại.');
        }

        return $this->form($voucher);
    }

    private function form(?Voucher $voucher): View
    {
        return view('admin.voucher.form', [
            'voucher' => $voucher,
            'tiers' => [
                'member' => Loyalty::tierLabel('member'),
                'silver' => Loyalty::tierLabel('silver'),
                'gold' => Loyalty::tierLabel('gold'),
                'vip' => Loyalty::tierLabel('vip'),
            ],
            'admin_header_title' => $voucher ? 'Sửa voucher' : 'Thêm voucher',
        ]);
    }
}
