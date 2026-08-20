<?php

namespace App\Support\Rbac;

/**
 * Single source of truth for the permission matrix. Permissions are global
 * (module.action); roles that reference them are tenant-scoped.
 */
class PermissionCatalog
{
    /** module => list of actions available on that module. */
    public const MATRIX = [
        'dashboard' => ['view'],
        'branches' => ['view', 'create', 'update', 'delete'],
        'warehouses' => ['view', 'create', 'update', 'delete'],
        'products' => ['view', 'create', 'update', 'delete', 'export'],
        'inventory' => ['view', 'create', 'update', 'adjust', 'export', 'approve'],
        // `approve` is the supervisor bit: it lets a user's PIN authorise an over-limit
        // discount, a price override or a void at the till (see ApprovalService).
        'pos' => ['view', 'create', 'refund', 'discount', 'price_override', 'approve'],
        'sales' => ['view', 'create', 'update', 'delete', 'export', 'refund'],
        'purchases' => ['view', 'create', 'update', 'delete', 'approve', 'export', 'edit', 'return'],
        'customers' => ['view', 'create', 'update', 'delete', 'export'],
        'suppliers' => ['view', 'create', 'update', 'delete', 'export'],
        'expenses' => ['view', 'create', 'update', 'delete', 'approve'],
        'accounting' => ['view', 'create', 'update', 'approve', 'export'],
        'taxes' => ['view', 'manage'],
        'reports' => ['view', 'export'],
        'coupons' => ['view', 'create', 'update', 'delete'],
        'loyalty' => ['view', 'manage'],
        'subscriptions' => ['view', 'manage'],
        'shifts' => ['view', 'manage'],
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'settings' => ['view', 'update'],
        'stocktakes' => ['view', 'create', 'update'],
        'reorder-suggestions' => ['view', 'create'],
        'audit' => ['view'],
    ];

    /**
     * The flattened catalogue. `slug` is the machine key stored in `permissions.name`
     * (spatie keys everything off `name`); `title` is the human label stored in
     * `permissions.title`. `name` is kept as an alias of `title` so the shape stays
     * backwards compatible for older callers.
     *
     * @return array<int, array{slug:string, title:string, name:string, module:string, action:string}>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::MATRIX as $module => $actions) {
            foreach ($actions as $action) {
                $title = ucfirst(str_replace('_', ' ', $action)).' '.ucfirst(str_replace('-', ' ', $module));

                $permissions[] = [
                    'slug' => "{$module}.{$action}",
                    'title' => $title,
                    'name' => $title,
                    'module' => $module,
                    'action' => $action,
                ];
            }
        }

        return $permissions;
    }

    /**
     * Default roles and the permission slugs granted to each. `owner` is granted
     * everything implicitly in code, so it is omitted here.
     *
     * @return array<string, array{name:string, permissions: string[]|'*'}>
     */
    public static function defaultRoles(): array
    {
        return [
            'admin' => ['name' => 'Admin', 'permissions' => '*'],
            'branch_manager' => ['name' => 'Branch Manager', 'permissions' => [
                'dashboard.view', 'branches.view', 'warehouses.view',
                'products.view', 'inventory.view', 'inventory.adjust', 'inventory.approve',
                'pos.view', 'pos.create', 'pos.refund', 'pos.discount', 'pos.price_override', 'pos.approve',
                'sales.view', 'sales.refund', 'customers.view', 'customers.create',
                'reports.view', 'reports.export', 'shifts.view', 'shifts.manage',
                'stocktakes.view', 'stocktakes.create', 'stocktakes.update',
            ]],
            'cashier' => ['name' => 'Cashier', 'permissions' => [
                'dashboard.view', 'products.view', 'pos.view', 'pos.create',
                'sales.view', 'customers.view', 'customers.create', 'shifts.view',
            ]],
            'accountant' => ['name' => 'Accountant', 'permissions' => [
                'dashboard.view', 'accounting.view', 'accounting.create', 'accounting.approve',
                'expenses.view', 'expenses.create', 'expenses.approve', 'taxes.view', 'taxes.manage',
                'reports.view', 'reports.export', 'sales.view', 'purchases.view',
            ]],
            'viewer' => ['name' => 'Viewer', 'permissions' => [
                'dashboard.view', 'products.view', 'sales.view', 'reports.view',
            ]],
        ];
    }
}
