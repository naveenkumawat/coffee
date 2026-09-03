<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerVisitorIdentity extends AbstractModel
{
    protected $fillable = [
        'visitor_key',
        'customer_id',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
