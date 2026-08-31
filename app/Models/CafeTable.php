<?php

namespace App\Models;

use Database\Factories\CafeTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CafeTable extends AbstractModel
{
    /** @use HasFactory<CafeTableFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function displayLabel(): string
    {
        $code = trim((string) $this->code);
        $name = trim((string) ($this->name ?? ''));

        if ($name !== '' && strcasecmp($name, $code) !== 0) {
            return $code.' — '.$name;
        }

        return $code !== '' ? $code : 'Table';
    }

    public function snapshotLabel(): string
    {
        return $this->displayLabel();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
