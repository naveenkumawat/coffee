<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemAddOn extends AbstractModel
{
    protected $fillable = [
        'order_item_id',
        'add_on_id',
        'name',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class)->withTrashed();
    }
}
