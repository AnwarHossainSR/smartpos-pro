<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'user_id', 'subject', 'message', 'category',
        'priority', 'status', 'assigned_to', 'sla_due_at', 'last_reply_at',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'last_reply_at' => 'datetime',
        ];
    }

    public const CATEGORIES = ['general', 'billing', 'technical', 'feature_request', 'bug_report'];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const STATUSES = ['open', 'in_progress', 'waiting_on_customer', 'resolved', 'closed'];

    public const SLA_HOURS = [
        'urgent' => 4,
        'high' => 8,
        'medium' => 24,
        'low' => 72,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id');
    }

    public function visibleReplies(): HasMany
    {
        return $this->replies()->where('is_internal', false);
    }

    public function getIsSlaBreachedAttribute(): bool
    {
        if (! $this->sla_due_at || $this->status === 'resolved' || $this->status === 'closed') {
            return false;
        }

        return now()->greaterThan($this->sla_due_at);
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (SupportTicket $ticket) {
            if (! $ticket->sla_due_at && $ticket->priority) {
                $hours = self::SLA_HOURS[$ticket->priority] ?? 24;
                $ticket->sla_due_at = now()->addHours($hours);
            }
            if (! $ticket->last_reply_at) {
                $ticket->last_reply_at = now();
            }
        });
    }
}
