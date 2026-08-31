<?php

namespace App\Models;

use App\Enums\StaffNotificationChannel;
use App\Enums\StaffNotificationType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffNotificationLog extends AbstractModel
{
    protected $fillable = [
        'type',
        'channel',
        'unique_key',
        'user_id',
        'order_id',
        'ingredient_id',
        'inventory_refill_request_id',
        'status',
        'error_message',
        'sent_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => StaffNotificationType::class,
            'channel' => StaffNotificationChannel::class,
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function refillRequest(): BelongsTo
    {
        return $this->belongsTo(InventoryRefillRequest::class, 'inventory_refill_request_id');
    }
}
