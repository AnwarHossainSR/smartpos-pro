<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'type', 'account_number', 'normal_side',
        'opening_balance', 'balance', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
