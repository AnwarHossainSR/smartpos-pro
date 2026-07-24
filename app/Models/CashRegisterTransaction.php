<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CashRegisterTransaction extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'cash_register_session_id', 'user_id', 'type', 'amount', 'reason',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
