<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalisationProfile extends AbstractModel
{
    protected $fillable = [
        'customer_id',
        'visitor_key',
        'profile_version',
        'event_sample_count',
        'order_sample_count',
        'has_sufficient_evidence',
        'last_activity_at',
        'calculated_at',
        'category_affinities',
        'product_affinities',
        'flavour_affinities',
        'preferred_variants',
        'addon_preferences',
        'recent_product_ids',
        'recent_category_ids',
        'purchase_frequency',
        'repeat_purchase_product_ids',
        'spend_band',
        'time_of_day_preferences',
        'signals_meta',
    ];

    protected function casts(): array
    {
        return [
            'profile_version' => 'integer',
            'event_sample_count' => 'integer',
            'order_sample_count' => 'integer',
            'has_sufficient_evidence' => 'boolean',
            'last_activity_at' => 'datetime',
            'calculated_at' => 'datetime',
            'category_affinities' => 'array',
            'product_affinities' => 'array',
            'flavour_affinities' => 'array',
            'preferred_variants' => 'array',
            'addon_preferences' => 'array',
            'recent_product_ids' => 'array',
            'recent_category_ids' => 'array',
            'purchase_frequency' => 'array',
            'repeat_purchase_product_ids' => 'array',
            'spend_band' => 'array',
            'time_of_day_preferences' => 'array',
            'signals_meta' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function isCustomerOwned(): bool
    {
        return $this->customer_id !== null;
    }

    public function isVisitorOwned(): bool
    {
        return $this->visitor_key !== null && $this->customer_id === null;
    }
}
