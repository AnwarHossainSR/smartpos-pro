<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'is_super_admin' => (bool) $this->is_super_admin,
            'tenant_id' => $this->tenant_id,
            'business_id' => $this->business_id,
            'branch_id' => $this->branch_id,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('slug')),
            'permissions' => $this->when(
                $this->relationLoaded('roles'),
                fn () => $this->permissionSlugs()
            ),
            'created_at' => $this->created_at,
        ];
    }
}
