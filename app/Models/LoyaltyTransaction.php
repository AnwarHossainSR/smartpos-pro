<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class LoyaltyTransaction extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'customer_id', 'sale_id', 'user_id', 'type', 'points', 'balance_after', 'note',
    ];

    protected function casts(): array
    {
        return ['points' => 'integer', 'balance_after' => 'integer'];
    }
}
