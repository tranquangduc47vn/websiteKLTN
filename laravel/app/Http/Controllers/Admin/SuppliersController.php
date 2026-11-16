<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Models\Supplier;
use App\Support\Admin\AdminPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Nhà cung cấp. Port admin/Suppliers: danh sách + thêm. CI3 không có sửa/xóa.
 */
class SuppliersController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ($denied = $this->denyView()) {
            return $denied;
        }

        return view('admin.suppliers.index', [
            'list' => Supplier::query()->orderBy('name')->get(),
            'canManage' => AdminPermission::can(Auth::guard('admin')->user(), 'stock.manage'),
            'admin_header_title' => 'Nhà cung cấp',
        ]);
    }

    public function handlePath(string $path = ''): View|RedirectResponse
    {
        if ($denied = $this->denyView()) {
            return $denied;
        }

        $action = strtolower(explode('/', trim($path, '/'))[0] ?? '');

        return match ($action) {
            '', 'index' => $this->index(),
            'add' => $this->addForm(),
            default => abort(404),
        };
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::query()->create([
            'code' => trim((string) $request->input('code')),
            'name' => trim((string) $request->input('name')),
            'contact_name' => trim((string) $request->input('contact_name', '')),
            'phone' => trim((string) $request->input('phone', '')),
            'email' => trim((string) $request->input('email', '')),
            'address' => trim((string) $request->input('address', '')),
            'note' => trim((string) $request->input('note', '')),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->to(url('admin/suppliers'))->with('message_success', 'Đã thêm nhà cung cấp.');
    }

    private function addForm(): View|RedirectResponse
    {
        if (! AdminPermission::can(Auth::guard('admin')->user(), 'stock.manage')) {
            return redirect()->to(url('admin/suppliers'));
        }

        return view('admin.suppliers.form', [
            'admin_header_title' => 'Thêm nhà cung cấp',
        ]);
    }

    private function denyView(): ?RedirectResponse
    {
        if (AdminPermission::can(Auth::guard('admin')->user(), 'stock.view')) {
            return null;
        }

        return redirect()->to(url('admin'));
    }
}
