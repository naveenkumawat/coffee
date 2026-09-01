<?php

namespace App\Models;

use App\Enums\CafeClosureType;
use Database\Factories\CafeClosureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class CafeClosure extends AbstractModel
{
    /** @use HasFactory<CafeClosureFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'title',
        'type',
        'starts_at',
        'ends_at',
        'customer_message',
        'internal_note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CafeClosureType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
