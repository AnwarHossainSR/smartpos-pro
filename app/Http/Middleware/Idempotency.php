<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes unsafe writes safe to retry. When a client sends an `Idempotency-Key`
 * header, the first response is persisted and replayed for any subsequent
 * request carrying the same key **on the same method + path** — so a retried
 * POS checkout never double-charges or double-decrements stock, and a key
 * accidentally reused on a different endpoint can't replay a stranger's
 * cached response. A key replayed with a different request body (same
 * method + path) is rejected outright rather than silently served stale.
 *
 * The key row doubles as a mutex: it is written *before* the handler runs, so a
 * retry that arrives while the original is still in flight — the usual case,
 * since clients retry on their own timeout — is rejected instead of executing
 * the operation a second time.
 */
class Idempotency
{
    /**
     * How long a claim with no recorded outcome blocks retries. Long enough to
     * cover a slow checkout, short enough that a worker killed mid-request
     * (which leaves the claim behind) doesn't lock the key out for long.
     */
    protected const CLAIM_TTL_SECONDS = 60;

    public function __construct(protected TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key || ! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $identity = [
            'tenant_id' => $this->context->id(),
            'key' => $key,
            'method' => $request->method(),
            'path' => $request->path(),
        ];

        $claim = $this->claim($identity, hash('sha256', $request->getContent()));

        // Replay of a finished request, or a rejection (conflicting body / still running).
        if ($claim instanceof Response) {
            return $claim;
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            // A claim must never outlive a failed attempt, or the client's retry would
            // be rejected as "in progress" for the whole TTL.
            $claim->delete();

            throw $e;
        }

        if ($response->getStatusCode() >= 500) {
            // Services wrap their writes in a transaction, so a 5xx left nothing durable
            // behind. Release the key rather than pinning the client to a failed attempt.
            $claim->delete();

            return $response;
        }

        $claim->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => json_decode($response->getContent(), true),
        ]);

        return $response;
    }

    /**
     * Take exclusive ownership of the key for this attempt, or return the response the
     * caller should receive instead of running the handler.
     *
     * @param  array{tenant_id: int|null, key: string, method: string, path: string}  $identity
     */
    protected function claim(array $identity, string $payloadHash): IdempotencyKey|Response
    {
        $existing = $this->find($identity);

        if (! $existing) {
            try {
                return IdempotencyKey::create($identity + ['payload_hash' => $payloadHash]);
            } catch (QueryException $e) {
                // Lost the insert race to a concurrent request carrying the same key;
                // the unique index held, so fall through and treat theirs as the claim.
                $existing = $this->find($identity);

                if (! $existing) {
                    throw $e;
                }
            }
        }

        if ($existing->payload_hash !== null && $existing->payload_hash !== $payloadHash) {
            return response()->json([
                'message' => 'This idempotency key was already used with a different request body.',
            ], 409);
        }

        if ($existing->response_status !== null) {
            return response()->json($existing->response_body, $existing->response_status)
                ->header('Idempotent-Replayed', 'true');
        }

        // No recorded outcome: a request with this key is still running. Reclaim it only
        // once the claim is provably abandoned, and only via a conditional update so two
        // racing retries can't both decide they are the new owner.
        $reclaimed = IdempotencyKey::withoutGlobalScopes()
            ->whereKey($existing->getKey())
            ->whereNull('response_status')
            ->where('updated_at', '<', now()->subSeconds(self::CLAIM_TTL_SECONDS))
            ->update(['updated_at' => now()]);

        if ($reclaimed === 1) {
            return $existing->refresh();
        }

        return response()->json([
            'message' => 'A request with this idempotency key is already in progress. Retry shortly.',
        ], 409)->header('Retry-After', (string) self::CLAIM_TTL_SECONDS);
    }

    /** @param  array{tenant_id: int|null, key: string, method: string, path: string}  $identity */
    protected function find(array $identity): ?IdempotencyKey
    {
        return IdempotencyKey::withoutGlobalScopes()->where($identity)->first();
    }
}
