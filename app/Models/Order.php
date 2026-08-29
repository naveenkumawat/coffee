<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends AbstractModel
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'order_date',
        'daily_sequence',
        'customer_id',
        'assigned_barista_id',
        'status',
        'subtotal',
        'discount_total',
        'total_amount',
        'customer_notes',
        'placed_at',
        'payment_confirmed_at',
        'accepted_at',
        'preparing_at',
        'ready_for_pickup_at',
        'completed_at',
        'cancelled_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'order_date' => 'date',
            'daily_sequence' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'placed_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_for_pickup_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function assignedBarista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_barista_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }
}
