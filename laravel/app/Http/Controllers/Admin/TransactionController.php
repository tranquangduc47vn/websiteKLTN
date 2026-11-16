<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\OrderAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * B10 — Đơn hàng admin. Port CI3 admin/Transaction.
 * GET /admin/orders/{số} là offset phân trang, không phải mã đơn.
 */
class TransactionController extends Controller
{
    public function __construct(private readonly OrderAdminService $orders)
    {
    }

    public function index(Request $request, int $offset = 0): View
    {
        $filters = [
            'q' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', ''),
            'payment' => (string) $request->query('payment', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        return view('admin.orders.index', [
            'page' => $this->orders->list($filters, $offset),
            'filters' => $filters,
            'admin_header_title' => 'Đơn đặt hàng',
        ]);
    }

    public function handlePath(Request $request, string $path = ''): View|RedirectResponse|JsonResponse
    {
        $parts = explode('/', trim($path, '/'));
        $action = strtolower($parts[0] ?? '');
        $id = (int) ($parts[1] ?? 0);

        return match ($action) {
            '', 'index' => $this->index($request, (int) ($parts[1] ?? 0)),
            'detail' => $this->detail($id),
            'accept' => $this->act($this->orders->accept($id)),
            'ship' => $this->act($this->orders->ship($id)),
            'complete' => $this->act($this->orders->complete($id)),
            'cancel' => $this->cancel($request, $id),
            'del' => $this->refuseDelete($request),
            'deldetail' => $this->removeLine($request, $id),
            'chat' => redirect()->to(url('admin/support-chat')),
            'pending_count' => response()->json([
                'ok' => true,
                'count' => $this->orders->pendingCount(),
            ]),
            default => abort(404),
        };
    }

    private function detail(int $id): View|RedirectResponse
    {
        $order = Transaction::query()->find($id);
        if (! $order) {
            return redirect()->to(url('admin/orders'))->with('message_fail', 'Đơn đặt hàng không tồn tại!');
        }

        return view('admin.orders.detail', [
            'order' => $order,
            'lines' => $this->orders->lines($id),
            'admin_header_title' => 'Chi tiết đơn đặt hàng',
        ]);
    }

    private function cancel(Request $request, int $id): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }

        return $this->act($this->orders->cancel($id, (string) $request->input('reason', '')));
    }

    private function refuseDelete(Request $request): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }

        return redirect()->to(url('admin/orders'))->with(
            'message_fail',
            'Đơn hàng không được xóa. Hãy cập nhật trạng thái (ví dụ: Đã hủy / Hoàn) thay vì xóa.'
        );
    }

    private function removeLine(Request $request, int $id): RedirectResponse
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        $result = $this->orders->removeLine($id);

        return redirect()->to($result['to'])->with($result['ok'] ? 'message_success' : 'message_fail', $result['message']);
    }

    /** @param  array{ok: bool, message: string, to: string}  $result */
    private function act(array $result): RedirectResponse
    {
        return redirect()->to($result['to'])->with($result['ok'] ? 'message_success' : 'message_fail', $result['message']);
    }
}
