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
        'pos' => ['view', 'create', 'refund', 'discount', 'price_override'],
        'sales' => ['view', 'create', 'update', 'delete', 'export', 'refund'],
        'purchases' => ['view', 'create', 'update', 'delete', 'approve', 'export'],
        'customers' => ['view', 'create', 'update', 'delete', 'export'],
        'suppliers' => ['view', 'create', 'update', 'delete', 'export'],
        'expenses' => ['view', 'create', 'update', 'delete', 'approve'],
        'accounting' => ['view', 'create', 'update', 'approve', 'export'],
        'reports' => ['view', 'export'],
        'coupons' => ['view', 'create', 'update', 'delete'],
        'loyalty' => ['view', 'manage'],
        'subscriptions' => ['view', 'manage'],
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'settings' => ['view', 'update'],
        'audit' => ['view'],
    ];

    /**
     * @return array<int, array{slug:string, name:string, module:string, action:string}>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::MATRIX as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'slug' => "{$module}.{$action}",
                    'name' => ucfirst($action).' '.ucfirst($module),
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
                'pos.view', 'pos.create', 'pos.refund', 'pos.discount', 'pos.price_override',
                'sales.view', 'sales.refund', 'customers.view', 'customers.create',
                'reports.view', 'reports.export',
            ]],
            'cashier' => ['name' => 'Cashier', 'permissions' => [
                'dashboard.view', 'products.view', 'pos.view', 'pos.create',
                'sales.view', 'customers.view', 'customers.create',
            ]],
            'accountant' => ['name' => 'Accountant', 'permissions' => [
                'dashboard.view', 'accounting.view', 'accounting.create', 'accounting.approve',
                'expenses.view', 'expenses.create', 'expenses.approve',
                'reports.view', 'reports.export', 'sales.view', 'purchases.view',
            ]],
            'viewer' => ['name' => 'Viewer', 'permissions' => [
                'dashboard.view', 'products.view', 'sales.view', 'reports.view',
            ]],
        ];
    }
}
