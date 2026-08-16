<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use BelongsToTenant, HasShortUuids;

    protected $fillable = ['tenant_id', 'name'];
}
