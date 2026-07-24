<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = ['tenant_id', 'key', 'method', 'path', 'response_status', 'response_body'];

    protected function casts(): array
    {
        return ['response_body' => 'array'];
    }
}
