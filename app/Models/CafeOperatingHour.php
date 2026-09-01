<?php

namespace App\Models;

use Database\Factories\CafeOperatingHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CafeOperatingHour extends AbstractModel
{
    /** @use HasFactory<CafeOperatingHourFactory> */
    use HasFactory;

    protected $fillable = [
        'weekday',
        'opens_at',
        'closes_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function opensAtHm(): string
    {
        return substr((string) $this->opens_at, 0, 5);
    }

    public function closesAtHm(): string
    {
        return substr((string) $this->closes_at, 0, 5);
    }
}
