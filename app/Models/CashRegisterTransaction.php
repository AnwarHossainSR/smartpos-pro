<?php

namespace App\Models;

use App\Domain\Enums\CashMovementType;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;

class CashRegisterTransaction extends Model
{
    use BelongsToTenant, HasShortUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'cash_register_session_id', 'user_id', 'type', 'amount', 'reason',
    ];

    protected function casts(): array
    {
        return ['type' => CashMovementType::class, 'amount' => 'decimal:2'];
    }
}
