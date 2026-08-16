<?php

namespace App\Models;

use App\Domain\Enums\LoyaltyTransactionType;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;

class LoyaltyTransaction extends Model
{
    use BelongsToTenant, HasShortUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'customer_id', 'sale_id', 'user_id', 'type', 'points', 'balance_after', 'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => LoyaltyTransactionType::class,
            'points' => 'integer',
            'balance_after' => 'integer',
        ];
    }
}
