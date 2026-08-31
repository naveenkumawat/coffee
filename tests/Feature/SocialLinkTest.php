<?php

namespace Tests\Feature;

use App\Enums\SocialIconKey;
use App\Enums\WebsiteSettingKey;
use App\Models\SocialLink;
use App\Models\User;
use App\Models\WebsiteSetting;
use Database\Seeders\SocialLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_structural_platforms_are_seeded_without_fake_urls(): void
    {
        $this->seed(SocialLinkSeeder::class);

        $this->assertDatabaseHas('social_links', [
            'platform_key' => 'facebook',
            'label' => 'Facebook',
            'url' => null,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('social_links', [
            'platform_key' => 'whatsapp',
            'label' => 'WhatsApp',
            'url' => null,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('social_links', [
            'platform_key' => 'instagram',
            'label' => 'Instagram',
            'url' => null,
            'is_active' => false,
        ]);
    }

    public function test_content_api_returns_ordered_active_configured_links_only(): void
    {
        SocialLink::factory()->create([
            'platform_key' => 'instagram',
            'label' => 'Instagram',
            'icon_key' => SocialIconKey::Instagram->value,
            'url' => 'https://instagram.com/the88coffees',
            'sort_order' => 30,
            'is_active' => true,
        ]);
        SocialLink::factory()->create([
            'platform_key' => 'facebook',
            'label' => 'Facebook',
            'icon_key' => SocialIconKey::Facebook->value,
            'url' => 'https://facebook.com/the88coffees',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        SocialLink::factory()->inactive()->create([
            'platform_key' => 'youtube',
            'label' => 'YouTube',
            'icon_key' => SocialIconKey::Youtube->value,
            'url' => 'https://youtube.com/@the88coffees',
            'sort_order' => 5,
        ]);
        SocialLink::factory()->withoutUrl()->create([
            'platform_key' => 'tiktok',
            'label' => 'TikTok',
            'icon_key' => SocialIconKey::Tiktok->value,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        WebsiteSetting::query()->where('key', WebsiteSettingKey::BusinessWhatsappNumber->value)->update([
            'value' => '+91 98765 43210',
        ]);

        SocialLink::factory()->withoutUrl()->create([
            'platform_key' => SocialLink::PLATFORM_WHATSAPP,
            'label' => 'WhatsApp',
            'icon_key' => SocialIconKey::Whatsapp->value,
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonCount(3, 'data.social_links')
            ->assertJsonPath('data.social_links.0.label', 'Facebook')
            ->assertJsonPath('data.social_links.0.icon_key', 'facebook')
            ->assertJsonPath('data.social_links.0.url', 'https://facebook.com/the88coffees')
            ->assertJsonPath('data.social_links.1.label', 'WhatsApp')
            ->assertJsonPath('data.social_links.1.url', 'https://wa.me/919876543210')
            ->assertJsonPath('data.social_links.2.label', 'Instagram')
            ->assertJsonMissingPath('data.social_links.3');
    }

    public function test_manager_can_manage_social_links_and_rejects_invalid_or_duplicate_keys(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.social-links.store'), [
                'platform_key' => 'Facebook!',
                'label' => 'Facebook',
                'url' => 'https://facebook.com/cafe',
                'icon_key' => SocialIconKey::Facebook->value,
                'sort_order' => 10,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('platform_key');

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.social-links.store'), [
                'platform_key' => 'facebook',
                'label' => 'Facebook',
                'url' => 'not-a-url',
                'icon_key' => SocialIconKey::Facebook->value,
                'sort_order' => 10,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('url');

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.social-links.store'), [
                'platform_key' => 'facebook',
                'label' => 'Facebook',
                'url' => 'https://facebook.com/cafe',
                'icon_key' => SocialIconKey::Facebook->value,
                'sort_order' => 10,
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('social_links', [
            'platform_key' => 'facebook',
            'label' => 'Facebook',
        ]);

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.social-links.store'), [
                'platform_key' => 'facebook',
                'label' => 'Facebook again',
                'url' => 'https://facebook.com/other',
                'icon_key' => SocialIconKey::Facebook->value,
                'sort_order' => 11,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('platform_key');

        $facebook = SocialLink::query()->where('platform_key', 'facebook')->firstOrFail();
        $instagram = SocialLink::factory()->create([
            'platform_key' => 'instagram',
            'label' => 'Instagram',
            'icon_key' => SocialIconKey::Instagram->value,
            'url' => 'https://instagram.com/cafe',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $this->actingAs($manager, 'admin')
            ->patch(route('administrator.social-links.move-up', $instagram))
            ->assertRedirect(route('administrator.social-links.index'));

        $this->assertTrue((int) $instagram->fresh()->sort_order < (int) $facebook->fresh()->sort_order);

        $this->actingAs($manager, 'admin')
            ->patch(route('administrator.social-links.toggle', $facebook))
            ->assertRedirect(route('administrator.social-links.index'));

        $this->assertFalse((bool) $facebook->fresh()->is_active);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonCount(1, 'data.social_links')
            ->assertJsonPath('data.social_links.0.label', 'Instagram');

        SocialLink::factory()->create([
            'platform_key' => 'youtube',
            'label' => 'YouTube',
            'icon_key' => SocialIconKey::Youtube->value,
            'url' => 'https://youtube.com/@cafe',
            'sort_order' => 40,
            'is_active' => true,
        ]);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonCount(2, 'data.social_links')
            ->assertJsonPath('data.social_links.1.label', 'YouTube')
            ->assertJsonPath('data.social_links.1.icon_key', 'youtube');
    }

    public function test_barista_cannot_manage_social_links(): void
    {
        $barista = User::factory()->barista()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.social-links.index'))
            ->assertForbidden();
    }

    public function test_unknown_icon_key_still_serializes_for_customer_fallback(): void
    {
        SocialLink::factory()->create([
            'platform_key' => 'threads',
            'label' => 'Threads',
            'icon_key' => 'threads',
            'url' => 'https://threads.net/@cafe',
            'sort_order' => 50,
            'is_active' => true,
        ]);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.social_links.0.icon_key', 'threads')
            ->assertJsonPath('data.social_links.0.label', 'Threads');
    }
}
