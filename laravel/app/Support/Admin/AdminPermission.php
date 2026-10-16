<?php

namespace App\Support\Admin;

use App\Models\Admin;

/**
 * Port application/helpers/permission_helper.php.
 * Level lấy từ model/guard, không từ request.
 *
 * ROLE_ADMIN = 0, ROLE_MOD = 1, ROLE_USER = 2.
 */
final class AdminPermission
{
    public static function roleLabel(mixed $level): string
    {
        return match ((int) $level) {
            Admin::ROLE_ADMIN => 'Admin',
            Admin::ROLE_MOD => 'Mod',
            Admin::ROLE_USER => 'User',
            default => '—',
        };
    }

    public static function canAccessPanel(mixed $admin): bool
    {
        if (! $admin || ! isset($admin->level)) {
            return false;
        }

        return in_array((int) $admin->level, [Admin::ROLE_ADMIN, Admin::ROLE_MOD, Admin::ROLE_USER], true);
    }

    public static function can(mixed $admin, string $permission): bool
    {
        if (! $admin || ! isset($admin->level)) {
            return false;
        }

        $level = (int) $admin->level;
        $isAdmin = $level === Admin::ROLE_ADMIN;
        $isMod = $level === Admin::ROLE_MOD;
        $isUser = $level === Admin::ROLE_USER;
        $isStaff = $isAdmin || $isMod;

        return match ($permission) {
            'order.manage' => $isStaff,
            'order.chat', 'panel.home' => $isAdmin || $isMod || $isUser,
            'inventory.view', 'stock.view' => $isAdmin || $isMod || $isUser,
            'inventory.count' => $isAdmin || $isMod,
            'inventory.adjust', 'inventory.manage' => $isAdmin,
            'stock.receipt' => $isAdmin || $isMod,
            'stock.manage', 'product.manage', 'catalog.manage', 'banner.manage', 'content.manage' => $isStaff,
            'voucher.manage' => $isAdmin,
            'product.delete_single', 'product.delete', 'product.bulk_delete',
            'catalog.delete', 'staff.delete', 'staff.grant_admin', 'staff.edit_admin', 'staff.manage' => $isAdmin,
            'ai.manage' => $isStaff,
            default => $isAdmin,
        };
    }

    public static function isStaff(mixed $admin): bool
    {
        return $admin && in_array((int) ($admin->level ?? -1), [Admin::ROLE_ADMIN, Admin::ROLE_MOD], true);
    }

    public static function sidebarSlugPermission(string $slug): ?string
    {
        return match ($slug) {
            'voucher' => 'voucher.manage',
            'admin' => 'staff.manage',
            'inventory-adjust' => 'inventory.count',
            default => null,
        };
    }

    public static function sidebarAllowed(mixed $slug, mixed $admin): bool
    {
        if (! self::canAccessPanel($admin)) {
            return false;
        }
        if (is_array($slug)) {
            foreach ($slug as $item) {
                if (self::sidebarAllowed($item, $admin)) {
                    return true;
                }
            }

            return false;
        }

        $slug = (string) $slug;
        $userOnly = ['home'];
        $staff = ['catalog', 'products', 'product-reviews', 'slider', 'users', 'admin', 'posts', 'page', 'banner', 'branding', 'store', 'tuyendung', 'hoptac', 'vanchuyen', 'voucher'];
        $warehouse = ['inventory', 'receipts', 'inventory-adjust', 'stock-movements', 'suppliers', 'stock-receipts', 'stock-inventory'];
        $level = (int) $admin->level;

        if ($level === Admin::ROLE_USER) {
            if (in_array($slug, $userOnly, true)) {
                return true;
            }
            if ($slug === 'inventory' || in_array($slug, $warehouse, true)) {
                return self::can($admin, 'inventory.view') || self::can($admin, 'stock.view');
            }

            return false;
        }

        $required = self::sidebarSlugPermission($slug);
        if ($required !== null) {
            return self::can($admin, $required);
        }
        if (in_array($slug, $userOnly, true) || in_array($slug, $staff, true)) {
            return true;
        }
        if (in_array($slug, $warehouse, true)) {
            return self::can($admin, 'stock.view') || self::can($admin, 'inventory.view');
        }
        if ($slug === 'orders' || $slug === 'report') {
            return self::can($admin, 'order.manage');
        }

        return false;
    }

    public static function sectionAllowed(string $section, mixed $admin): bool
    {
        if (! $admin) {
            return false;
        }

        return match ($section) {
            'orders' => self::can($admin, 'order.manage'),
            'revenue' => self::can($admin, 'order.manage') && self::isStaff($admin),
            'products' => self::sidebarAllowed('products', $admin),
            'inventory' => self::sidebarAllowed('inventory', $admin),
            'users' => self::sidebarAllowed('users', $admin),
            'catalog' => self::sidebarAllowed('catalog', $admin),
            'voucher' => self::sidebarAllowed('voucher', $admin),
            'posts' => self::sidebarAllowed('posts', $admin),
            'slider' => self::sidebarAllowed('slider', $admin),
            'staff' => self::sidebarAllowed('admin', $admin),
            default => false,
        };
    }

    public static function kpiAllowed(string $key, mixed $admin): bool
    {
        $map = [
            'orders_today' => 'orders',
            'revenue_today' => 'revenue',
            'pending' => 'orders',
            'shipping' => 'orders',
            'completed' => 'orders',
            'cancelled' => 'orders',
            'new_users' => 'users',
            'low_stock' => 'inventory',
            'stock_import' => 'inventory',
            'cogs' => 'revenue',
            'gross_profit' => 'revenue',
            'inventory_value' => 'inventory',
        ];

        return self::sectionAllowed($map[$key] ?? 'orders', $admin);
    }

    public static function orderStatusText(mixed $status): string
    {
        return match ((string) $status) {
            '0' => 'Mới đặt',
            '1' => 'Đã xác nhận',
            '2' => 'Đang giao hàng',
            '3' => 'Hoàn thành',
            '4' => 'Đã hủy',
            default => '—',
        };
    }
}
