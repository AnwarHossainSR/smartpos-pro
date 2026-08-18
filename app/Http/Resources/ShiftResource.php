<?php

namespace App\Http\Resources;

use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Shift */
class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'worked_minutes' => $this->worked_minutes,
            // Live for an open shift, identical to worked_minutes once closed — the
            // header pill seeds its ticking timer from this instead of guessing.
            'elapsed_minutes' => $this->minutesWorked(),
            'note' => $this->note,
            'status' => $this->status,
        ];
    }
}
