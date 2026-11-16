<?php

namespace App\View\Composers;

use App\Support\Admin\AdminPermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/** Dữ liệu layout admin. Không gắn vào view storefront. */
class AdminComposer
{
    public static array $views = [
        'layouts.admin.*',
        'admin.*',
    ];

    public function compose(View $view): void
    {
        $request = request();
        if (! $request->attributes->has('admin.shared')) {
            $admin = Auth::guard('admin')->user();
            $badge = 0;
            $waiting = 0;
            if ($admin && AdminPermission::can($admin, 'order.manage')) {
                $badge = (int) DB::table('orders')->whereIn('status', ['pending', 'confirmed', 'packed'])->count();
            }
            if ($admin && AdminPermission::can($admin, 'order.chat') && Schema::hasTable('ai_conversations')) {
                $waiting = (int) DB::table('ai_conversations')->whereIn('status', ['waiting_staff', 'handed_off'])->count();
            }
            $request->attributes->set('admin.shared', [
                'login' => $admin,
                'admin_pending_orders' => $badge,
                'admin_support_waiting' => $waiting,
            ]);
        }

        $view->with($request->attributes->get('admin.shared'));
    }
}
