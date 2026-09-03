<?php

namespace App\Models;

use App\Enums\OperationalNotificationPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class OperationalNotification extends AbstractModel
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'type',
        'category',
        'priority',
        'title',
        'message',
        'action_required',
        'action_code',
        'action_url',
        'subject_type',
        'subject_id',
        'actor_type',
        'actor_id',
        'resolved_at',
        'resolved_by_type',
        'resolved_by_id',
        'resolution_action',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'priority' => OperationalNotificationPriority::class,
            'action_required' => 'boolean',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booting(): void
    {
        static::creating(function (OperationalNotification $notification): void {
            if (blank($notification->uuid)) {
                $notification->uuid = (string) Str::uuid();
            }
        });
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(OperationalNotificationRecipient::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function resolvedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
