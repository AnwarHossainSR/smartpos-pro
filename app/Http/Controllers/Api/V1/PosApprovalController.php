<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\ApprovalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StoreApprovalRequest;
use App\Models\PosApproval;
use App\Services\Pos\ApprovalService;
use Illuminate\Http\JsonResponse;

/**
 * The supervisor challenge. A blocked cashier posts the action, the numbers the
 * checkout endpoint quoted back at them, and a manager's PIN; what comes back is
 * a single-use token to retry the original request with.
 */
class PosApprovalController extends Controller
{
    public function __construct(protected ApprovalService $approvals) {}

    public function store(StoreApprovalRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->approvals->grant(
            $request->user(),
            ApprovalAction::from($data['action']),
            $data['pin'],
            (float) ($data['requested_value'] ?? 0),
            isset($data['limit_value']) ? (float) $data['limit_value'] : null,
            $data['sale_id'] ?? null,
            $data['reason'] ?? null,
        );

        /** @var PosApproval $approval */
        $approval = $result['approval'];
        $approval->load('approver:id,name');

        return response()->json([
            'data' => [
                'id' => $approval->id,
                // Handed over once. It is stored only as a SHA-256 digest, so this is
                // the sole moment the plaintext exists outside the terminal.
                'token' => $result['token'],
                'action' => $approval->action->value,
                'approved_by' => ['id' => $approval->approver_id, 'name' => $approval->approver?->name],
                'requested_value' => (float) $approval->requested_value,
                'limit_value' => $approval->limit_value === null ? null : (float) $approval->limit_value,
                'expires_at' => $approval->expires_at,
            ],
        ], 201);
    }
}
