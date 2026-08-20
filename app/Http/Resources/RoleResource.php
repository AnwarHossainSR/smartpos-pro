<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The wire contract predates spatie and the frontend still speaks it: `name` is the
 * human label and `slug` the machine key. Internally those are `title` and `name`
 * respectively (spatie keys everything off `name`), so the mapping is flipped here
 * rather than anywhere the client can see.
 */
class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->title,
            'slug' => $this->name,
            'description' => $this->description,
            'is_system' => (bool) $this->is_system,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
