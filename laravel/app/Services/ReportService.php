<?php

namespace App\Services;

use App\Http\Controllers\Admin\InventoryController;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Storefront\Loyalty;
use Illuminate\Support\Facades\DB;

/**
 * Báo cáo doanh thu CI3 admin/Report và dữ liệu export admin/Export.
 * Doanh thu = SUM(transaction.amount) của đơn status 3. Không lấy giá sản phẩm hiện tại.
 */
class ReportService
{
    /** @var list<string> */
    public const MODULES = [
        'orders',
        'products',
        'customers',
        'inventory',
        'voucher',
        'revenue',
        'top_products',
        'dashboard',
    ];

    public function __construct(
        private readonly OrderAdminService $orders,
        private readonly AdminDashboardService $dashboard,
    ) {}

    public function supports(string $module): bool
    {
        return in_array($module, self::MODULES, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function page(string $type, string $dateFrom, string $dateTo): array
    {
        [$where, $whereT, $lines] = $this->completedRange($type, $dateFrom, $dateTo);
        $totals = DB::selectOne("SELECT COUNT(id) AS total_orders, SUM(total) AS total_revenue FROM orders WHERE {$where}");
        $qty = DB::selectOne("SELECT SUM(o.qty) AS total_qty FROM order_items o JOIN orders t ON o.order_id = t.id WHERE {$whereT}");
        $products = DB::select($this->productSql($whereT));

        $year = (int) date('Y');
        $chart = [];
        for ($month = 1; $month <= 12; $month++) {
            $start = strtotime(sprintf('%04d-%02d-01 00:00:00', $year, $month));
            $end = strtotime(date('Y-m-t 23:59:59', $start));
            $row = DB::selectOne(
                'SELECT SUM(total) AS revenue FROM orders WHERE status = \'completed\' AND created_at BETWEEN ? AND ?',
                [date('Y-m-d H:i:s', $start), date('Y-m-d H:i:s', $end)]
            );
            $chart[] = (int) ($row->revenue ?? 0);
        }

        return [
            'type' => $type,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'filter_lines' => $lines,
            'total_orders' => (int) ($totals->total_orders ?? 0),
            'total_revenue' => (int) ($totals->total_revenue ?? 0),
            'total_qty' => (int) ($qty->total_qty ?? 0),
            'products' => $products,
            'chart' => $chart,
            'year' => $year,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function definition(string $module, array $query): array
    {
        return match ($module) {
            'revenue' => $this->revenueDefinition($query),
            'top_products' => $this->topProductsDefinition($query),
            'orders' => $this->ordersDefinition($query),
            'products' => $this->productsDefinition($query),
            'customers' => $this->customersDefinition(),
            'inventory' => $this->inventoryDefinition($query),
            'voucher' => $this->voucherDefinition(),
            'dashboard' => $this->dashboardDefinition($query),
            default => abort(404),
        };
    }

    public function filename(array $definition, string $ext): string
    {
        $base = preg_replace('/[^a-z0-9\-]/', '', (string) ($definition['filename'] ?? 'bao-cao')) ?: 'bao-cao';

        return $base . '-' . date('Y-m-d') . '.' . $ext;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{0: string, 1: string, 2: list<array{label: string, value: string}>}
     */
    private function completedRange(string $type, string $dateFrom, string $dateTo): array
    {
        $where = "status = 'completed'";
        $start = 0;
        $end = 0;
        $lines = [];

        if ($type !== '') {
            switch ($type) {
                case 'day':
                    $start = (int) strtotime(date('Y-m-d 00:00:00'));
                    $end = (int) strtotime(date('Y-m-d 23:59:59'));
                    $lines[] = ['label' => 'Kỳ', 'value' => 'Hôm nay'];
                    break;
                case 'week':
                    $start = (int) strtotime('monday this week');
                    $end = (int) strtotime('sunday this week 23:59:59');
                    $lines[] = ['label' => 'Kỳ', 'value' => 'Tuần này'];
                    break;
                case 'month':
                    $start = (int) strtotime(date('Y-m-01 00:00:00'));
                    $end = (int) strtotime(date('Y-m-t 23:59:59'));
                    $lines[] = ['label' => 'Kỳ', 'value' => 'Tháng này'];
                    break;
                case 'year':
                    $start = (int) strtotime(date('Y-01-01 00:00:00'));
                    $end = (int) strtotime(date('Y-12-31 23:59:59'));
                    $lines[] = ['label' => 'Kỳ', 'value' => 'Năm nay'];
                    break;
            }
        } else {
            if ($dateFrom !== '' && ($ts = strtotime($dateFrom . ' 00:00:00'))) {
                $start = (int) $ts;
                $lines[] = ['label' => 'Từ ngày', 'value' => $dateFrom];
            }
            if ($dateTo !== '' && ($ts = strtotime($dateTo . ' 23:59:59'))) {
                $end = (int) $ts;
                $lines[] = ['label' => 'Đến ngày', 'value' => $dateTo];
            }
        }

        if ($start && $end) {
            $where .= " AND created_at BETWEEN '" . date('Y-m-d H:i:s', $start) . "' AND '" . date('Y-m-d H:i:s', $end) . "'";
        } elseif ($start) {
            $where .= " AND created_at >= '" . date('Y-m-d H:i:s', $start) . "'";
        } elseif ($end) {
            $where .= " AND created_at <= '" . date('Y-m-d H:i:s', $end) . "'";
        }

        $whereT = str_replace(['created_at', 'status'], ['t.created_at', 't.status'], $where);

        return [$where, $whereT, $lines];
    }

    private function productSql(string $whereT): string
    {
        return "
            SELECT p.id, p.name,
                (SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = p.id AND status = 'active') AS quantity,
                (
                    SELECT SUM(o.qty)
                    FROM order_items o
                    JOIN orders t ON o.order_id = t.id
                    WHERE o.product_id = p.id AND {$whereT}
                ) AS da_ban,
                COALESCE(
                    (
                        SELECT o.unit_price
                        FROM order_items o
                        JOIN orders t ON o.order_id = t.id
                        WHERE o.product_id = p.id AND {$whereT}
                        LIMIT 1
                    ),
                    p.list_price
                ) AS price
            FROM products p
            ORDER BY da_ban DESC
        ";
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function revenueDefinition(array $query): array
    {
        $page = $this->page(
            trim((string) ($query['type'] ?? '')),
            trim((string) ($query['date_from'] ?? '')),
            trim((string) ($query['date_to'] ?? ''))
        );
        $rows = [];
        foreach ($page['chart'] as $index => $revenue) {
            $rows[] = [
                'month' => 'Tháng ' . ($index + 1) . '/' . $page['year'],
                'revenue' => (int) $revenue,
            ];
        }

        return array_merge($this->blank('Báo cáo doanh thu', 'doanh-thu', 'Thống kê đơn hàng hoàn thành', $query), [
            'filters' => $page['filter_lines'],
            'summary' => [
                ['label' => 'Đơn hàng thành công', 'value' => $page['total_orders'], 'type' => 'int'],
                ['label' => 'Số lượng bán ra', 'value' => $page['total_qty'], 'type' => 'int'],
                ['label' => 'Doanh thu thực thu', 'value' => $page['total_revenue'], 'type' => 'money'],
            ],
            'columns' => [
                ['key' => 'month', 'label' => 'Tháng', 'type' => 'text'],
                ['key' => 'revenue', 'label' => 'Doanh thu (VNĐ)', 'type' => 'money'],
            ],
            'rows' => $rows,
            'totals' => [
                ['label' => 'Tổng doanh thu năm ' . $page['year'], 'value' => array_sum($page['chart']), 'type' => 'money'],
            ],
            'notes' => ['Biểu đồ theo tháng chỉ tính đơn hoàn thành (status = 3).'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function topProductsDefinition(array $query): array
    {
        $type = trim((string) ($query['type'] ?? ''));
        $from = trim((string) ($query['date_from'] ?? ''));
        $to = trim((string) ($query['date_to'] ?? ''));
        [, $whereT, $lines] = $this->completedRange($type, $from, $to);
        $rows = DB::select($this->productSql($whereT));
        [$limit, $offset, $scope] = $this->pageWindow($query, 9999);
        if ($limit !== null) {
            $rows = array_slice($rows, $offset, $limit);
        }

        $out = [];
        $soldTotal = 0;
        $stt = 0;
        foreach ($rows as $row) {
            $sold = (int) ($row->da_ban ?? 0);
            if ($sold <= 0 && $limit === null) {
                continue;
            }
            $stt++;
            $stock = (int) ($row->quantity ?? 0);
            $soldTotal += $sold;
            $out[] = [
                'stt' => $stt,
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'price' => (float) $row->price,
                'sold' => $sold,
                'stock' => $stock,
                'status' => $this->reportStockLabel($stock),
            ];
        }

        $def = $this->blank('Báo cáo sản phẩm bán chạy', 'san-pham-ban-chay', 'Theo số lượng đã bán (đơn hoàn thành)', $query);
        $def['scope'] = $scope;
        $def['landscape'] = true;
        $def['filters'] = $lines;
        $def['columns'] = [
            ['key' => 'stt', 'label' => 'STT', 'type' => 'int'],
            ['key' => 'id', 'label' => 'Mã SP', 'type' => 'int'],
            ['key' => 'name', 'label' => 'Tên sản phẩm', 'type' => 'text'],
            ['key' => 'price', 'label' => 'Giá bán', 'type' => 'money'],
            ['key' => 'sold', 'label' => 'Đã bán', 'type' => 'int'],
            ['key' => 'stock', 'label' => 'Tồn kho', 'type' => 'int'],
            ['key' => 'status', 'label' => 'Trạng thái', 'type' => 'text'],
        ];
        $def['rows'] = $out;
        $def['totals'] = [['label' => 'Tổng đã bán', 'value' => $soldTotal, 'type' => 'int']];

        return $def;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function ordersDefinition(array $query): array
    {
        $filters = [
            'q' => trim((string) ($query['q'] ?? '')),
            'status' => (string) ($query['status'] ?? ''),
            'payment' => (string) ($query['payment'] ?? ''),
            'date_from' => trim((string) ($query['date_from'] ?? '')),
            'date_to' => trim((string) ($query['date_to'] ?? '')),
        ];
        [$limit, $offset, $scope] = $this->pageWindow($query, OrderAdminService::PER_PAGE);
        $rows = $this->orders->rowsForExport($filters, $limit, $offset);
        $out = [];
        $sum = 0;
        $stt = $offset ?? 0;
        foreach ($rows as $row) {
            $stt++;
            $amount = (int) $row->amount;
            $sum += $amount;
            $out[] = [
                'stt' => $stt,
                'id' => (int) $row->id,
                'user_name' => (string) $row->user_name,
                'user_phone' => (string) $row->user_phone,
                'created' => $row->created_at ? date('d/m/Y H:i', strtotime((string) $row->created_at)) : '—',
                'amount' => $amount,
                'payment' => ((string) $row->payment === 'Chuyển khoản') ? 'Chuyển khoản' : 'COD',
                'status' => $this->orderStatus((string) $row->status),
            ];
        }
        $lines = [];
        if ($filters['q'] !== '') {
            $lines[] = ['label' => 'Tên / SĐT', 'value' => $filters['q']];
        }
        if (in_array($filters['status'], ['0', '1', '2', '3', '4'], true)) {
            $lines[] = ['label' => 'Trạng thái', 'value' => $this->orderStatus($filters['status'])];
        }
        if ($filters['payment'] !== '') {
            $lines[] = ['label' => 'Thanh toán', 'value' => $filters['payment'] === 'transfer' ? 'Chuyển khoản' : 'COD'];
        }
        if ($filters['date_from'] !== '') {
            $lines[] = ['label' => 'Từ ngày', 'value' => $filters['date_from']];
        }
        if ($filters['date_to'] !== '') {
            $lines[] = ['label' => 'Đến ngày', 'value' => $filters['date_to']];
        }

        $def = $this->blank('Báo cáo đơn hàng', 'don-hang', '', $query);
        $def['scope'] = $scope;
        $def['landscape'] = true;
        $def['filters'] = $lines;
        $def['columns'] = [
            ['key' => 'stt', 'label' => 'STT', 'type' => 'int'],
            ['key' => 'id', 'label' => 'Mã ĐH', 'type' => 'int'],
            ['key' => 'user_name', 'label' => 'Khách hàng', 'type' => 'text'],
            ['key' => 'user_phone', 'label' => 'SĐT', 'type' => 'text'],
            ['key' => 'created', 'label' => 'Ngày đặt', 'type' => 'text'],
            ['key' => 'amount', 'label' => 'Giá trị', 'type' => 'money'],
            ['key' => 'payment', 'label' => 'Thanh toán', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Trạng thái', 'type' => 'text'],
        ];
        $def['rows'] = $out;
        $def['totals'] = [
            ['label' => 'Số đơn', 'value' => count($out), 'type' => 'int'],
            ['label' => 'Tổng giá trị', 'value' => $sum, 'type' => 'money'],
        ];

        return $def;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function productsDefinition(array $query): array
    {
        $name = trim((string) ($query['name'] ?? ''));
        $catalogId = (int) ($query['catalog_id'] ?? 0);
        [$limit, $offset, $scope] = $this->pageWindow($query, 10);
        $builder = DB::table('products')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->orderByDesc('products.id');
        if ($name !== '') {
            $builder->where('products.name', 'like', '%' . addcslashes($name, '%_\\') . '%');
        }
        if ($catalogId > 0) {
            $builder->where('products.category_id', $catalogId);
        }
        if ($limit !== null) {
            $builder->offset($offset)->limit($limit);
        }
        $rows = $builder->get([
            'products.id as id',
            'products.name as name',
            'products.list_price as price',
            'products.discount_amount as discount',
            'products.view_count as view',
            'products.sold_count as buyed',
            'categories.name as namecatalog',
            DB::raw("(SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = products.id AND status = 'active') as quantity"),
        ]);
        $out = [];
        $stt = $offset ?? 0;
        foreach ($rows as $row) {
            $stt++;
            $out[] = [
                'stt' => $stt,
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'catalog' => (string) $row->namecatalog,
                'price' => (int) $row->price,
                'discount' => (int) $row->discount,
                'quantity' => (int) $row->quantity,
                'buyed' => (int) $row->buyed,
                'view' => (int) $row->view,
            ];
        }
        $lines = [];
        if ($name !== '') {
            $lines[] = ['label' => 'Tên sản phẩm', 'value' => $name];
        }
        if ($catalogId > 0) {
            $catalog = DB::table('categories')->where('id', $catalogId)->value('name');
            $lines[] = ['label' => 'Danh mục', 'value' => $catalog ? (string) $catalog : '#' . $catalogId];
        }
        $def = $this->blank('Báo cáo sản phẩm', 'san-pham', '', $query);
        $def['scope'] = $scope;
        $def['landscape'] = true;
        $def['filters'] = $lines;
        $def['columns'] = [
            ['key' => 'stt', 'label' => 'STT', 'type' => 'int'],
            ['key' => 'id', 'label' => 'Mã SP', 'type' => 'int'],
            ['key' => 'name', 'label' => 'Tên sản phẩm', 'type' => 'text'],
            ['key' => 'catalog', 'label' => 'Danh mục', 'type' => 'text'],
            ['key' => 'price', 'label' => 'Giá bán', 'type' => 'money'],
            ['key' => 'discount', 'label' => 'Giảm giá', 'type' => 'money'],
            ['key' => 'quantity', 'label' => 'Tồn kho', 'type' => 'int'],
            ['key' => 'buyed', 'label' => 'Đã bán', 'type' => 'int'],
            ['key' => 'view', 'label' => 'Lượt xem', 'type' => 'int'],
        ];
        $def['rows'] = $out;

        return $def;
    }

    /**
     * @return array<string, mixed>
     */
    private function customersDefinition(): array
    {
        $rows = User::query()->orderByDesc('id')->get();
        $out = [];
        $stt = 0;
        foreach ($rows as $row) {
            $stt++;
            $out[] = [
                'stt' => $stt,
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'email' => (string) $row->email,
                'phone' => (string) ($row->phone ?? ''),
                'address' => (string) ($row->address ?? ''),
            ];
        }
        $def = $this->blank('Báo cáo khách hàng', 'khach-hang', '', []);
        $def['columns'] = [
            ['key' => 'stt', 'label' => 'STT', 'type' => 'int'],
            ['key' => 'id', 'label' => 'Mã KH', 'type' => 'int'],
            ['key' => 'name', 'label' => 'Họ tên', 'type' => 'text'],
            ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
            ['key' => 'phone', 'label' => 'Điện thoại', 'type' => 'text'],
            ['key' => 'address', 'label' => 'Địa chỉ', 'type' => 'text'],
        ];
        $def['rows'] = $out;
        $def['totals'] = [['label' => 'Tổng khách hàng', 'value' => count($out), 'type' => 'int']];

        return $def;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function inventoryDefinition(array $query): array
    {
        $filters = [
            'name' => trim((string) ($query['name'] ?? '')),
            'catalog_id' => (int) ($query['catalog_id'] ?? 0),
            'sku' => trim((string) ($query['sku'] ?? '')),
            'stock' => (string) ($query['stock'] ?? ''),
        ];
        [$limit, $offset, $scope] = $this->pageWindow($query, InventoryController::PER_PAGE);
        $rows = app(InventoryController::class)->rowsForExport($filters, $limit, $offset);
        $out = [];
        $stt = $offset ?? 0;
        foreach ($rows as $row) {
            $stt++;
            $stock = (int) $row->stock;
            $min = (int) $row->min_stock;
            if ($stock <= 0) {
                $status = 'Hết hàng';
            } elseif ($stock <= $min) {
                $status = 'Sắp hết';
            } else {
                $status = 'Còn hàng';
            }
            $out[] = [
                'stt' => $stt,
                'sku' => (string) $row->sku,
                'product_name' => (string) $row->product_name,
                'catalog_name' => (string) ($row->catalog_name ?? ''),
                'color' => $row->color !== '' && $row->color !== null ? (string) $row->color : '—',
                'size' => $row->size !== '' && $row->size !== null ? (string) $row->size : '—',
                'stock' => $stock,
                'min_stock' => $min,
                'status' => $status,
            ];
        }
        $lines = [];
        if ($filters['name'] !== '') {
            $lines[] = ['label' => 'Tên sản phẩm', 'value' => $filters['name']];
        }
        if ($filters['sku'] !== '') {
            $lines[] = ['label' => 'SKU', 'value' => $filters['sku']];
        }
        if ($filters['catalog_id'] > 0) {
            $catalog = DB::table('categories')->where('id', $filters['catalog_id'])->value('name');
            $lines[] = ['label' => 'Danh mục', 'value' => $catalog ? (string) $catalog : '#' . $filters['catalog_id']];
        }
        if ($filters['stock'] !== '') {
            $labels = ['out' => 'Hết hàng', 'low' => 'Sắp hết', 'ok' => 'Còn hàng'];
            $lines[] = ['label' => 'Trạng thái tồn', 'value' => $labels[$filters['stock']] ?? $filters['stock']];
        }
        $def = $this->blank('Báo cáo tồn kho', 'ton-kho', '', $query);
        $def['scope'] = $scope;
        $def['landscape'] = true;
        $def['filters'] = $lines;
        $def['columns'] = [
            ['key' => 'stt', 'label' => 'STT', 'type' => 'int'],
            ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
            ['key' => 'product_name', 'label' => 'Sản phẩm', 'type' => 'text'],
            ['key' => 'catalog_name', 'label' => 'Danh mục', 'type' => 'text'],
            ['key' => 'color', 'label' => 'Màu', 'type' => 'text'],
            ['key' => 'size', 'label' => 'Size', 'type' => 'text'],
            ['key' => 'stock', 'label' => 'Tồn', 'type' => 'int'],
            ['key' => 'min_stock', 'label' => 'Tối thiểu', 'type' => 'int'],
            ['key' => 'status', 'label' => 'Trạng thái', 'type' => 'text'],
        ];
        $def['rows'] = $out;
        $def['totals'] = [['label' => 'Số biến thể', 'value' => count($out), 'type' => 'int']];

        return $def;
    }

    /**
     * @return array<string, mixed>
     */
    private function voucherDefinition(): array
    {
        $rows = Voucher::query()->orderByDesc('id')->get();
        $out = [];
        $stt = 0;
        foreach ($rows as $row) {
            $stt++;
            $discount = $row->discount_type === 'percent'
                ? (int) $row->discount_value . '%'
                : number_format((int) $row->discount_value, 0, ',', '.') . ' ₫';
            $used = (int) $row->used_count;
            $limit = (int) $row->usage_limit;
            $valid = '';
            if ($row->starts_at) {
                $valid .= 'Từ ' . $row->starts_at->format('d/m/Y');
            }
            if ($row->ends_at) {
                $valid .= ($valid !== '' ? ' · ' : '') . 'Đến ' . $row->ends_at->format('d/m/Y');
            }
            $out[] = [
                'stt' => $stt,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'discount' => $discount,
                'tier_min' => Loyalty::tierLabel($row->tier_min),
                'used' => $limit > 0 ? $used . ' / ' . $limit : (string) $used,
                'status' => ((int) $row->is_active === 1) ? 'Đang bật' : 'Tắt',
                'valid' => $valid !== '' ? $valid : 'Không giới hạn',
            ];
        }
        $def = $this->blank('Báo cáo voucher', 'voucher', '', []);
        $def['landscape'] = true;
        $def['columns'] = [
            ['key' => 'stt', 'label' => 'STT', 'type' => 'int'],
            ['key' => 'code', 'label' => 'Mã', 'type' => 'text'],
            ['key' => 'name', 'label' => 'Tên', 'type' => 'text'],
            ['key' => 'discount', 'label' => 'Giảm', 'type' => 'text'],
            ['key' => 'tier_min', 'label' => 'Hạng tối thiểu', 'type' => 'text'],
            ['key' => 'used', 'label' => 'Đã dùng', 'type' => 'text'],
            ['key' => 'status', 'label' => 'Trạng thái', 'type' => 'text'],
            ['key' => 'valid', 'label' => 'Hiệu lực', 'type' => 'text'],
        ];
        $def['rows'] = $out;
        $def['totals'] = [['label' => 'Số voucher', 'value' => count($out), 'type' => 'int']];

        return $def;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function dashboardDefinition(array $query): array
    {
        $period = trim((string) ($query['period'] ?? '')) ?: '30d';
        $from = trim((string) ($query['from'] ?? ''));
        $to = trim((string) ($query['to'] ?? ''));
        $dash = $this->dashboard->data(auth('admin')->user(), $period, $from, $to);
        $summary = [];
        foreach ($dash['kpi_groups'] as $group) {
            foreach ($group['items'] as $kpi) {
                $summary[] = [
                    'label' => (string) $kpi['label'],
                    'value' => $kpi['value'],
                    'type' => ($kpi['format'] ?? '') === 'money' ? 'money' : 'int',
                ];
            }
        }
        $rows = [];
        $rank = 0;
        foreach ($dash['top_products'] as $item) {
            $rank++;
            $rows[] = [
                'rank' => $rank,
                'name' => (string) ($item->name ?? ''),
                'sold' => (int) ($item->total_sold ?? 0),
                'revenue' => (int) ($item->total_amount ?? 0),
            ];
        }
        $def = $this->blank('Báo cáo tổng quan', 'tong-quan', 'KPI kinh doanh', $query);
        $def['filters'] = [['label' => 'Khoảng thời gian', 'value' => (string) ($dash['range']['label'] ?? '')]];
        $def['summary'] = $summary;
        $def['columns'] = [
            ['key' => 'rank', 'label' => '#', 'type' => 'int'],
            ['key' => 'name', 'label' => 'Sản phẩm', 'type' => 'text'],
            ['key' => 'sold', 'label' => 'Đã bán', 'type' => 'int'],
            ['key' => 'revenue', 'label' => 'Doanh thu', 'type' => 'money'],
        ];
        $def['rows'] = $rows;
        if ($rows === []) {
            $def['notes'] = ['Chưa có dữ liệu sản phẩm bán chạy trong kỳ đã chọn.'];
        }

        return $def;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{0: ?int, 1: ?int, 2: string}
     */
    private function pageWindow(array $query, int $defaultPerPage): array
    {
        if (($query['scope'] ?? '') !== 'page') {
            return [null, null, 'Toàn bộ dữ liệu lọc'];
        }
        $per = (int) ($query['per_page'] ?? 0);
        if ($per <= 0) {
            $per = $defaultPerPage;
        }
        $offset = max(0, (int) ($query['offset'] ?? 0));

        return [$per, $offset, 'Trang hiện tại'];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function blank(string $title, string $filename, string $subtitle, array $query): array
    {
        $scope = (($query['scope'] ?? '') === 'page') ? 'Trang hiện tại' : 'Toàn bộ dữ liệu lọc';

        return [
            'title' => $title,
            'filename' => $filename,
            'subtitle' => $subtitle,
            'shop' => (string) config('shop.name', 'qD Design'),
            'generated_at' => date('d/m/Y H:i'),
            'scope' => $scope,
            'landscape' => false,
            'filters' => [],
            'summary' => [],
            'columns' => [],
            'rows' => [],
            'totals' => [],
            'notes' => [],
        ];
    }

    private function reportStockLabel(int $stock): string
    {
        if ($stock <= 0) {
            return 'Hết hàng';
        }
        if ($stock <= 5) {
            return 'Sắp hết';
        }

        return 'An toàn';
    }

    private function orderStatus(string $status): string
    {
        return [
            '0' => 'Chờ xử lý',
            'pending' => 'Chờ xử lý',
            '1' => 'Đã xác nhận',
            'confirmed' => 'Đã xác nhận',
            '2' => 'Đang giao',
            'packed' => 'Đang giao',
            '3' => 'Thành công',
            'completed' => 'Thành công',
            '4' => 'Đã hủy / Hoàn',
            'cancelled' => 'Đã hủy / Hoàn',
        ][$status] ?? $status;
    }

    private function dateTime(int $timestamp): string
    {
        return $timestamp > 0 ? date('d/m/Y H:i', $timestamp) : '—';
    }
}
