<?php

namespace App\Providers;

use App\Events\InventoryLow;
use App\Events\SaleCompleted;
use App\Events\SaleReturned;
use App\Models\Customer;
use App\Services\Webhooks\WebhookService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class WebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(SaleCompleted::class, function (SaleCompleted $event) {
            app(WebhookService::class)->dispatch('sale.completed', [
                'sale_id' => $event->sale->id,
                'total' => (float) $event->sale->total,
                'payment_status' => $event->sale->payment_status,
                'sold_at' => $event->sale->sold_at,
            ]);
        });

        Event::listen(InventoryLow::class, function (InventoryLow $event) {
            app(WebhookService::class)->dispatch('inventory.low', [
                'product_id' => $event->balance->product_id,
                'warehouse_id' => $event->balance->warehouse_id,
                'quantity' => (float) $event->balance->quantity,
            ]);
        });

        Event::listen(SaleReturned::class, function (SaleReturned $event) {
            app(WebhookService::class)->dispatch('sale.returned', [
                'return_id' => $event->return->id,
                'sale_id' => $event->return->sale_id,
                'total' => (float) $event->return->total,
                'returned_at' => $event->return->returned_at,
            ]);
        });

        Customer::created(function (Customer $customer) {
            app(WebhookService::class)->dispatch('customer.created', [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ]);
        });
    }
}
