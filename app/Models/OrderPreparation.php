<?php

namespace App\Models;

use App\Enums\OrderPreparationStatus;
use App\Enums\PreparationStation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class OrderPreparation extends AbstractModel
{
    protected $fillable = [
        'order_id',
        'station',
        'status',
        'accepted_at',
        'preparing_at',
        'ready_at',
        'completed_at',
        'cancelled_at',
        'accepted_by_user_id',
        'ready_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'station' => PreparationStation::class,
            'status' => OrderPreparationStatus::class,
            'accepted_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id')->withTrashed();
    }

    public function readyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ready_by_user_id')->withTrashed();
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function items(): Collection
    {
        $this->loadMissing('order.items');

        if (! $this->order || ! $this->station instanceof PreparationStation) {
            return collect();
        }

        return $this->order->items
            ->filter(fn (OrderItem $item): bool => $item->preparation_station === $this->station)
            ->values();
    }

    public function isActive(): bool
    {
        return $this->status instanceof OrderPreparationStatus
            && ! $this->status->isTerminal();
    }

    public function canTransitionTo(OrderPreparationStatus $next): bool
    {
        if (! $this->status instanceof OrderPreparationStatus) {
            return false;
        }

        return in_array($next, $this->status->allowedTransitions(), true);
    }
}
