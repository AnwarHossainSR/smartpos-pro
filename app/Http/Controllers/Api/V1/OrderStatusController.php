<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderStatusResource;
use App\Models\Sale;
use App\Support\Tenancy\TenantContext;
use App\Traits\ApiResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The one unauthenticated read in the API: a customer following the link on
 * their receipt. The token is the credential, so the lookup is deliberately
 * blunt — an exact match on an unguessable 48-character string, no tenant
 * context, and a 404 for anything that isn't a finished sale (a held cart or a
 * draft is not the customer's business).
 */
class OrderStatusController extends Controller
{
    use ApiResponse;

    public function show(TenantContext $tenants, string $token): OrderStatusResource
    {
        $sale = Sale::withoutTenancy()
            ->where('public_token', $token)
            ->whereIn('status', [SaleStatus::Completed->value, SaleStatus::Cancelled->value])
            ->first();

        if (! $sale) {
            throw new NotFoundHttpException('Order not found.');
        }

        // The relations are tenant-scoped models of their own; bind the sale's tenant
        // for the duration of the load rather than punching a second hole in the scope.
        return $tenants->runAs($sale->tenant_id, fn () => new OrderStatusResource(
            $sale->load('items', 'business', 'branch'),
        ));
    }
}
