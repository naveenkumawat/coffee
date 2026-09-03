<?php

namespace App\Models;

use App\Enums\BehaviourEventSource;
use App\Enums\BehaviourEventType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBehaviourEvent extends AbstractModel
{
    protected $fillable = [
        'event_type',
        'source',
        'customer_id',
        'visitor_key',
        'product_id',
        'product_category_id',
        'product_variant_id',
        'order_id',
        'page_context',
        'metadata',
        'occurred_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => BehaviourEventType::class,
            'source' => BehaviourEventSource::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
