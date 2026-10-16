<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Port admin/Report. Chỉ đơn hoàn thành (status 3), amount đã lưu trên đơn.
 */
class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $type = trim((string) $request->query('type', ''));
        $page = $reports->page(
            $type,
            trim((string) $request->query('date_from', '')),
            trim((string) $request->query('date_to', ''))
        );
        $admin = Auth::guard('admin')->user();

        return view('admin.report.index', [
            'page' => $page,
            'canExport' => $admin instanceof Admin && (int) $admin->level === Admin::ROLE_ADMIN,
            'admin_header_title' => 'Thống kê doanh thu',
        ]);
    }
}
