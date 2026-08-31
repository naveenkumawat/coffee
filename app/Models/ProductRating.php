<?php

namespace App\Models;

use Database\Factories\ProductRatingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductRating extends AbstractModel
{
    /** @use HasFactory<ProductRatingFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'customer_id',
        'qualifying_order_id',
        'rating',
        'review',
        'is_public',
        'moderated_at',
        'moderated_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_public' => 'boolean',
            'moderated_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function qualifyingOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'qualifying_order_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * Safe public display name, e.g. "Naveen K."
     */
    public function publicCustomerName(): string
    {
        $name = trim((string) ($this->customer?->name ?? ''));

        if ($name === '') {
            return 'Customer';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = $parts[0] ?? 'Customer';

        if (count($parts) < 2) {
            return $first;
        }

        $lastInitial = mb_strtoupper(mb_substr($parts[array_key_last($parts)], 0, 1));

        return $lastInitial !== '' ? "{$first} {$lastInitial}." : $first;
    }
}
