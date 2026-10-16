<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustStockRequest;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Support\Admin\AdminPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Tồn kho admin. Port admin/Inventory.
 * GET /admin/inventory/index/{offset} là offset, 20 dòng/trang.
 */
class InventoryController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(private readonly InventoryService $inventory)
    {
    }

    public function index(Request $request, int $offset = 0): View|RedirectResponse
    {
        if ($denied = $this->denyView()) {
            return $denied;
        }

        $filters = [
            'name' => trim((string) $request->query('name', '')),
            'catalog_id' => (int) $request->query('catalog_id', 0),
            'sku' => trim((string) $request->query('sku', '')),
            'stock' => (string) $request->query('stock', ''),
        ];
        $offset = max(0, $offset);
        $query = $this->filtered($filters);
        $total = (int) (clone $query)->count('product_variants.id');
        $rows = $query
            ->orderBy('product_variants.stock')
            ->orderByDesc('product_variants.id')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get([
                'product_variants.*',
                'product.name as product_name',
                'catalog.name as catalog_name',
            ]);

        $admin = Auth::guard('admin')->user();

        return view('admin.inventory.index', [
            'rows' => $rows,
            'total' => $total,
            'offset' => $offset,
            'filters' => $filters,
            'stats' => $this->stats(),
            'catalogs' => $this->catalogs(),
            'canCount' => AdminPermission::can($admin, 'inventory.count'),
            'canAdjust' => AdminPermission::can($admin, 'inventory.adjust'),
            'admin_header_title' => 'Tồn kho',
        ]);
    }

    public function lowStock(Request $request): View|RedirectResponse
    {
        $request->query->set('stock', 'low');

        return $this->index($request, 0);
    }

    public function handlePath(Request $request, string $path = ''): View|RedirectResponse
    {
        if ($denied = $this->denyView()) {
            return $denied;
        }

        $parts = explode('/', trim($path, '/'));
        $action = strtolower($parts[0] ?? '');
        $id = (int) ($parts[1] ?? 0);

        return match ($action) {
            '', 'index' => $this->index($request, (int) ($parts[1] ?? 0)),
            'low_stock', 'low-stock' => $this->lowStock($request),
            'adjust' => $this->adjustForm($id),
            default => abort(404),
        };
    }

    public function adjust(AdjustStockRequest $request): RedirectResponse
    {
        $variantId = (int) $request->input('variant_id');
        if ((int) $request->input('new_qty') < 0) {
            return redirect()->to(url('admin/inventory/adjust/' . $variantId))
                ->with('message_fail', 'Số lượng tồn không được âm.');
        }

        $result = $this->inventory->adjustStock(
            $variantId,
            (int) $request->input('new_qty'),
            (string) $request->input('note'),
            (int) Auth::guard('admin')->id()
        );

        return redirect()->to(url('admin/inventory'))->with(
            $result['ok'] ? 'message_success' : 'message_fail',
            $result['message']
        );
    }

    private function adjustForm(int $variantId): View|RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        if (! AdminPermission::can($admin, 'inventory.count') && ! AdminPermission::can($admin, 'inventory.adjust')) {
            return redirect()->to(url('admin/inventory'))->with('message_fail', 'Bạn không có quyền kiểm kê tồn kho.');
        }
        if ($variantId <= 0) {
            return redirect()->to(url('admin/inventory'));
        }

        $variant = ProductVariant::query()->find($variantId);
        if (! $variant) {
            return redirect()->to(url('admin/inventory'))->with('message_fail', 'Biến thể không tồn tại.');
        }

        $name = (string) DB::table('product')->where('id', $variant->product_id)->value('name');

        return view('admin.inventory.adjust', [
            'variant' => $variant,
            'productName' => $name,
            'canAdjust' => AdminPermission::can($admin, 'inventory.adjust'),
            'admin_header_title' => 'Kiểm kê',
        ]);
    }

    private function denyView(): ?RedirectResponse
    {
        if (AdminPermission::can(Auth::guard('admin')->user(), 'inventory.view')) {
            return null;
        }

        return redirect()->to(url('admin'))->with('message_fail', 'Bạn không có quyền xem tồn kho.');
    }

    /**
     * @param  array{name: string, catalog_id: int, sku: string, stock: string}  $filters
     */
    private function filtered(array $filters)
    {
        $query = DB::table('product_variants')
            ->join('product', 'product.id', '=', 'product_variants.product_id')
            ->leftJoin('catalog', 'catalog.id', '=', 'product.catalog_id');

        if ($filters['name'] !== '') {
            $query->where('product.name', 'like', '%' . addcslashes($filters['name'], '%_\\') . '%');
        }
        if ($filters['catalog_id'] > 0) {
            $query->where('product.catalog_id', $filters['catalog_id']);
        }
        if ($filters['sku'] !== '') {
            $query->where('product_variants.sku', 'like', '%' . addcslashes($filters['sku'], '%_\\') . '%');
        }
        if ($filters['stock'] === 'out') {
            $query->where('product_variants.stock', '<=', 0);
        } elseif ($filters['stock'] === 'low') {
            $query->where('product_variants.stock', '>', 0)
                ->whereColumn('product_variants.stock', '<=', 'product_variants.min_stock');
        } elseif ($filters['stock'] === 'ok') {
            $query->where('product_variants.stock', '>', 0);
        }

        return $query;
    }

    /**
     * Cùng bộ lọc màn tồn kho. Đọc, không ghi.
     *
     * @param  array{name: string, catalog_id: int, sku: string, stock: string}  $filters
     */
    public function rowsForExport(array $filters, ?int $limit, ?int $offset)
    {
        $query = $this->filtered($filters)
            ->orderBy('product_variants.stock')
            ->orderByDesc('product_variants.id');
        if ($limit !== null) {
            $query->offset(max(0, (int) $offset))->limit($limit);
        }

        return $query->get([
            'product_variants.*',
            'product.name as product_name',
            'catalog.name as catalog_name',
        ]);
    }

    /** @return array{total: int, out_of_stock: int, low_stock: int, stock_value: float} */
    private function stats(): array
    {
        $row = DB::selectOne('
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
                SUM(CASE WHEN stock > 0 AND stock <= min_stock THEN 1 ELSE 0 END) AS low_stock,
                SUM(stock * cost_price) AS stock_value
            FROM product_variants
            WHERE status = 1
        ');

        return [
            'total' => (int) ($row->total ?? 0),
            'out_of_stock' => (int) ($row->out_of_stock ?? 0),
            'low_stock' => (int) ($row->low_stock ?? 0),
            'stock_value' => (float) ($row->stock_value ?? 0),
        ];
    }

    /** @return list<object> */
    private function catalogs(): array
    {
        $parents = DB::table('catalog')->where('parent_id', 1)->orderBy('sort_order')->get();
        foreach ($parents as $parent) {
            $parent->sub = DB::table('catalog')->where('parent_id', $parent->id)->orderBy('sort_order')->get();
        }

        return $parents->all();
    }
}
