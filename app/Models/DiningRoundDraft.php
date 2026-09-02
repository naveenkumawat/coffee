<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningRoundDraft extends AbstractModel
{
    protected $fillable = [
        'dining_session_id',
        'customer_id',
        'product_variant_id',
        'configuration_hash',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DiningSession::class, 'dining_session_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function draftAddOns(): HasMany
    {
        return $this->hasMany(DiningRoundDraftAddOn::class);
    }
}
