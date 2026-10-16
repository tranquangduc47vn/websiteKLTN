<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * B8 — Dashboard admin. Port CI3 admin/Home + admin_dashboard_helper.
 * URL: GET /admin và GET /admin/home.
 */
class HomeController extends Controller
{
    public function __construct(private readonly AdminDashboardService $dashboard)
    {
    }

    public function index(Request $request): View
    {
        $admin = Auth::guard('admin')->user();

        return view('admin.home.index', [
            'dash' => $this->dashboard->data(
                $admin,
                (string) $request->query('period', '30d'),
                (string) $request->query('from', ''),
                (string) $request->query('to', '')
            ),
            'admin_header_title' => 'Tổng quan quản trị',
        ]);
    }

    public function handlePath(Request $request, string $path = ''): View
    {
        $head = strtolower(explode('/', trim($path, '/'))[0] ?? '');
        if ($head === '' || $head === 'index') {
            return $this->index($request);
        }

        abort(404);
    }
}
