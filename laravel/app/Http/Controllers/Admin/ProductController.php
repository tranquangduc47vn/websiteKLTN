<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateProductDescriptionRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Services\Ai\ProductDescriptionService;
use App\Services\ProductAdminService;
use App\Support\Admin\AdminPermission;
use App\Support\Storefront\Csrf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * B9 — Sản phẩm admin. Port CI3 admin/Product.
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductAdminService $products,
        private readonly ProductDescriptionService $productDescriptions,
    ) {}

    public function generateDescription(GenerateProductDescriptionRequest $request): JsonResponse
    {
        $product = null;
        $productId = (int) $request->input('product_id', 0);
        if ($productId > 0) {
            $product = Product::query()->find($productId);
            if (! $product) {
                return response()->json(array_merge([
                    'ok' => false,
                    'message' => 'Không tìm thấy sản phẩm.',
                ], Csrf::jsonMeta()), 404);
            }
        }

        $input = $request->validated();
        $input['image_upload'] = $request->file('image');
        $input['gallery_uploads'] = $request->file('list_image', []);

        $result = $this->productDescriptions->generate($input, $product);

        $status = $result['ok'] ? 200 : ($result['error'] === 'rate_limited' ? 429 : 422);

        return response()->json(array_merge([
            'ok' => $result['ok'],
            'html' => $result['html'],
            'message' => $result['message'],
            'error' => $result['error'],
        ], Csrf::jsonMeta()), $status);
    }

    public function index(Request $request, int $offset = 0): View
    {
        return $this->renderIndex($request, $offset);
    }

    public function handlePath(Request $request, string $path = ''): View|RedirectResponse|JsonResponse|Response
    {
        $parts = explode('/', trim($path, '/'));
        $action = strtolower($parts[0] ?? '');

        return match ($action) {
            '', 'index' => $this->renderIndex($request, (int) ($parts[1] ?? 0)),
            'add' => $request->isMethod('post')
                ? $this->store(app(StoreProductRequest::class))
                : $this->create(),
            'edit' => $this->editFlow($request, (int) ($parts[1] ?? 0)),
            'del' => $this->destroy($request),
            'bulk_del' => $this->bulkDestroy($request),
            default => abort(404),
        };
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => null,
            'variants' => [],
            'catalog' => $this->products->catalogTree(),
            'colors' => $this->products->colors(),
            'sizes' => $this->products->sizes(),
            'admin_header_title' => 'Thêm sản phẩm',
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        try {
            $gallery = $request->file('list_image', []);
            $created = $this->products->create(
                $request->validated(),
                $request->file('image'),
                is_array($gallery) ? $gallery : []
            );
        } catch (RuntimeException $e) {
            return redirect()->to(url('admin/products/add'))
                ->with('message_fail', 'Không tải được ảnh đại diện. ' . $e->getMessage())
                ->withInput();
        }

        $warning = $created['warning'];

        return redirect()->to(url('admin/products'))->with(
            'message_success',
            'Sản phẩm đã tạo thành công. Tồn kho hiện tại = 0. Hãy tạo phiếu nhập để cập nhật số lượng thực tế.' . $warning
        );
    }

    private function editFlow(Request $request, int $id): View|RedirectResponse
    {
        $product = Product::query()->find($id);
        if (! $product) {
            return redirect()->to(url('admin/products'))->with('message_fail', 'Không tồn tại sản phẩm này');
        }
        if ($request->isMethod('post')) {
            return $this->update(app(UpdateProductRequest::class), $product);
        }

        $extraColors = $product->color ? explode(',', (string) $product->color) : [];
        $extraSizes = $product->size ? explode(',', (string) $product->size) : [];

        return view('admin.products.form', [
            'product' => $product,
            'variants' => $product->variants()->orderBy('color_name')->orderBy('size_name')->get(),
            'catalog' => $this->products->catalogTree(),
            'colors' => $this->products->colors($extraColors),
            'sizes' => $this->products->sizes($extraSizes),
            'admin_header_title' => 'Chỉnh sửa sản phẩm',
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        try {
            $gallery = $request->file('list_image', []);
            $warning = $this->products->update(
                $product,
                $request->validated(),
                $request->file('image'),
                is_array($gallery) ? $gallery : []
            );
        } catch (RuntimeException $e) {
            return redirect()->to(url('admin/products/edit/' . $product->id))
                ->with('message_fail', 'Không cập nhật được ảnh đại diện. ' . $e->getMessage())
                ->withInput();
        }

        return redirect()->to(url('admin/products'))
            ->with('message_success', 'Cập nhật sản phẩm thành công!' . $warning);
    }

    public function destroy(Request $request): JsonResponse|Response
    {
        if (! $request->ajax() && ! $request->isMethod('post')) {
            abort(404);
        }
        if (! $request->isMethod('post')) {
            abort(404);
        }

        $admin = Auth::guard('admin')->user();
        $ok = false;
        if (AdminPermission::can($admin, 'product.delete_single')) {
            $id = (int) $request->input('id');
            $ok = $id > 0 && $this->products->deleteByIds([$id]) > 0;
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(array_merge([
                'ok' => $ok,
                'status' => $ok ? 'success' : 'failer',
            ], Csrf::jsonMeta()));
        }

        return response($ok ? 'success' : 'failer');
    }

    public function bulkDestroy(Request $request): RedirectResponse|Response
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        $admin = Auth::guard('admin')->user();
        $ajax = $request->ajax() || $request->wantsJson();
        if (! AdminPermission::can($admin, 'product.bulk_delete')) {
            if ($ajax) {
                return response('failer');
            }

            return redirect()->to(url('admin/products'))
                ->with('message_fail', 'Tài khoản nhân viên (MOD) không có quyền xóa hàng loạt sản phẩm!');
        }

        $checkbox = $request->input('checkbox', []);
        if (! is_array($checkbox) || $checkbox === []) {
            if ($ajax) {
                return response('failer');
            }

            return redirect()->to(url('admin/products'))->with('message_fail', 'Không có sản phẩm nào được chọn');
        }

        $deleted = $this->products->deleteByIds($checkbox);
        if ($ajax) {
            return response($deleted > 0 ? 'success' : 'failer');
        }

        $selected = count(array_filter(array_map('intval', $checkbox)));
        if ($deleted > 0) {
            $msg = 'Xóa ' . $deleted . ' sản phẩm thành công';
            if ($deleted < $selected) {
                $msg .= ' (' . ($selected - $deleted) . ' sản phẩm không xóa được)';
            }

            return redirect()->to(url('admin/products'))->with('message_success', $msg);
        }

        return redirect()->to(url('admin/products'))
            ->with('message_fail', 'Không xóa được sản phẩm đã chọn. Vui lòng thử lại.');
    }

    private function renderIndex(Request $request, int $offset): View
    {
        $status = (string) $request->query('status', '');
        if (! in_array($status, ['0', '1'], true)) {
            $status = '';
        }
        $filters = [
            'name' => (string) $request->query('name', ''),
            'catalog_id' => (string) $request->query('catalog_id', ''),
            'status' => $status,
        ];
        $page = $this->products->list($filters, $offset);
        $admin = Auth::guard('admin')->user();

        return view('admin.products.index', [
            'page' => $page,
            'filters' => $filters,
            'catalog' => $this->products->catalogTree(),
            'canDelete' => AdminPermission::can($admin, 'product.delete_single'),
            'canBulkDelete' => AdminPermission::can($admin, 'product.bulk_delete'),
            'admin_header_title' => 'Quản lý sản phẩm',
        ]);
    }
}
