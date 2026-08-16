<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use BelongsToTenant, HasShortUuids, SoftDeletes;

    protected $fillable = ['tenant_id', 'name', 'slug', 'logo_path', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
