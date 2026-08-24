<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreWebhookRequest;
use App\Http\Resources\WebhookDeliveryResource;
use App\Http\Resources\WebhookResource;
use App\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $webhooks = QueryPipeline::for(Webhook::query(), $request)
            ->sortable(['id', 'url', 'is_active', 'created_at'], default: '-created_at')
            ->paginate();

        return $this->success($webhooks);
    }

    public function store(StoreWebhookRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['secret'] = WebhookService::generateSecret();

        $webhook = Webhook::create($data)->refresh();

        return response()->json([
            'data' => new WebhookResource($webhook),
            'secret' => $data['secret'],
        ], 201);
    }

    public function show(Request $request, Webhook $webhook): JsonResponse
    {
        return $this->success(new WebhookResource($webhook));
    }

    public function update(StoreWebhookRequest $request, Webhook $webhook): JsonResponse
    {
        $webhook->update($request->validated());

        return $this->success(new WebhookResource($webhook->fresh()));
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        $webhook->delete();

        return $this->noContent();
    }

    public function deliveries(Request $request): JsonResponse
    {
        $deliveries = QueryPipeline::for(WebhookDelivery::query(), $request)
            ->search(['event'])
            ->allow(AllowedFilter::exact('webhook_id'))
            ->allow(AllowedFilter::exact('event'))
            ->sortable(['id', 'event', 'response_code', 'delivered_at', 'attempts', 'created_at'], default: '-created_at')
            ->paginate();

        return $this->success($deliveries);
    }

    public function retryDelivery(WebhookDelivery $delivery): JsonResponse
    {
        $webhook = $delivery->webhook;

        if (! $webhook || ! $webhook->is_active) {
            return $this->error('Webhook is inactive or deleted.', 422);
        }

        $newDelivery = WebhookDelivery::create([
            'tenant_id' => $delivery->tenant_id,
            'webhook_id' => $delivery->webhook_id,
            'event' => $delivery->event,
            'payload' => $delivery->payload,
            'attempts' => 0,
        ]);

        DeliverWebhookJob::dispatch($newDelivery->id, $webhook->id)
            ->onQueue('webhooks');

        return $this->created(new WebhookDeliveryResource($newDelivery));
    }
}
