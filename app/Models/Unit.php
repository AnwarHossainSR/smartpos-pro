<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use BelongsToTenant, HasShortUuids;

    protected $fillable = ['tenant_id', 'name', 'short_name', 'base_unit_id', 'operator_value'];

    protected function casts(): array
    {
        return ['operator_value' => 'decimal:4'];
    }
}
