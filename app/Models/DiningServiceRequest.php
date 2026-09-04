<?php

namespace App\Models;

use App\Enums\DiningServiceRequestStatus;
use App\Enums\DiningServiceRequestType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiningServiceRequest extends AbstractModel
{
    protected $fillable = [
        'dining_session_id',
        'table_id',
        'customer_id',
        'type',
        'status',
        'preferred_waiter_user_id',
        'claimed_by_user_id',
        'completed_by_user_id',
        'completion_reason',
        'acknowledged_at',
        'escalated_at',
        'completed_at',
        'cancelled_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DiningServiceRequestType::class,
            'status' => DiningServiceRequestStatus::class,
            'acknowledged_at' => 'datetime',
            'escalated_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function diningSession(): BelongsTo
    {
        return $this->belongsTo(DiningSession::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(CafeTable::class, 'table_id')->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function preferredWaiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_waiter_user_id')->withTrashed();
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id')->withTrashed();
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id')->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === DiningServiceRequestStatus::Pending;
    }

    public function isOpen(): bool
    {
        return $this->status?->isOpen() ?? false;
    }
}
