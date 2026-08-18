<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'webhook_id', 'event', 'payload', 'response_code',
        'response_body', 'delivered_at', 'attempts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
            'attempts' => 'integer',
            'response_code' => 'integer',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('response_code', '>=', 200)
            ->where('response_code', '<', 300);
    }

    public function scopeFailed($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('response_code')
                ->orWhere('response_code', '<', 200)
                ->orWhere('response_code', '>=', 300);
        });
    }
}
