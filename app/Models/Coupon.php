<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'code', 'type', 'value', 'min_purchase', 'max_discount',
        'usage_limit', 'used_count', 'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return array{valid: bool, reason?: string, discount?: float} */
    public function evaluate(float $cartTotal): array
    {
        $today = Carbon::today();

        if (! $this->is_active) {
            return ['valid' => false, 'reason' => 'Coupon is inactive.'];
        }
        if ($this->starts_at && $today->lt($this->starts_at)) {
            return ['valid' => false, 'reason' => 'Coupon is not active yet.'];
        }
        if ($this->expires_at && $today->gt($this->expires_at)) {
            return ['valid' => false, 'reason' => 'Coupon has expired.'];
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return ['valid' => false, 'reason' => 'Coupon usage limit reached.'];
        }
        if ($cartTotal < (float) $this->min_purchase) {
            return ['valid' => false, 'reason' => "Minimum purchase of {$this->min_purchase} required."];
        }

        $discount = $this->type === 'percentage'
            ? round($cartTotal * ((float) $this->value / 100), 2)
            : (float) $this->value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }
        $discount = min($discount, $cartTotal);

        return ['valid' => true, 'discount' => $discount];
    }
}
