<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\AdminPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Lịch sử biến động kho, chỉ đọc. Port admin/Stock_movements. 25 dòng/trang.
 */
class StockMovementsController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request, int $offset = 0): View|RedirectResponse
    {
        if ($denied = $this->denyView()) {
            return $denied;
        }

        $sku = trim((string) $request->query('sku', ''));
        $type = trim((string) $request->query('type', ''));
        $offset = max(0, $offset);
        $query = $this->filtered($sku, $type);
        $total = (int) (clone $query)->count('stock_movements.id');
        $rows = $query->orderByDesc('stock_movements.id')->offset($offset)->limit(self::PER_PAGE)->get([
            'stock_movements.*',
            'product_variants.sku',
            'products.name as product_name',
        ]);

        return view('admin.movements.index', [
            'rows' => $rows,
            'total' => $total,
            'offset' => $offset,
            'filterSku' => $sku,
            'filterType' => $type,
            'admin_header_title' => 'Lịch sử biến động kho',
        ]);
    }

    public function handlePath(Request $request, string $path = ''): View|RedirectResponse
    {
        $parts = explode('/', trim($path, '/'));
        $action = strtolower($parts[0] ?? '');
        if ($action === '' || $action === 'index') {
            return $this->index($request, (int) ($parts[1] ?? 0));
        }

        abort(404);
    }

    private function filtered(string $sku, string $type)
    {
        $query = DB::table('stock_movements')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_movements.variant_id')
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id');
        if ($sku !== '') {
            $query->where('product_variants.sku', 'like', '%' . addcslashes($sku, '%_\\') . '%');
        }
        if (in_array($type, ['in', 'out', 'adjust'], true)) {
            $query->where('stock_movements.movement_type', $type);
        }

        return $query;
    }

    private function denyView(): ?RedirectResponse
    {
        if (AdminPermission::can(Auth::guard('admin')->user(), 'stock.view')) {
            return null;
        }

        return redirect()->to(url('admin'))->with('message_fail', 'Bạn không có quyền xem lịch sử biến động.');
    }
}
