<?php

namespace App\Exceptions;

use App\Domain\Enums\ApprovalAction;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a till action exceeds what the caller may do unaided and no valid
 * approval accompanied the request. Renders a machine-readable `403` so the
 * terminal knows exactly which challenge to raise and with what numbers, instead
 * of showing the cashier a generic "forbidden".
 *
 * `ShouldntReport` because this is expected control flow — a challenge, not a
 * fault — and it must not fill the error log every time a cashier is prompted.
 */
class ApprovalRequiredException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly ApprovalAction $action,
        public readonly float $requestedValue = 0.0,
        public readonly ?float $limitValue = null,
        string $message = '',
    ) {
        parent::__construct($message ?: "{$action->label()} requires manager approval.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'approval_required',
            'error' => [
                'action' => $this->action->value,
                'requested_value' => round($this->requestedValue, 2),
                'limit_value' => $this->limitValue === null ? null : round($this->limitValue, 2),
            ],
        ], 403);
    }
}
