<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReceiptRequest;
use App\Http\Requests\Admin\UpdateReceiptRequest;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Services\StockReceiptService;
use App\Support\Admin\AdminPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Phiếu nhập. Port admin/Stock_receipt.
 * Form thêm gọi create_confirmed_receipt: cộng tồn ngay.
 */
class StockReceiptController extends Controller
{
    public function __construct(private readonly StockReceiptService $receipts)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if ($denied = $this->denyView()) {
            return $denied;
        }

        $status = trim((string) $request->query('status', ''));
        $code = trim((string) $request->query('code', ''));
        $query = DB::table('stock_receipts')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'stock_receipts.supplier_id')
            ->select('stock_receipts.*', 'suppliers.name as supplier_name')
            ->selectRaw(
                '(SELECT COALESCE(SUM(IF(sri.line_total > 0, sri.line_total, sri.qty * sri.unit_cost)), 0)
                FROM stock_receipt_items sri WHERE sri.receipt_id = stock_receipts.id) AS lines_total'
            );
        if (in_array($status, ['draft', 'confirmed', 'cancelled'], true)) {
            $query->where('stock_receipts.status', $status);
        }
        if ($code !== '') {
            $query->where('stock_receipts.receipt_code', 'like', '%' . addcslashes($code, '%_\\') . '%');
        }

        $admin = Auth::guard('admin')->user();

        return view('admin.receipts.index', [
            'list' => $query->orderByDesc('stock_receipts.id')->get(),
            'filterStatus' => $status,
            'filterCode' => $code,
            'canReceipt' => AdminPermission::can($admin, 'stock.receipt'),
            'admin_header_title' => 'Phiếu nhập kho',
        ]);
    }

    public function add(): View|RedirectResponse
    {
        if ($denied = $this->denyView()) {
            return $denied;
        }
        if (! AdminPermission::can(Auth::guard('admin')->user(), 'stock.receipt')) {
            return redirect()->to(url('admin/stock-receipts'))
                ->with('message_fail', 'Bạn không có quyền tạo phiếu nhập kho.');
        }

        return $this->form(null, []);
    }

    public function store(StoreReceiptRequest $request): RedirectResponse
    {
        $result = $this->receipts->createConfirmed(
            (int) $request->input('supplier_id'),
            (string) $request->input('note', ''),
            $request->lines(),
            (int) Auth::guard('admin')->id()
        );
        if ($result['ok']) {
            return redirect()->to(url('admin/stock-receipts/view/' . $result['receipt_id']))
                ->with('message_success', $result['message']);
        }

        return redirect()->to(url('admin/stock-receipts/add'))->with('message_fail', $result['message']);
    }

    public function update(UpdateReceiptRequest $request, int $id): RedirectResponse
    {
        $receipt = StockReceipt::query()->find($id);
        if (! $receipt) {
            return redirect()->to(url('admin/stock-receipts'))->with('message_fail', 'Phiếu nhập không tồn tại.');
        }
        if ((string) $receipt->status !== StockReceipt::STATUS_DRAFT) {
            return redirect()->to(url('admin/stock-receipts/view/' . $id))
                ->with('message_fail', 'Phiếu đã khóa, không thể sửa.');
        }

        $result = $this->receipts->updateDraft(
            $id,
            (int) $request->input('supplier_id'),
            (string) $request->input('note', ''),
            $request->lines()
        );

        return redirect()->to(url('admin/stock-receipts/' . ($result['ok'] ? 'view/' : 'edit/') . $id))
            ->with($result['ok'] ? 'message_success' : 'message_fail', $result['message']);
    }

    public function view(int $id): View|RedirectResponse
    {
        if ($denied = $this->denyView()) {
            return $denied;
        }

        $receipt = $this->detail($id);
        if (! $receipt) {
            return redirect()->to(url('admin/stock-receipts'))->with('message_fail', 'Phiếu nhập không tồn tại.');
        }

        $admin = Auth::guard('admin')->user();

        return view('admin.receipts.view', [
            'receipt' => $receipt,
            'items' => $this->items($id),
            'statusLabel' => $this->receipts->statusLabel((string) $receipt->status),
            'canReceipt' => AdminPermission::can($admin, 'stock.receipt'),
            'admin_header_title' => 'Phiếu nhập',
        ]);
    }

    public function confirm(Request $request, int $id = 0): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return redirect()->to(url('admin/stock-receipts'));
        }
        if ($denied = $this->denyView()) {
            return $denied;
        }
        if (! AdminPermission::can(Auth::guard('admin')->user(), 'stock.receipt')) {
            return redirect()->to(url('admin/stock-receipts'))
                ->with('message_fail', 'Bạn không có quyền xác nhận phiếu nhập.');
        }

        $receiptId = (int) $request->input('receipt_id', $id);
        $result = $this->receipts->confirm($receiptId, (int) Auth::guard('admin')->id());

        return redirect()->to(url('admin/stock-receipts/view/' . $receiptId))
            ->with($result['ok'] ? 'message_success' : 'message_fail', $result['message']);
    }

    public function cancel(Request $request, int $id = 0): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            return redirect()->to(url('admin/stock-receipts'));
        }
        if ($denied = $this->denyView()) {
            return $denied;
        }
        if (! AdminPermission::can(Auth::guard('admin')->user(), 'stock.receipt')) {
            return redirect()->to(url('admin/stock-receipts'))
                ->with('message_fail', 'Bạn không có quyền hủy phiếu nhập.');
        }

        $receiptId = (int) $request->input('receipt_id', $id);
        $result = $this->receipts->cancel($receiptId);

        return redirect()->to(url('admin/stock-receipts/view/' . $receiptId))
            ->with($result['ok'] ? 'message_success' : 'message_fail', $result['message']);
    }

    public function handlePath(Request $request, string $path = ''): View|RedirectResponse|JsonResponse
    {
        if ($denied = $this->denyView()) {
            return $denied;
        }

        $parts = explode('/', trim($path, '/'));
        $action = strtolower($parts[0] ?? '');
        $id = (int) ($parts[1] ?? 0);

        return match ($action) {
            '', 'index' => $this->index($request),
            'add', 'create' => $request->isMethod('post') ? $this->store(app(StoreReceiptRequest::class)) : $this->add(),
            'edit' => $request->isMethod('post') ? $this->update(app(UpdateReceiptRequest::class), $id) : $this->edit($id),
            'view' => $this->view($id),
            'confirm' => $this->confirm($request, (int) $request->input('receipt_id', $id)),
            'cancel' => $this->cancel($request, (int) $request->input('receipt_id', $id)),
            'search_variants' => $this->searchVariants($request),
            'filter_products' => $this->filterProducts($request),
            'filter_options' => $this->filterOptions($request),
            'recent_variants' => $this->recentVariants(),
            default => abort(404),
        };
    }

    private function edit(int $id): View|RedirectResponse
    {
        if (! AdminPermission::can(Auth::guard('admin')->user(), 'stock.receipt')) {
            return redirect()->to(url('admin/stock-receipts'))
                ->with('message_fail', 'Bạn không có quyền sửa phiếu nhập kho.');
        }
        $receipt = StockReceipt::query()->find($id);
        if (! $receipt) {
            return redirect()->to(url('admin/stock-receipts'))->with('message_fail', 'Phiếu nhập không tồn tại.');
        }
        if ((string) $receipt->status !== StockReceipt::STATUS_DRAFT) {
            return redirect()->to(url('admin/stock-receipts/view/' . $id))
                ->with('message_fail', 'Phiếu đã khóa, không thể sửa.');
        }

        return $this->form($receipt, $this->items($id));
    }

    /**
     * @param  list<object>|\Illuminate\Support\Collection<int, object>  $items
     */
    private function form(?StockReceipt $receipt, $items): View
    {
        $parents = DB::table('categories')->where('parent_id', 1)->orderBy('sort_order')->get();
        foreach ($parents as $parent) {
            $parent->sub = DB::table('categories')->where('parent_id', $parent->id)->orderBy('sort_order')->get();
        }

        $initialLines = [];
        foreach ($items as $item) {
            $initialLines[] = [
                'variant_id' => (int) $item->variant_id,
                'sku' => (string) ($item->sku ?? ''),
                'product_name' => (string) ($item->product_name ?? ''),
                'color' => (string) ($item->color ?? ''),
                'size' => (string) ($item->size ?? ''),
                'qty' => (int) ($item->qty ?? 0),
                'unit_cost' => (float) ($item->unit_cost ?? 0),
            ];
        }

        return view('admin.receipts.form', [
            'receipt' => $receipt,
            'items' => $items,
            'initialLines' => $initialLines,
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->get(),
            'catalogs' => $parents,
            'admin_header_title' => $receipt ? 'Sửa phiếu nháp' : 'Nhập kho',
        ]);
    }

    private function searchVariants(Request $request): JsonResponse
    {
        if ($denied = $this->pickerDenied()) {
            return $denied;
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(10, min(50, (int) $request->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;
        $query = $this->pickerQuery($request);
        $total = (int) (clone $query)->count('product_variants.id');
        $rows = $query->orderBy('products.name')->orderBy('product_variants.id')
            ->offset($offset)->limit($perPage)
            ->get([
                'product_variants.id', 'product_variants.product_id', 'product_variants.sku',
                'product_variants.color_name as color', 'product_variants.size_name as size', 'product_variants.stock',
                'product_variants.min_stock', 'product_variants.cost_price',
                'products.name as product_name',
            ]);

        return response()->json([
            'ok' => true,
            'items' => $this->formatVariants($rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
        ]);
    }

    private function filterProducts(Request $request): JsonResponse
    {
        if ($denied = $this->pickerDenied()) {
            return $denied;
        }

        $q = trim((string) $request->query('q', ''));
        $catalogId = (int) $request->query('catalog_id', 0);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $query = DB::table('products')->where('status', 'active')->orderBy('name');
        if ($catalogId > 0) {
            $query->where('category_id', $catalogId);
        }
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(function ($inner) use ($like) {
                $inner->where('name', 'like', $like)->orWhere('code', 'like', $like);
            });
        }
        $rows = $query->offset(($page - 1) * $perPage)->limit($perPage + 1)->get(['id', 'name', 'code']);
        $hasMore = $rows->count() > $perPage;
        if ($hasMore) {
            $rows->pop();
        }
        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id' => (int) $row->id,
                'text' => $row->code !== '' ? ($row->code . ' — ' . $row->name) : $row->name,
            ];
        }

        return response()->json([
            'ok' => true,
            'results' => $results,
            'pagination' => ['more' => $hasMore],
        ]);
    }

    private function filterOptions(Request $request): JsonResponse
    {
        if ($denied = $this->pickerDenied()) {
            return $denied;
        }

        $base = DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('product_variants.status', 'active');
        $catalogId = (int) $request->query('catalog_id', 0);
        $productId = (int) $request->query('product_id', 0);
        if ($catalogId > 0) {
            $base->where('products.category_id', $catalogId);
        }
        if ($productId > 0) {
            $base->where('product_variants.product_id', $productId);
        }

        return response()->json([
            'ok' => true,
            'colors' => (clone $base)->where('product_variants.color_name', '!=', '')
                ->distinct()->orderBy('product_variants.color_name')->pluck('product_variants.color_name')->all(),
            'sizes' => (clone $base)->where('product_variants.size_name', '!=', '')
                ->distinct()->orderBy('product_variants.size_name')->pluck('product_variants.size_name')->all(),
        ]);
    }

    private function recentVariants(): JsonResponse
    {
        if ($denied = $this->pickerDenied()) {
            return $denied;
        }

        $adminId = (int) Auth::guard('admin')->id();
        $rows = DB::select('
            SELECT pv.id, pv.product_id, pv.sku, pv.color_name AS color, pv.size_name AS size, pv.stock, pv.min_stock, pv.cost_price,
                p.name AS product_name, MAX(sri.id) AS last_used_at
            FROM stock_receipt_items sri
            INNER JOIN stock_receipts sr ON sr.id = sri.receipt_id
            INNER JOIN product_variants pv ON pv.id = sri.variant_id
            INNER JOIN products p ON p.id = pv.product_id
            WHERE sr.created_by = ? AND sri.variant_id > 0 AND pv.status = \'active\'
            GROUP BY pv.id, pv.product_id, pv.sku, pv.color_name, pv.size_name, pv.stock, pv.min_stock, pv.cost_price, p.name
            ORDER BY last_used_at DESC
            LIMIT 10
        ', [$adminId]);

        return response()->json(['ok' => true, 'items' => $this->formatVariants($rows)]);
    }

    private function pickerDenied(): ?JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $allowed = AdminPermission::can($admin, 'stock.receipt')
            || AdminPermission::can($admin, 'stock.manage')
            || AdminPermission::can($admin, 'stock.view');
        if ($allowed) {
            return null;
        }

        return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
    }

    private function pickerQuery(Request $request)
    {
        $query = DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('product_variants.status', 'active');
        $catalogId = (int) $request->query('catalog_id', 0);
        $productId = (int) $request->query('product_id', 0);
        $color = trim((string) $request->query('color', ''));
        $size = trim((string) $request->query('size', ''));
        $q = trim((string) $request->query('q', ''));
        if ($catalogId > 0) {
            $query->where('products.category_id', $catalogId);
        }
        if ($productId > 0) {
            $query->where('product_variants.product_id', $productId);
        }
        if ($color !== '') {
            $query->where('product_variants.color_name', $color);
        }
        if ($size !== '') {
            $query->where('product_variants.size_name', $size);
        }
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $query->where(function ($inner) use ($like) {
                $inner->where('product_variants.sku', 'like', $like)
                    ->orWhere('products.name', 'like', $like)
                    ->orWhere('product_variants.color_name', 'like', $like)
                    ->orWhere('product_variants.size_name', 'like', $like);
            });
        }

        return $query;
    }

    /**
     * @param  iterable<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    private function formatVariants(iterable $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $stock = (int) $row->stock;
            $min = (int) $row->min_stock;
            if ($stock <= 0) {
                $label = ['key' => 'out', 'label' => 'Hết hàng', 'class' => 'danger'];
            } elseif ($stock <= $min) {
                $label = ['key' => 'low', 'label' => 'Sắp hết', 'class' => 'warning'];
            } else {
                $label = ['key' => 'ok', 'label' => 'Còn hàng', 'class' => 'success'];
            }
            $items[] = [
                'id' => (int) $row->id,
                'product_id' => (int) $row->product_id,
                'sku' => (string) $row->sku,
                'product_name' => (string) $row->product_name,
                'color' => (string) $row->color,
                'size' => (string) $row->size,
                'stock' => $stock,
                'min_stock' => $min,
                'cost_price' => (float) $row->cost_price,
                'stock_status' => $label['key'],
                'stock_label' => $label['label'],
                'stock_class' => $label['class'],
            ];
        }

        return $items;
    }

    private function detail(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        return DB::table('stock_receipts')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'stock_receipts.supplier_id')
            ->leftJoin('admins as creator', 'creator.id', '=', 'stock_receipts.created_by')
            ->leftJoin('admins as confirmer', 'confirmer.id', '=', 'stock_receipts.confirmed_by')
            ->where('stock_receipts.id', $id)
            ->first([
                'stock_receipts.*',
                'suppliers.name as supplier_name',
                'creator.name as creator_name',
                'creator.level as creator_level',
                'confirmer.name as confirmer_name',
                'confirmer.level as confirmer_level',
            ]);
    }

    /** @return list<object> */
    private function items(int $id): array
    {
        return DB::table('stock_receipt_items')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_receipt_items.variant_id')
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id')
            ->where('stock_receipt_items.receipt_id', $id)
            ->orderBy('stock_receipt_items.id')
            ->get([
                'stock_receipt_items.*',
                'stock_receipt_items.color_name as color',
                'stock_receipt_items.size_name as size',
                'stock_receipt_items.line_total as subtotal',
                'products.name as product_name',
                'product_variants.product_id as product_id',
                'product_variants.sku as sku',
            ])
            ->all();
    }

    private function denyView(): ?RedirectResponse
    {
        if (AdminPermission::can(Auth::guard('admin')->user(), 'stock.view')) {
            return null;
        }

        return redirect()->to(url('admin'))->with('message_fail', 'Bạn không có quyền xem phiếu nhập kho.');
    }
}
