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
        'stocktakes' => ['view', 'create', 'update'],
        'reorder-suggestions' => ['view', 'create'],
        // `pos.approve` backs the supervisor-PIN flow: ApprovalService only accepts a
        // PIN from a user who also holds this, so it must exist as a grantable slug or
        // the till can never escalate to anyone but the owner.
        'pos' => ['view', 'create', 'refund', 'discount', 'price_override', 'approve'],
        'sales' => ['view', 'create', 'update', 'delete', 'export', 'refund'],
        'deliveries' => ['view', 'create', 'update'],
        // `edit` is deliberately distinct from `update`: `update` covers receive/cancel,
        // `edit` amends an already-posted purchase and restates its stock ledger.
        'purchases' => ['view', 'create', 'update', 'edit', 'delete', 'approve', 'export', 'return'],
        'customers' => ['view', 'create', 'update', 'delete', 'export'],
        'suppliers' => ['view', 'create', 'update', 'delete', 'export'],
        'expenses' => ['view', 'create', 'update', 'delete', 'approve'],
        'accounting' => ['view', 'create', 'update', 'approve', 'export'],
        'taxes' => ['view', 'manage'],
        'reports' => ['view', 'export'],
        'coupons' => ['view', 'create', 'update', 'delete'],
        'loyalty' => ['view', 'manage'],
        'subscriptions' => ['view', 'manage'],
        // `shifts.manage` is deliberately self-scoped — it grants clocking *yourself*
        // in and out, never editing anyone else's timesheet. Reading another user's
        // shifts additionally requires `users.view`.
        'shifts' => ['view', 'manage'],
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
                $label = self::label($action).' '.self::label($module);

                $permissions[] = [
                    'slug' => "{$module}.{$action}",
                    'name' => $label,
                    'title' => $label,
                    'module' => $module,
                    'action' => $action,
                ];
            }
        }

        return $permissions;
    }

    /** `price_override` => `Price Override`, `reorder-suggestions` => `Reorder Suggestions`. */
    private static function label(string $segment): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $segment));
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
                'pos.approve',
                'stocktakes.view', 'stocktakes.create', 'stocktakes.update',
                'reorder-suggestions.view', 'reorder-suggestions.create',
                'sales.view', 'sales.refund', 'customers.view', 'customers.create',
                'deliveries.view', 'deliveries.create', 'deliveries.update',
                'reports.view', 'reports.export',
                'shifts.view', 'shifts.manage',
            ]],
            'cashier' => ['name' => 'Cashier', 'permissions' => [
                'dashboard.view', 'products.view', 'pos.view', 'pos.create',
                'sales.view', 'customers.view', 'customers.create',
                'shifts.view', 'shifts.manage',
            ]],
            'accountant' => ['name' => 'Accountant', 'permissions' => [
                'dashboard.view', 'accounting.view', 'accounting.create', 'accounting.approve',
                'expenses.view', 'expenses.create', 'expenses.approve', 'taxes.view', 'taxes.manage',
                'reports.view', 'reports.export', 'sales.view', 'purchases.view',
                'shifts.view', 'shifts.manage',
            ]],
            'viewer' => ['name' => 'Viewer', 'permissions' => [
                'dashboard.view', 'products.view', 'sales.view', 'reports.view',
                'shifts.view', 'shifts.manage',
            ]],
        ];
    }
}
