<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ProductReview;
use App\Services\ReviewService;
use App\Support\Storefront\Urls;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Port admin/Product_review. Duyệt, từ chối, xóa. Quyền product.manage.
 */
class ProductReviewController extends Controller
{
    public function index(Request $request, ReviewService $reviews): View
    {
        $status = trim((string) $request->query('status', ''));
        if ($status === '') {
            $status = 'pending';
        }
        $keyword = trim((string) $request->query('q', ''));
        $data = $reviews->adminList($status, $keyword);

        return view('admin.product-reviews.index', [
            'list' => $data['list'],
            'counts' => $data['counts'],
            'filter_status' => $status,
            'keyword' => $keyword,
            'productUrl' => fn ($row) => Urls::product((object) ['id' => (int) $row->product_id, 'name' => $row->product_name ?: '']),
            'admin_header_title' => 'Duyệt đánh giá sản phẩm',
        ]);
    }

    public function handlePath(Request $request, string $path, ReviewService $reviews): RedirectResponse
    {
        $parts = explode('/', trim($path, '/'));
        $action = strtolower($parts[0] ?? '');
        $id = (int) ($parts[1] ?? 0);
        if (! in_array($action, ['approve', 'reject', 'delete'], true) || $id <= 0) {
            abort(404);
        }
        if (! $request->isMethod('post')) {
            abort(404);
        }

        $admin = Auth::guard('admin')->user();
        $adminId = $admin instanceof Admin ? (int) $admin->id : 0;

        if ($action === 'delete') {
            if (! $reviews->delete($id)) {
                return redirect()->to(url('admin/product-reviews'))->with('message_fail', 'Đánh giá không tồn tại.');
            }

            return redirect()->to(url('admin/product-reviews'))->with('message_success', 'Đã xóa đánh giá.');
        }

        $status = $action === 'approve' ? ProductReview::STATUS_APPROVED : ProductReview::STATUS_REJECTED;
        $message = $action === 'approve' ? 'Đã duyệt đánh giá.' : 'Đã từ chối đánh giá.';
        if (! $reviews->setStatus($id, $status, $adminId)) {
            return redirect()->to(url('admin/product-reviews'))->with('message_fail', 'Đánh giá không tồn tại.');
        }

        return redirect()->to(url('admin/product-reviews'))->with('message_success', $message);
    }
}
