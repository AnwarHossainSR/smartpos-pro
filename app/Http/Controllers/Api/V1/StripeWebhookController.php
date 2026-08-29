<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    use ApiResponse;

    protected $stripe;

    public function __construct(StripeService $stripe)
    {
        $this->stripe = $stripe;
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $webhookSecret = (string) config('services.stripe.webhook_secret');

        // Authenticate the request with Stripe's signature header BEFORE trusting any
        // payload data. The previous code fetched the event by its header-supplied
        // id, which let anyone who could call the Stripe API impersonate the API by
        // replaying a header. constructEvent fails closed: a missing/empty/whitelist-
        // mismatched secret returns 402 and no event is processed.
        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook rejected: malformed payload', ['error' => $e->getMessage()]);

            return response('Bad payload', 400);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook rejected: bad signature', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        $result = $this->stripe->constructFromWebhookEvent($event);

        // Log only the event type — sale/customer ids and amounts are payment-PII best
        // kept out of the application log.
        Log::info('Stripe webhook received', ['event' => $result['type']]);

        return $this->success(['status' => 'received']);
    }
}
