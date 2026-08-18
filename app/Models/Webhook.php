<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'url', 'events', 'secret', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function acceptsEvent(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }
}
