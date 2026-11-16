<?php

namespace App\Services;

use App\Models\ProductReview;
use App\Support\Admin\AdminPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Port application/helpers/admin_dashboard_helper.php (đọc số liệu dashboard).
 * Một lần đọc transaction trong cửa sổ kỳ + kỳ trước, rồi gom KPI/biểu đồ trong PHP.
 */
class AdminDashboardService
{
    public const LOW_STOCK = 10;

    /**
     * @return array<string, mixed>
     */
    public function data(mixed $admin, string $period = '30d', string $from = '', string $to = ''): array
    {
        $range = $this->resolvePeriod($period, $from, $to);
        $rows = DB::table('orders')
            ->whereBetween('created_at', [$this->sqlTime($range['prev_start']), $this->sqlTime($range['end'])])
            ->get(['id', 'created_at', 'status', 'total', 'user_id', 'customer_name', 'customer_email', 'customer_phone']);

        $current = $this->bucket($rows, $range['start'], $range['end']);
        $previous = $this->bucket($rows, $range['prev_start'], $range['prev_end']);
        $kpis = $this->kpis($current, $previous, $range);
        $cost = $this->costKpis($range, $current['revenue'], $previous['revenue']);
        $perm = $this->permissions($admin);

        return [
            'range' => $range,
            'low_stock_threshold' => self::LOW_STOCK,
            'kpi_groups' => $this->kpiGroups(array_merge($kpis, $cost)),
            'charts' => $this->charts($rows, $range),
            'top_products' => $perm['products'] ? $this->topProducts($range, 8) : [],
            'pending_orders' => $perm['orders'] ? $this->pendingOrders(12) : [],
            'top_customers' => $perm['users'] ? $this->topCustomers($range, 6) : [],
            'top_categories' => $perm['catalog'] ? $this->topCategories($range, 6) : [],
            'low_stock' => $perm['inventory'] ? $this->lowStock(self::LOW_STOCK, 8) : [],
            'recent_reviews' => $perm['products'] ? $this->recentReviews($range, 15) : [],
            'notify_groups' => $this->notifications($admin, $perm, $range),
            'perm' => $perm,
        ];
    }

    /**
     * @return array{key: string, start: int, end: int, prev_start: int, prev_end: int, label: string}
     */
    public function resolvePeriod(string $period, string $from = '', string $to = ''): array
    {
        $period = strtolower(trim($period));
        $now = time();
        $todayStart = strtotime('today', $now);
        $todayEnd = strtotime('tomorrow', $todayStart) - 1;

        switch ($period) {
            case 'today':
                $start = $todayStart;
                $end = $now;
                $label = 'Hôm nay';
                break;
            case '7d':
                $start = strtotime('-6 days', $todayStart);
                $end = $now;
                $label = '7 ngày qua';
                break;
            case 'month':
                $start = strtotime(date('Y-m-01 00:00:00'));
                $end = $now;
                $label = 'Tháng này';
                break;
            case 'year':
                $start = strtotime(date('Y-01-01 00:00:00'));
                $end = $now;
                $label = 'Năm nay';
                break;
            case 'custom':
                $start = $this->parseDate($from, $todayStart);
                $end = $this->parseDate($to, $todayEnd);
                if ($end < $start) {
                    [$start, $end] = [$end, $start];
                }
                $end = strtotime('23:59:59', $end);
                $label = date('d/m/Y', $start) . ' – ' . date('d/m/Y', $end);
                break;
            case '30d':
            default:
                $period = '30d';
                $start = strtotime('-29 days', $todayStart);
                $end = $now;
                $label = '30 ngày qua';
                break;
        }

        $span = max(1, $end - $start + 1);
        $prevEnd = $start - 1;

        return [
            'key' => $period,
            'start' => (int) $start,
            'end' => (int) $end,
            'prev_start' => (int) ($prevEnd - $span + 1),
            'prev_end' => (int) $prevEnd,
            'label' => $label,
        ];
    }

    /**
     * @param  array{all: int, s0: int, s1: int, s2: int, s3: int, s4: int, revenue: float}  $cur
     * @param  array{all: int, s0: int, s1: int, s2: int, s3: int, s4: int, revenue: float}  $prev
     * @param  array{start: int, end: int, prev_start: int, prev_end: int, label: string}  $range
     * @return list<array<string, mixed>>
     */
    private function kpis(array $cur, array $prev, array $range): array
    {
        $hint = 'Trong ' . $range['label'];
        $low = $this->lowStockCount(self::LOW_STOCK);

        $item = function (string $key, string $label, float|int $value, float|int $previous, string $format, bool $static = false, string $extraHint = '') use ($hint) {
            return [
                'key' => $key,
                'label' => $label,
                'value' => $value,
                'delta' => $static ? 0 : $this->pctChange($value, $previous),
                'format' => $format,
                'hint' => $extraHint !== '' ? $extraHint : $hint,
                'static_delta' => $static,
            ];
        };

        return [
            $item('orders_today', 'Đơn hàng', $cur['all'], $prev['all'], 'int'),
            $item('revenue_today', 'Doanh thu', $cur['revenue'], $prev['revenue'], 'money'),
            $item('pending', 'Đơn chờ xử lý', $cur['s0'] + $cur['s1'], $prev['s0'] + $prev['s1'], 'int'),
            $item('shipping', 'Đơn đang giao', $cur['s2'], $prev['s2'], 'int'),
            $item('completed', 'Đơn hoàn thành', $cur['s3'], $prev['s3'], 'int'),
            $item('cancelled', 'Đơn đã hủy', $cur['s4'], $prev['s4'], 'int'),
            $item('new_users', 'Khách hàng mới', $this->countUsers($range['start'], $range['end']), $this->countUsers($range['prev_start'], $range['prev_end']), 'int'),
            $item('low_stock', 'Sản phẩm sắp hết', $low, 0, 'int', true, 'Tồn ≤ ' . self::LOW_STOCK),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array{all: int, s0: int, s1: int, s2: int, s3: int, s4: int, revenue: float}
     */
    private function bucket($rows, int $start, int $end): array
    {
        $out = ['all' => 0, 's0' => 0, 's1' => 0, 's2' => 0, 's3' => 0, 's4' => 0, 'revenue' => 0.0];
        foreach ($rows as $row) {
            $created = $row->created_at ? strtotime((string) $row->created_at) : 0;
            if ($created < $start || $created > $end) {
                continue;
            }
            $out['all']++;
            $status = match ((string) $row->status) {
                'pending' => 's0',
                'confirmed' => 's1',
                'packed' => 's2',
                'completed' => 's3',
                'cancelled' => 's4',
                default => null,
            };
            if ($status !== null) {
                $out[$status]++;
            }
            if ((string) $row->status === 'completed') {
                $out['revenue'] += (float) $row->total;
            }
        }

        return $out;
    }

    /**
     * @param  array{start: int, end: int, prev_start: int, prev_end: int, label: string}  $range
     * @return list<array<string, mixed>>
     */
    private function costKpis(array $range, float $revenue, float $revenuePrev): array
    {
        $hint = 'Trong ' . $range['label'];
        $import = $this->sumReceipts($range['start'], $range['end']);
        $importPrev = $this->sumReceipts($range['prev_start'], $range['prev_end']);
        $cogs = $this->sumCogs($range['start'], $range['end']);
        $cogsPrev = $this->sumCogs($range['prev_start'], $range['prev_end']);

        return [
            [
                'key' => 'stock_import', 'label' => 'Giá trị nhập kho', 'value' => $import,
                'delta' => $this->pctChange($import, $importPrev), 'format' => 'money', 'tone' => 'warning',
                'hint' => $hint, 'caption' => 'Tổng phiếu nhập đã xác nhận trong kỳ', 'static_delta' => false,
            ],
            [
                'key' => 'inventory_value', 'label' => 'Giá trị tồn kho', 'value' => $this->inventoryValue(),
                'delta' => 0, 'format' => 'money', 'tone' => 'default', 'hint' => 'Tồn hiện tại',
                'caption' => 'Tồn × giá vốn biến thể hiện tại', 'static_delta' => true,
            ],
            [
                'key' => 'cogs', 'label' => 'Giá vốn đã bán (COGS)', 'value' => $cogs,
                'delta' => $this->pctChange($cogs, $cogsPrev), 'format' => 'money', 'tone' => 'default',
                'hint' => $hint, 'caption' => 'SL × giá vốn trên đơn hoàn thành', 'static_delta' => false,
            ],
            [
                'key' => 'gross_profit', 'label' => 'Lợi nhuận gộp', 'value' => $revenue - $cogs,
                'delta' => $this->pctChange($revenue - $cogs, $revenuePrev - $cogsPrev), 'format' => 'money',
                'tone' => 'success', 'hint' => $hint, 'caption' => 'Doanh thu − COGS trong kỳ', 'static_delta' => false,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function kpiGroups(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $indexed[$item['key']] = $item;
        }
        $groups = [
            ['id' => 'sales', 'title' => 'Bán hàng', 'icon' => 'fa-solid fa-chart-line', 'keys' => ['orders_today', 'revenue_today', 'completed', 'cancelled']],
            ['id' => 'ops', 'title' => 'Vận hành', 'icon' => 'fa-solid fa-gears', 'keys' => ['pending', 'shipping', 'new_users', 'low_stock']],
            ['id' => 'finance', 'title' => 'Kho & tài chính', 'icon' => 'fa-solid fa-warehouse', 'keys' => ['stock_import', 'inventory_value', 'cogs', 'gross_profit']],
        ];
        $result = [];
        foreach ($groups as $group) {
            $group['items'] = array_values(array_filter(array_map(fn ($key) => $indexed[$key] ?? null, $group['keys'])));
            unset($group['keys']);
            $result[] = $group;
        }

        return $result;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @param  array{start: int, end: int}  $range
     * @return array{labels: list<string>, revenue: list<float>, orders: list<int>}
     */
    private function charts($rows, array $range): array
    {
        $labels = [];
        $revenue = [];
        $orders = [];
        $daySpan = (int) floor(($range['end'] - $range['start']) / 86400) + 1;
        $step = $daySpan <= 45 ? 86400 : 7 * 86400;

        for ($ts = strtotime('midnight', $range['start']); $ts <= $range['end']; $ts += $step) {
            $chunkEnd = min($ts + $step - 1, $range['end']);
            $labels[] = date('d/m', $ts);
            $bucket = $this->bucket($rows, $ts, $chunkEnd);
            $revenue[] = $bucket['revenue'];
            $orders[] = $bucket['all'];
        }

        return compact('labels', 'revenue', 'orders');
    }

    /**
     * @param  array{start: int, end: int, prev_start: int, prev_end: int}  $range
     * @return list<object>
     */
    private function topProducts(array $range, int $limit): array
    {
        $rows = DB::select(
            'SELECT p.id, p.name, p.list_price AS price,
                (SELECT path FROM product_images WHERE product_id = p.id AND variant_id IS NULL AND is_primary = 1 ORDER BY id LIMIT 1) AS image_link,
                (SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = p.id AND status = \'active\') AS quantity,
                SUM(o.qty) AS total_sold, SUM(o.line_subtotal) AS total_amount
             FROM order_items o
             INNER JOIN products p ON o.product_id = p.id
             INNER JOIN orders t ON o.order_id = t.id
             WHERE t.status = \'completed\' AND t.created_at >= ? AND t.created_at <= ?
             GROUP BY p.id, p.name, p.list_price
             ORDER BY total_sold DESC
             LIMIT ' . (int) $limit,
            [$this->sqlTime($range['start']), $this->sqlTime($range['end'])]
        );

        if ($rows === []) {
            return [];
        }

        $ids = array_map(fn ($row) => (int) $row->id, $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $prev = DB::select(
            "SELECT o.product_id, COALESCE(SUM(o.qty), 0) AS sold
             FROM order_items o
             INNER JOIN orders t ON o.order_id = t.id
             WHERE t.status = 'completed' AND o.product_id IN ({$placeholders})
               AND t.created_at >= ? AND t.created_at <= ?
             GROUP BY o.product_id",
            [...$ids, $this->sqlTime($range['prev_start']), $this->sqlTime($range['prev_end'])]
        );
        $prevMap = [];
        foreach ($prev as $row) {
            $prevMap[(int) $row->product_id] = (int) $row->sold;
        }
        foreach ($rows as $row) {
            $row->trend_pct = $this->pctChange((int) $row->total_sold, $prevMap[(int) $row->id] ?? 0);
        }

        return $rows;
    }

    /** @return list<object> */
    private function pendingOrders(int $limit): array
    {
        return DB::table('orders')
            ->select([
                'id',
                'customer_name as user_name',
                'customer_phone as user_phone',
                'total as amount',
                'status',
                'created_at',
            ])
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    /** @return list<object> */
    private function topCustomers(array $range, int $limit): array
    {
        return DB::select(
            'SELECT user_id, customer_name AS user_name, customer_email AS user_email, customer_phone AS user_phone, COUNT(*) AS order_count, SUM(total) AS total_spent
             FROM orders
             WHERE status = \'completed\' AND created_at >= ? AND created_at <= ? AND user_id IS NOT NULL
             GROUP BY user_id, customer_name, customer_email, customer_phone
             ORDER BY total_spent DESC
             LIMIT ' . (int) $limit,
            [$this->sqlTime($range['start']), $this->sqlTime($range['end'])]
        );
    }

    /** @return list<object> */
    private function topCategories(array $range, int $limit): array
    {
        return DB::select(
            'SELECT c.id, c.name, SUM(o.qty) AS total_sold, SUM(o.line_subtotal) AS total_amount
             FROM order_items o
             INNER JOIN products p ON o.product_id = p.id
             INNER JOIN categories c ON p.category_id = c.id
             INNER JOIN orders t ON o.order_id = t.id
             WHERE t.status = \'completed\' AND t.created_at >= ? AND t.created_at <= ?
             GROUP BY c.id, c.name
             ORDER BY total_sold DESC
             LIMIT ' . (int) $limit,
            [$this->sqlTime($range['start']), $this->sqlTime($range['end'])]
        );
    }

    /** @return list<object> */
    private function lowStock(int $threshold, int $limit): array
    {
        return DB::table('products')
            ->select(['products.id', 'products.name', 'products.list_price as price'])
            ->selectRaw("(SELECT path FROM product_images WHERE product_id = products.id AND variant_id IS NULL AND is_primary = 1 ORDER BY id LIMIT 1) as image_link")
            ->selectRaw("(SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = products.id AND status = 'active') as quantity")
            ->whereRaw("(SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = products.id AND status = 'active') <= ?", [$threshold])
            ->orderBy('quantity')
            ->limit($limit)
            ->get()
            ->all();
    }

    /** @return list<object> */
    private function recentReviews(array $range, int $limit): array
    {
        if (! Schema::hasTable('product_reviews')) {
            return [];
        }

        return DB::table('product_reviews')
            ->leftJoin('products', 'products.id', '=', 'product_reviews.product_id')
            ->where('product_reviews.status', ProductReview::STATUS_APPROVED)
            ->whereBetween('product_reviews.created_at', [$this->sqlTime($range['start']), $this->sqlTime($range['end'])])
            ->orderByDesc('product_reviews.created_at')
            ->limit($limit)
            ->get([
                'product_reviews.user_name',
                'product_reviews.product_id',
                'product_reviews.stars',
                'product_reviews.created_at as created',
                'products.name as product_name',
            ])
            ->all();
    }

    /**
     * @param  array<string, bool>  $perm
     * @param  array{start: int, end: int, label: string}  $range
     * @return list<array<string, mixed>>
     */
    private function notifications(mixed $admin, array $perm, array $range): array
    {
        $groups = [];
        if (! empty($perm['orders'])) {
            $counts = DB::table('orders')->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
            $pendingNew = (int) ($counts['pending'] ?? 0);
            $shipping = (int) ($counts['packed'] ?? 0);
            $failed = (int) ($counts['cancelled'] ?? 0);
            $badge = $pendingNew + $shipping + $failed;
            $groups[] = [
                'title' => 'Đơn hàng',
                'icon' => 'fa-solid fa-cart-shopping',
                'tone' => ($pendingNew > 0 || $failed > 0) ? 'danger' : ($shipping > 0 ? 'warning' : 'success'),
                'action_label' => $badge > 0 ? 'Xử lý' : 'Xem chi tiết',
                'action_url' => url('admin/orders'),
                'lines' => [
                    $this->line($pendingNew, 'Không có đơn mới cần xác nhận.', 'Có %s đơn mới cần xác nhận.'),
                    $this->line($shipping, 'Không có đơn đang giao.', 'Có %s đơn đang giao.'),
                    $this->line($failed, 'Không có đơn giao thất bại / hủy cần xem.', 'Có %s đơn giao thất bại hoặc đã hủy.'),
                ],
            ];
        }

        if (! empty($perm['inventory'])) {
            $low = $this->lowStockCount(self::LOW_STOCK);
            $out = $this->lowStockCount(0);
            $groups[] = [
                'title' => 'Kho hàng',
                'icon' => 'fa-solid fa-boxes-stacked',
                'tone' => $out > 0 ? 'danger' : ($low > 0 ? 'warning' : 'success'),
                'action_label' => ($low + $out) > 0 ? 'Xử lý' : 'Xem chi tiết',
                'action_url' => url('admin/inventory'),
                'lines' => [
                    $this->line($low, 'Không có sản phẩm sắp hết hàng.', 'Có %s sản phẩm sắp hết hàng (≤ ' . self::LOW_STOCK . ').'),
                    $this->line($out, 'Không có sản phẩm hết hàng.', 'Có %s sản phẩm đã hết hàng.'),
                ],
            ];
        }

        if (AdminPermission::can($admin, 'panel.home')) {
            $groups[] = [
                'title' => 'Hệ thống',
                'icon' => 'fa-solid fa-server',
                'tone' => 'success',
                'action_label' => 'Xem chi tiết',
                'action_url' => url('admin/store'),
                'lines' => ['Trạng thái hệ thống: hoạt động bình thường.'],
            ];
        }

        return $groups;
    }

    /** @return array<string, bool> */
    private function permissions(mixed $admin): array
    {
        $keys = ['orders', 'revenue', 'products', 'inventory', 'users', 'catalog', 'voucher', 'posts', 'slider', 'staff'];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = AdminPermission::sectionAllowed($key, $admin);
        }

        return $out;
    }

    private function countUsers(int $start, int $end): int
    {
        return (int) DB::table('users')->whereBetween('created_at', [$this->sqlTime($start), $this->sqlTime($end)])->count();
    }

    private function sumReceipts(int $start, int $end): float
    {
        if (! Schema::hasTable('stock_receipts')) {
            return 0;
        }

        $row = DB::selectOne(
            'SELECT COALESCE(SUM(total_amount), 0) AS total FROM stock_receipts
             WHERE status = ? AND COALESCE(confirmed_at, created_at) >= ? AND COALESCE(confirmed_at, created_at) <= ?',
            ['confirmed', $this->sqlTime($start), $this->sqlTime($end)]
        );

        return (float) ($row->total ?? 0);
    }

    private function sumCogs(int $start, int $end): float
    {
        if (! Schema::hasTable('order_items')) {
            return 0;
        }

        $row = DB::selectOne(
            'SELECT COALESCE(SUM(o.qty * COALESCE(o.cost_price, 0)), 0) AS total
             FROM order_items o INNER JOIN orders t ON t.id = o.order_id
             WHERE t.status = \'completed\' AND t.created_at >= ? AND t.created_at <= ?',
            [$this->sqlTime($start), $this->sqlTime($end)]
        );

        return (float) ($row->total ?? 0);
    }

    private function inventoryValue(): float
    {
        if (! Schema::hasTable('product_variants')) {
            return 0;
        }
        $where = "WHERE status = 'active'";
        $row = DB::selectOne("SELECT COALESCE(SUM(stock * cost_price), 0) AS total FROM product_variants {$where}");

        return (float) ($row->total ?? 0);
    }

    private function pctChange(float|int $current, float|int $previous): float
    {
        $current = (float) $current;
        $previous = (float) $previous;
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function parseDate(string $str, int $fallback): int
    {
        $str = trim($str);
        if ($str === '') {
            return $fallback;
        }
        $ts = strtotime($str);

        return $ts ? (int) $ts : $fallback;
    }

    private function line(int $count, string $zero, string $template): string
    {
        return $count <= 0 ? $zero : sprintf($template, number_format($count, 0, ',', '.'));
    }

    private function sqlTime(int $unix): string
    {
        return date('Y-m-d H:i:s', $unix);
    }

    private function lowStockCount(int $threshold): int
    {
        return (int) DB::table('products')
            ->whereRaw("(SELECT COALESCE(SUM(stock),0) FROM product_variants WHERE product_id = products.id AND status = 'active') <= ?", [$threshold])
            ->count();
    }
}
