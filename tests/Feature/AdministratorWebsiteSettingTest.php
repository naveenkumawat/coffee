<?php

namespace Tests\Feature;

use App\Enums\WebsiteSettingKey;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministratorWebsiteSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_and_update_website_settings(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.website-settings.edit'))
            ->assertOk()
            ->assertSee('Website Settings')
            ->assertSee('Hero title')
            ->assertSee('Payment display');

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), [
                WebsiteSettingKey::HeroTitle->value => 'Morning roast',
                WebsiteSettingKey::BusinessName->value => 'OMBE Cafe',
                WebsiteSettingKey::PagesAbout->value => '<b>Welcome</b> to our cafe',
                WebsiteSettingKey::PaymentUpiId->value => 'ombe@upi',
            ])
            ->assertRedirect(route('administrator.website-settings.edit'));

        $this->assertDatabaseHas('website_settings', [
            'key' => WebsiteSettingKey::HeroTitle->value,
            'value' => 'Morning roast',
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => WebsiteSettingKey::BusinessName->value,
            'value' => 'OMBE Cafe',
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => WebsiteSettingKey::PagesAbout->value,
            'value' => 'Welcome to our cafe',
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => WebsiteSettingKey::PaymentUpiId->value,
            'value' => 'ombe@upi',
        ]);
    }

    public function test_barista_cannot_manage_website_settings(): void
    {
        $barista = User::factory()->barista()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.website-settings.edit'))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->put(route('administrator.website-settings.update'), [
                WebsiteSettingKey::HeroTitle->value => 'Nope',
            ])
            ->assertForbidden();

        $this->assertTrue(
            WebsiteSetting::query()
                ->where('key', WebsiteSettingKey::HeroTitle->value)
                ->whereNull('value')
                ->exists()
        );
    }
}
