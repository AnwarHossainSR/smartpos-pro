<?php

namespace App\Support\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use RuntimeException;

/**
 * Global scope that transparently constrains every query on a tenant-scoped
 * model to the current tenant. This is the core guarantee of data isolation:
 * no query can leak rows across tenants unless the scope is explicitly removed.
 *
 * Fails closed: a query reaching this scope with no tenant bound throws rather
 * than silently running unscoped (which would leak every tenant's rows). The
 * only way around it is explicit — `Model::withoutTenancy()` when you control
 * the query, or `TenantContext::runUnscoped()` around code you don't (e.g. a
 * framework internal on a legitimately cross-tenant/public route).
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);
        $tenantId = $context->id();

        if ($tenantId !== null) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);

            return;
        }

        if ($context->unscopedAllowed()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Unscoped query on tenant-owned model [%s] with no tenant bound. '.
            'Use %s::withoutTenancy() if this is intentional (a public route, a super-admin '.
            'route, or a job/command that should bind a specific tenant via TenantContext::runAs()).',
            $model::class,
            class_basename($model),
        ));
    }
}
