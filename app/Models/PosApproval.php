<?php

namespace App\Models;

use App\Domain\Enums\ApprovalAction;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One granted supervisor approval. Single-use by construction: `consumed_at` is
 * stamped under a row lock the moment the sale that carries it is written, so the
 * same token can never authorise two documents.
 */
class PosApproval extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'business_id', 'action', 'approver_id', 'requested_by_id',
        'sale_id', 'requested_value', 'limit_value', 'reason', 'token_hash',
        'expires_at', 'consumed_at',
    ];

    /** The token itself is a bearer credential — it never leaves the grant response. */
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'action' => ApprovalAction::class,
            'requested_value' => 'float',
            'limit_value' => 'float',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
