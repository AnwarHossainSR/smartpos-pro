<?php

namespace App\Services\Hr;

use App\Domain\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The employee time clock. A user has at most one *open* shift at a time; that
 * invariant is held here — inside the clock-in transaction, on a locked read —
 * rather than by a partial unique index, because MySQL has no partial indexes
 * and SQLite (which the test suite runs on) gives them different semantics, so
 * no single piece of DDL expresses the rule on both engines.
 */
class ShiftService
{
    /** A day past this many minutes counts as overtime. */
    public const OVERTIME_MINUTES_PER_DAY = 8 * 60;

    /** A week past this many minutes counts as overtime. */
    public const OVERTIME_MINUTES_PER_WEEK = 40 * 60;

    /** The shift a user is currently clocked into, if any. */
    public function currentShift(User $user): ?Shift
    {
        return Shift::query()
            ->where('user_id', $user->id)
            ->open()
            ->with('branch')
            ->latest('clock_in')
            ->first();
    }

    /**
     * @throws ValidationException when the user is already clocked in.
     */
    public function clockIn(User $user, ?string $branchId = null, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($user, $branchId, $note) {
            $open = Shift::query()
                ->where('user_id', $user->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($open) {
                throw ValidationException::withMessages([
                    'user_id' => ['You are already clocked in. Clock out before starting a new shift.'],
                ]);
            }

            return Shift::create([
                'user_id' => $user->id,
                'branch_id' => $branchId ?? $user->branch_id,
                'clock_in' => Carbon::now(),
                'note' => $note,
                'status' => ShiftStatus::Open,
            ]);
        });
    }

    /**
     * Closes the user's open shift and snapshots the minutes worked.
     *
     * @throws ValidationException when the user has no open shift.
     */
    public function clockOut(User $user, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($user, $note) {
            $shift = Shift::query()
                ->where('user_id', $user->id)
                ->open()
                ->lockForUpdate()
                ->latest('clock_in')
                ->first();

            if (! $shift) {
                throw ValidationException::withMessages([
                    'user_id' => ['You are not clocked in.'],
                ]);
            }

            $clockOut = Carbon::now();

            $shift->update([
                'clock_out' => $clockOut,
                'worked_minutes' => max(0, (int) $shift->clock_in->diffInMinutes($clockOut)),
                'status' => ShiftStatus::Closed,
                'note' => $note ?? $shift->note,
            ]);

            return $shift->fresh(['branch']);
        });
    }

    /**
     * Hours per user per day (or per ISO week), with the overtime portion split out.
     *
     * Aggregated in PHP rather than SQL on purpose: week bucketing and the "count an
     * open shift up to now" rule have no portable expression across MySQL and SQLite,
     * and a reporting period holds a handful of rows per employee.
     *
     * @param  'day'|'week'  $groupBy
     * @return array<string, mixed>
     */
    public function hoursReport(Carbon $from, Carbon $to, string $groupBy = 'day', ?string $userId = null, ?string $branchId = null): array
    {
        $threshold = $groupBy === 'week' ? self::OVERTIME_MINUTES_PER_WEEK : self::OVERTIME_MINUTES_PER_DAY;

        $shifts = Shift::query()
            ->with('user:id,name')
            ->whereBetween('clock_in', [$from, $to])
            ->when($userId, fn ($q, $id) => $q->where('user_id', $id))
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->orderBy('clock_in')
            ->get();

        $buckets = [];

        foreach ($shifts as $shift) {
            $period = $groupBy === 'week'
                ? $shift->clock_in->copy()->startOfWeek()->toDateString()
                : $shift->clock_in->toDateString();

            $key = $shift->user_id.'|'.$period;

            $buckets[$key] ??= [
                'user_id' => $shift->user_id,
                'user_name' => $shift->user?->name,
                'period' => $period,
                'shifts' => 0,
                'minutes' => 0,
            ];

            $buckets[$key]['shifts']++;
            $buckets[$key]['minutes'] += $shift->minutesWorked();
        }

        $rows = array_values(array_map(function (array $bucket) use ($threshold) {
            $overtime = max(0, $bucket['minutes'] - $threshold);

            return $bucket + [
                'hours' => round($bucket['minutes'] / 60, 2),
                'overtime_minutes' => $overtime,
                'overtime_hours' => round($overtime / 60, 2),
                'is_overtime' => $overtime > 0,
            ];
        }, $buckets));

        usort($rows, fn ($a, $b) => [$a['period'], (string) $a['user_name']] <=> [$b['period'], (string) $b['user_name']]);

        $minutes = array_sum(array_column($rows, 'minutes'));
        $overtimeMinutes = array_sum(array_column($rows, 'overtime_minutes'));

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'group_by' => $groupBy,
            'overtime_threshold_minutes' => $threshold,
            'rows' => $rows,
            'by_user' => $this->rollUpByUser($rows),
            'totals' => [
                'shifts' => array_sum(array_column($rows, 'shifts')),
                'minutes' => $minutes,
                'hours' => round($minutes / 60, 2),
                'overtime_minutes' => $overtimeMinutes,
                'overtime_hours' => round($overtimeMinutes / 60, 2),
            ],
        ];
    }

    /**
     * Per-user totals across every period in the range. Overtime is summed from the
     * per-period rows rather than recomputed against the total: a week of ordinary
     * eight-hour days is not overtime just because the week crosses a daily threshold.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function rollUpByUser(array $rows): array
    {
        $users = [];

        foreach ($rows as $row) {
            $id = $row['user_id'];

            $users[$id] ??= [
                'user_id' => $id,
                'user_name' => $row['user_name'],
                'periods' => 0,
                'shifts' => 0,
                'minutes' => 0,
                'overtime_minutes' => 0,
            ];

            $users[$id]['periods']++;
            $users[$id]['shifts'] += $row['shifts'];
            $users[$id]['minutes'] += $row['minutes'];
            $users[$id]['overtime_minutes'] += $row['overtime_minutes'];
        }

        $byUser = array_values(array_map(fn (array $u) => $u + [
            'hours' => round($u['minutes'] / 60, 2),
            'overtime_hours' => round($u['overtime_minutes'] / 60, 2),
            'is_overtime' => $u['overtime_minutes'] > 0,
        ], $users));

        usort($byUser, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);

        return $byUser;
    }
}
