<?php

namespace App\Services\Billing;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\PaymentIntent;
use App\Models\Sale;
use App\Models\Tenant;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createCheckoutSession(Sale $sale): string
    {
        $customer = $sale->customer;

        if (!$customer) {
            $customer = Customer::create([
                'email' => $sale->customer->email,
                'name' => $sale->customer->name,
                'metadata' => [
                    'sale_id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                ],
            ]);
        } else {
            $customer = Customer::retrieve($customer->stripe_id ?? $customer->stripe_customer_id ?? '');
        }

        if (!$customer->stripe_id) {
            $customer = Customer::create([
                'email' => $sale->customer->email,
                'name' => $sale->customer->name,
            ]);
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'Invoice Payment - ' . $sale->invoice_number,
                        'description' => 'Outstanding balance',
                    ],
                    'unit_amount' => $sale->due_amount * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => config('app.url') . "/pay/{$sale->payment_url_token}?success=1",
            'cancel_url' => config('app.url') . "/pay/{$sale->payment_url_token}?canceled=1",
            'metadata' => [
                'sale_id' => $sale->id,
                'tenant_id' => $sale->tenant_id,
            ],
        ]);

        return $session->id;
    }

    public function createSubscriptionCheckout(string $stripePriceId, string $returnUrl): string
    {
        $session = Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $stripePriceId,
            ]],
            'success_url' => $returnUrl,
            'cancel_url' => config('app.url') . '/billing?canceled=1',
        ]);

        return $session->id;
    }

    public function constructFromWebhookEvent(\Stripe\Event $event): array
    {
        $type = $event->type;
        $data = $event->data->object;

        switch ($type) {
            case 'checkout.session.completed':
                $session = \Stripe\Checkout\Session::retrieve($data->id);
                return [
                    'type' => 'checkout.session.completed',
                    'sale_id' => $session->metadata['sale_id'] ?? null,
                    'customer_email' => $session->customer_email,
                    'amount_total' => $session->amount_total,
                    'payment_status' => $session->payment_status,
                ];

            case 'invoice.payment_succeeded':
                $invoice = \Stripe\Invoice::retrieve($data->id);
                return [
                    'type' => 'invoice.payment_succeeded',
                    'sale_id' => $invoice->metadata['sale_id'] ?? null,
                    'customer_id' => $invoice->customer,
                    'amount_paid' => $invoice->amount_paid,
                    'currency' => $invoice->currency,
                ];

            case 'invoice.payment_failed':
                $invoice = \Stripe\Invoice::retrieve($data->id);
                return [
                    'type' => 'invoice.payment_failed',
                    'sale_id' => $invoice->metadata['sale_id'] ?? null,
                    'customer_id' => $invoice->customer,
                    'last_payment_error' => $invoice->last_payment_error?.message ?? null,
                ];

            case 'customer.subscription.created':
                $subscription = \Stripe\Subscription::retrieve($data->id);
                return [
                    'type' => 'customer.subscription.created',
                    'subscription_id' => $subscription->id,
                    'customer_id' => $subscription->customer,
                    'status' => $subscription->status,
                    'plan_id' => $subscription->plan->id,
                ];

            case 'customer.subscription.updated':
                $subscription = \Stripe\Subscription::retrieve($data->id);
                return [
                    'type' => 'customer.subscription.updated',
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'plan_id' => $subscription->plan->id,
                ];

            case 'customer.subscription.deleted':
                $subscription = \Stripe\Subscription::retrieve($data->id);
                return [
                    'type' => 'customer.subscription.deleted',
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                ];

            default:
                return [
                    'type' => $type,
                    'data' => $data,
                ];
        }
    }
}