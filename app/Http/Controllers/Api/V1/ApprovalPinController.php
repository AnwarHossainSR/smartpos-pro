<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetApprovalPinRequest;
use App\Models\User;
use App\Services\Pos\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Managing the supervisor PIN. A user sets and clears their own (re-authenticating
 * with their password first); an admin can only ever *revoke* someone else's —
 * never read or set it, so a PIN is always something exactly one person knows.
 */
class ApprovalPinController extends Controller
{
    public function __construct(protected ApprovalService $approvals) {}

    /** Set or replace the caller's own PIN. */
    public function update(SetApprovalPinRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->assertPassword($user, (string) $request->input('current_password'));

        $this->approvals->setPin($user, (string) $request->input('pin'));
        $user->loadMissing('roles.permissions');

        return response()->json(['data' => [
            'has_approval_pin' => true,
            'can_approve' => $user->hasPermission('pos.approve'),
        ]]);
    }

    /** Remove the caller's own PIN. */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate(['current_password' => ['required', 'string']]);
        $this->assertPassword($user, (string) $request->input('current_password'));

        $this->approvals->clearPin($user, $user);

        return response()->json(['data' => ['has_approval_pin' => false]]);
    }

    /**
     * Revoke another team member's PIN — for when a manager leaves or the PIN is
     * suspected shared. Requires `users.update`; the target must be in the caller's
     * tenant, which the global tenant scope already guarantees on the binding.
     */
    public function clearForUser(Request $request, User $user): JsonResponse
    {
        $this->approvals->clearPin($user, $request->user());

        return response()->json(['data' => ['has_approval_pin' => false]]);
    }

    protected function assertPassword(User $user, #[\SensitiveParameter] string $password): void
    {
        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }
    }
}
