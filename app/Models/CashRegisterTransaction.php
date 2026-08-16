<?php

namespace App\Models;

use App\Domain\Enums\CashMovementType;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CashRegisterTransaction extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'cash_register_session_id', 'user_id', 'type', 'amount', 'reason',
    ];

    protected function casts(): array
    {
        return ['type' => CashMovementType::class, 'amount' => 'decimal:2'];
    }
}
