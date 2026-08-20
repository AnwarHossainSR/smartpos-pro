<?php

namespace App\Http\Resources;

use App\Services\Media\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_path' => $this->logo_path,
            'logo_url' => app(MediaService::class)->url($this->logo_path),
            'logo_urls' => app(MediaService::class)->urls($this->logo_path, 'brand.logo'),
            'is_active' => (bool) $this->is_active,
            'products_count' => $this->whenCounted('products'),
            'created_at' => $this->created_at,
        ];
    }
}
