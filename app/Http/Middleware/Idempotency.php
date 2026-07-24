<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes unsafe writes safe to retry. When a client sends an `Idempotency-Key`
 * header, the first response is persisted and replayed for any subsequent
 * request carrying the same key — so a retried POS checkout never double-charges
 * or double-decrements stock.
 */
class Idempotency
{
    public function __construct(protected TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key || ! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $existing = IdempotencyKey::withoutGlobalScopes()
            ->where('tenant_id', $this->context->id())
            ->where('key', $key)
            ->first();

        if ($existing && $existing->response_status !== null) {
            return response()->json($existing->response_body, $existing->response_status)
                ->header('Idempotent-Replayed', 'true');
        }

        $record = $existing ?? IdempotencyKey::create([
            'tenant_id' => $this->context->id(),
            'key' => $key,
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            $record->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => json_decode($response->getContent(), true),
            ]);
        }

        return $response;
    }
}
