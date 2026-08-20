<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use BelongsToTenant, HasApiTokens, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'customer_group', 'tax_number',
        'tax_exempt', 'tax_exemption_reference', 'country_code', 'state', 'postcode',
        'billing_address', 'shipping_address', 'credit_limit', 'opening_balance',
        'balance', 'loyalty_points', 'birthday', 'is_active',
        'portal_token', 'portal_token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'tax_exempt' => 'boolean',
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'balance' => 'decimal:2',
            'loyalty_points' => 'integer',
            'birthday' => 'date',
            'portal_token_expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function generatePortalToken(): string
    {
        $this->portal_token = Str::random(64);
        $this->portal_token_expires_at = now()->addHours(24);
        $this->save();

        return $this->portal_token;
    }
}
