<?php

namespace App\Http\Resources;

use App\Domain\Enums\UserStatus;
use App\Support\Theme\ThemeCatalog;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status instanceof UserStatus
                ? $this->status->value
                : $this->status,
            'is_super_admin' => $this->is_super_admin,
            'email_verified_at' => $this->email_verified_at,
            'last_login_at' => $this->last_login_at,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->whenLoaded('tenant', fn () => $this->tenant->name),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'business_id' => $this->business_id,
            'branch_id' => $this->branch_id,
            'created_at' => $this->created_at,
            /** Mode only (light/dark/system). The colour palette is tenant-owned, served by
             *  `GET /settings/business` rather than the per-user account. */
            'theme' => $this->theme
                ? ['mode' => $this->theme['mode'] ?? ThemeCatalog::DEFAULT_MODE]
                : null,
        ];
    }
}
