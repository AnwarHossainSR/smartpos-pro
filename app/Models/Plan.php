<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['name', 'slug', 'price', 'interval', 'limits', 'features', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
