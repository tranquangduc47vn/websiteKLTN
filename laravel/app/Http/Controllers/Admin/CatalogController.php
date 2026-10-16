<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCatalogRequest;
use App\Http\Requests\Admin\UpdateCatalogRequest;
use App\Models\Catalog;
use App\Support\Admin\AdminPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * B9 — Danh mục admin. Port CI3 admin/Catalog.
 */
class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $query = Catalog::query()->orderBy('id');
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where('name', 'like', '%' . addcslashes($q, '%_\\') . '%');
        }

        return view('admin.catalog.index', [
            'list' => $query->get(),
            'search' => $q,
            'canDelete' => AdminPermission::can(Auth::guard('admin')->user(), 'catalog.delete'),
            'admin_header_title' => 'Quản lý danh mục',
        ]);
    }

    public function handlePath(Request $request, string $path = ''): View|RedirectResponse
    {
        $parts = explode('/', trim($path, '/'));
        $action = strtolower($parts[0] ?? '');

        return match ($action) {
            '', 'index' => $this->index($request),
            'add' => $request->isMethod('post')
                ? $this->store(app(StoreCatalogRequest::class))
                : $this->create(),
            'edit' => $this->editFlow($request, (int) ($parts[1] ?? 0)),
            'del' => $this->destroy($request, (int) ($parts[1] ?? 0)),
            default => abort(404),
        };
    }

    public function create(): View
    {
        return view('admin.catalog.form', [
            'catalog' => null,
            'parents' => $this->parentChoices(),
            'admin_header_title' => 'Thêm danh mục',
        ]);
    }

    public function store(StoreCatalogRequest $request): RedirectResponse
    {
        Catalog::query()->create([
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description') ?: null,
            'parent_id' => (int) $request->input('parent_id', 0),
            'sort_order' => (int) $request->input('sort_order', 1),
            'created' => now(),
        ]);

        return redirect()->to(url('admin/catalog'))->with('message_success', 'Thêm danh mục thành công');
    }

    private function editFlow(Request $request, int $id): View|RedirectResponse
    {
        $catalog = Catalog::query()->find($id);
        if (! $catalog) {
            return redirect()->to(url('admin/catalog'))->with('message_fail', 'Danh mục không tồn tại');
        }
        if ($request->isMethod('post')) {
            return $this->update(app(UpdateCatalogRequest::class), $catalog);
        }

        return view('admin.catalog.form', [
            'catalog' => $catalog,
            'parents' => $this->parentChoices(),
            'admin_header_title' => 'Sửa danh mục',
        ]);
    }

    public function update(UpdateCatalogRequest $request, Catalog $catalog): RedirectResponse
    {
        $parentId = (int) $request->input('parent_id', 0);
        if ($parentId === (int) $catalog->id || $this->isDescendant((int) $catalog->id, $parentId)) {
            return redirect()->to(url('admin/catalog/edit/' . $catalog->id))
                ->with('message_fail', 'Không thể chọn chính danh mục này hoặc danh mục con làm danh mục cha.')
                ->withInput();
        }

        $catalog->update([
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description') ?: null,
            'parent_id' => $parentId,
            'sort_order' => (int) $request->input('sort_order', $catalog->sort_order),
        ]);

        return redirect()->to(url('admin/catalog'))->with('message_success', 'Thay đổi danh mục thành công');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        if (! AdminPermission::can(Auth::guard('admin')->user(), 'catalog.delete')) {
            return redirect()->to(url('admin/catalog'))
                ->with('message_fail', 'Bạn không có đủ quyền hạn để xóa danh mục sản phẩm!');
        }
        $catalog = Catalog::query()->find($id);
        if (! $catalog) {
            return redirect()->to(url('admin/catalog'))->with('message_fail', 'Danh mục không tồn tại');
        }
        $catalog->delete();

        return redirect()->to(url('admin/catalog'))->with('message_success', 'Xóa danh mục thành công');
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function parentChoices()
    {
        return DB::table('catalog')->where('parent_id', '<', 2)->orderBy('id')->get(['id', 'name', 'parent_id']);
    }

    private function isDescendant(int $catalogId, int $maybeChild): bool
    {
        $current = $maybeChild;
        for ($i = 0; $i < 12 && $current > 0; $i++) {
            if ($current === $catalogId) {
                return true;
            }
            $current = (int) DB::table('catalog')->where('id', $current)->value('parent_id');
        }

        return false;
    }
}
