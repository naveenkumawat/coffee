<?php

namespace App\Models;

use App\Enums\PromotionDiscountType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPromotion extends AbstractModel
{
    public $timestamps = true;

    protected $fillable = [
        'order_id',
        'promotion_id',
        'name_snapshot',
        'code_snapshot',
        'discount_type_snapshot',
        'discount_value_snapshot',
        'discount_amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'discount_type_snapshot' => PromotionDiscountType::class,
            'discount_value_snapshot' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
