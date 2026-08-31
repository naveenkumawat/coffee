<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeSectionProduct extends AbstractModel
{
    protected $fillable = [
        'home_section_id',
        'product_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(HomeSection::class, 'home_section_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
