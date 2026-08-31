<?php

namespace App\Models;

use Database\Factories\SocialLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialLink extends AbstractModel
{
    /** @use HasFactory<SocialLinkFactory> */
    use HasFactory;

    use SoftDeletes;

    public const PLATFORM_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'platform_key',
        'label',
        'url',
        'icon_key',
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

    public function isWhatsapp(): bool
    {
        return $this->platform_key === self::PLATFORM_WHATSAPP;
    }
}
