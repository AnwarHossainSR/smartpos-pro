<?php

namespace App\Models;

use App\Domain\Enums\ExpenseStatus;
use App\Domain\Enums\PaymentMethod;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use Auditable, BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'branch_id', 'expense_category_id', 'user_id', 'reference',
        'amount', 'payment_method', 'status', 'note', 'spent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExpenseStatus::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'spent_at' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
