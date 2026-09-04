<?php

namespace App\Models;

use App\Enums\AttributionFunnelStage;
use App\Enums\AttributionMode;
use App\Enums\AttributionSourceType;
use Database\Factories\CommerceAttributionEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceAttributionEvent extends AbstractModel
{
    /** @use HasFactory<CommerceAttributionEventFactory> */
    use HasFactory;

    protected $fillable = [
        'source_type',
        'source_id',
        'request_id',
        'product_id',
        'product_variant_id',
        'customer_id',
        'visitor_key',
        'strategy',
        'reason',
        'placement',
        'context',
        'attribution_mode',
        'stage',
        'order_id',
        'order_item_id',
        'units',
        'revenue_amount',
        'idempotency_key',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => AttributionSourceType::class,
            'attribution_mode' => AttributionMode::class,
            'stage' => AttributionFunnelStage::class,
            'units' => 'integer',
            'revenue_amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
