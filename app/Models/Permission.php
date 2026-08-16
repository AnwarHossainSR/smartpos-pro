<?php

namespace App\Models;

use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasShortUuids;

    protected $fillable = ['name', 'slug', 'module', 'action'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
