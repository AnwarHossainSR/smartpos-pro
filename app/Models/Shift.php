<?php

namespace App\Models;

use App\Domain\Enums\ShiftStatus;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee time-clock entry: clocked in at `clock_in`, still running while
 * `status` is open, closed with a snapshotted `worked_minutes` on clock-out.
 */
class Shift extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'user_id', 'branch_id', 'clock_in', 'clock_out', 'worked_minutes', 'note', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'worked_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @param  Builder<Shift>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', ShiftStatus::Open);
    }

    public function isOpen(): bool
    {
        return $this->status === ShiftStatus::Open;
    }

    /**
     * Minutes worked so far: the snapshot for a closed shift, or the time elapsed
     * since clock-in for one that is still running (so "today" reads live).
     */
    public function minutesWorked(): int
    {
        if ($this->worked_minutes !== null) {
            return $this->worked_minutes;
        }

        return max(0, (int) $this->clock_in->diffInMinutes(now()));
    }
}
