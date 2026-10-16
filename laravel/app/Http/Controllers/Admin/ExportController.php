<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Export\ReportFileWriter;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Port admin/Export. GET như CI3. Chỉ ROLE_ADMIN. Không ghi dữ liệu nghiệp vụ.
 */
class ExportController extends Controller
{
    public function excel(Request $request, string $module, ReportService $reports, ReportFileWriter $writer): Response
    {
        $definition = $this->definition($request, $module, $reports);

        return response($writer->excel($definition), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $reports->filename($definition, 'xlsx') . '"',
        ]);
    }

    public function pdf(Request $request, string $module, ReportService $reports, ReportFileWriter $writer): Response
    {
        $definition = $this->definition($request, $module, $reports);

        return response($writer->pdf($definition), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $reports->filename($definition, 'pdf') . '"',
        ]);
    }

    public function preview(Request $request, string $module, ReportService $reports, ReportFileWriter $writer): View
    {
        $definition = $this->definition($request, $module, $reports);

        return view('admin.export.preview', [
            'html' => $writer->html($definition),
            'admin_header_title' => (string) $definition['title'],
        ]);
    }

    /** @return array<string, mixed> */
    private function definition(Request $request, string $module, ReportService $reports): array
    {
        $module = trim($module);
        if ($module === '' || ! $reports->supports($module) || str_contains($module, '..') || str_contains($module, '/') || str_contains($module, '\\')) {
            abort(404);
        }
        $admin = Auth::guard('admin')->user();
        if (! $admin instanceof Admin || (int) $admin->level !== Admin::ROLE_ADMIN) {
            abort(403, 'Bạn không có quyền xuất báo cáo.');
        }

        return $reports->definition($module, $request->query());
    }
}
