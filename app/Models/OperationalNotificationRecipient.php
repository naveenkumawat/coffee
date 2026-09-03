<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class OperationalNotificationRecipient extends AbstractModel
{
    use HasFactory;

    protected $fillable = [
        'operational_notification_id',
        'user_id',
        'role',
        'broadcast_at',
        'delivered_at',
        'first_seen_at',
        'read_at',
        'acknowledged_at',
        'action_started_at',
        'action_completed_at',
        'reminder_count',
        'last_reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'broadcast_at' => 'datetime',
            'delivered_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'read_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'action_started_at' => 'datetime',
            'action_completed_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'reminder_count' => 'integer',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(OperationalNotification::class, 'operational_notification_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Idempotent first-write for a lifecycle timestamp column.
     */
    public function markFirstTimestamp(string $column, ?Carbon $at = null): bool
    {
        if ($this->{$column} !== null) {
            return false;
        }

        $this->forceFill([$column => $at ?? now()])->save();

        return true;
    }

    public function deliveryDelaySeconds(): ?int
    {
        return $this->delayFromCreated($this->delivered_at);
    }

    public function firstSeenDelaySeconds(): ?int
    {
        return $this->delayFromCreated($this->first_seen_at);
    }

    public function acknowledgeDelaySeconds(): ?int
    {
        return $this->delayFromCreated($this->acknowledged_at);
    }

    public function actionStartDelaySeconds(): ?int
    {
        return $this->delayFromCreated($this->action_started_at);
    }

    public function actionCompletionDelaySeconds(): ?int
    {
        return $this->delayFromCreated($this->action_completed_at);
    }

    public function resolutionDelaySeconds(): ?int
    {
        $notification = $this->relationLoaded('notification')
            ? $this->notification
            : $this->notification()->first();

        return $this->delayFromCreated($notification?->resolved_at, $notification?->created_at);
    }

    protected function delayFromCreated(mixed $to, mixed $from = null): ?int
    {
        $createdAt = $from ?? (
            $this->relationLoaded('notification')
                ? $this->notification?->created_at
                : $this->notification()->value('created_at')
        );

        if ($createdAt === null || $to === null) {
            return null;
        }

        return (int) max(0, $createdAt->diffInSeconds($to, false));
    }
}
