<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ApiResponse;

    public function indexUsers(Request $request): JsonResponse
    {
        $query = User::withoutTenancy()
            ->with('tenant:id,name')
            ->with('roles:id,name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        if (str_starts_with($sort, '-')) {
            $sort = substr($sort, 1);
            $direction = 'desc';
        }
        $allowed = ['created_at', 'name', 'email', 'status'];
        $sort = in_array($sort, $allowed) ? $sort : 'created_at';

        $users = $query->orderBy($sort, $direction)
            ->paginate($request->input('per_page', 20));

        return $this->success(UserResource::collection($users));
    }

    public function showUser(string $id): JsonResponse
    {
        $user = User::withoutTenancy()->with('tenant:id,name', 'roles:id,name')->findOrFail($id);

        return $this->success(new UserResource($user));
    }

    public function updateUserStatus(Request $request, string $id): JsonResponse
    {
        $user = User::withoutTenancy()->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,invited,suspended'],
        ]);

        $user->update(['status' => $validated['status']]);

        return $this->success(new UserResource($user->fresh()));
    }

    public function forceLogoutUser(string $id): JsonResponse
    {
        $user = User::withoutTenancy()->findOrFail($id);
        $user->tokens()->delete();

        return $this->success(null, 'User logged out from all devices.');
    }
}
