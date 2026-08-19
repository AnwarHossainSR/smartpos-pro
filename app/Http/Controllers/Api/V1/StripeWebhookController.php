<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;

class StripeWebhookController extends Controller
{
    protected $stripe;

    public function __construct(StripeService $stripe)
    {
        $this->stripe = $stripe;
    }

    public function handle(Request $request)
    {
        $event = Event::retrieve($request->header('Stripe-Event-Id'));

        $result = $this->stripe->constructFromWebhookEvent($event);

        // Handle the event - for now just log it
        // In production, you would dispatch events to update sales, customers, etc.
        Log::info('Stripe webhook received', ['event' => $result['type'], 'sale_id' => $result['sale_id']]);

        return response()->json(['status' => 'received']);
    }
}
