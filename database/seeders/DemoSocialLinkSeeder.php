<?php

namespace Database\Seeders;

use App\Enums\SocialIconKey;
use App\Models\SocialLink;
use Illuminate\Database\Seeder;

/**
 * Demo URLs for local/testing only. Structural shells remain URL-less in production.
 */
class DemoSocialLinkSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        SocialLink::query()->updateOrCreate(
            ['platform_key' => 'facebook'],
            [
                'label' => 'Facebook',
                'url' => 'https://facebook.com/the88coffees.demo',
                'icon_key' => SocialIconKey::Facebook->value,
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        SocialLink::query()->updateOrCreate(
            ['platform_key' => SocialLink::PLATFORM_WHATSAPP],
            [
                'label' => 'WhatsApp',
                'url' => null,
                'icon_key' => SocialIconKey::Whatsapp->value,
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        SocialLink::query()->updateOrCreate(
            ['platform_key' => 'instagram'],
            [
                'label' => 'Instagram',
                'url' => 'https://instagram.com/the88coffees.demo',
                'icon_key' => SocialIconKey::Instagram->value,
                'sort_order' => 30,
                'is_active' => true,
            ],
        );

        SocialLink::query()->updateOrCreate(
            ['platform_key' => 'youtube'],
            [
                'label' => 'YouTube',
                'url' => 'https://youtube.com/@the88coffees.demo',
                'icon_key' => SocialIconKey::Youtube->value,
                'sort_order' => 40,
                'is_active' => false,
            ],
        );
    }
}
