<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiningRoundDraftAddOn extends AbstractModel
{
    protected $fillable = [
        'dining_round_draft_id',
        'add_on_id',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(DiningRoundDraft::class, 'dining_round_draft_id');
    }

    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class);
    }
}
