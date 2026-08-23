<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'message' => $this->message,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'is_sla_breached' => $this->is_sla_breached,
            'sla_due_at' => $this->sla_due_at,
            'last_reply_at' => $this->last_reply_at,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ]),
            'replies_count' => $this->whenCounted('replies'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
