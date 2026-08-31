<?php

namespace Database\Seeders;

use App\Enums\SocialIconKey;
use App\Models\SocialLink;
use Illuminate\Database\Seeder;

/**
 * Structural bootstrap platforms only — no fake URLs.
 *
 * Safe for all environments. Activate and set URLs in Administrator (or leave
 * WhatsApp active with blank URL to derive from Website Settings WhatsApp).
 */
class SocialLinkSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            [
                'platform_key' => 'facebook',
                'label' => 'Facebook',
                'icon_key' => SocialIconKey::Facebook,
                'sort_order' => 10,
                'is_active' => false,
            ],
            [
                'platform_key' => SocialLink::PLATFORM_WHATSAPP,
                'label' => 'WhatsApp',
                'icon_key' => SocialIconKey::Whatsapp,
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'platform_key' => 'instagram',
                'label' => 'Instagram',
                'icon_key' => SocialIconKey::Instagram,
                'sort_order' => 30,
                'is_active' => false,
            ],
        ];

        foreach ($platforms as $platform) {
            SocialLink::query()->updateOrCreate(
                ['platform_key' => $platform['platform_key']],
                [
                    'label' => $platform['label'],
                    'url' => null,
                    'icon_key' => $platform['icon_key']->value,
                    'sort_order' => $platform['sort_order'],
                    'is_active' => $platform['is_active'],
                ],
            );
        }
    }
}
