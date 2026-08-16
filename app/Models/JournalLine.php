<?php

namespace App\Models;

use App\Domain\Enums\JournalLineType;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'journal_entry_id', 'account_id', 'type', 'amount', 'debit', 'credit', 'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => JournalLineType::class,
            'amount' => 'decimal:2',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
