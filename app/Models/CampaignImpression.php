<?php

namespace App\Models;

use App\Enums\CampaignImpressionEvent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignImpression extends AbstractModel
{
    protected $fillable = [
        'campaign_id',
        'customer_id',
        'visitor_key',
        'session_key',
        'event_type',
        'placement',
        'request_id',
        'cta_type',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => CampaignImpressionEvent::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
