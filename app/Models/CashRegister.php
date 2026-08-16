<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    use BelongsToTenant, HasShortUuids;

    protected $fillable = ['tenant_id', 'branch_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashRegisterSession::class);
    }

    public function openSession(): ?CashRegisterSession
    {
        return $this->sessions()->where('status', 'open')->latest('id')->first();
    }
}
