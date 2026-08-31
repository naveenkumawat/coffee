<?php

namespace App\Models;

use App\Enums\CustomerNotificationChannel;
use App\Enums\CustomerNotificationType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNotificationLog extends AbstractModel
{
    protected $fillable = [
        'type',
        'channel',
        'unique_key',
        'customer_id',
        'order_id',
        'recipient_email',
        'recipient_phone',
        'provider_message_id',
        'status',
        'error_message',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerNotificationType::class,
            'channel' => CustomerNotificationChannel::class,
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
