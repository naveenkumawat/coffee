<?php

namespace Tests\Feature;

use App\Enums\BrandDisplayMode;
use App\Enums\CmsPageKey;
use App\Enums\UserRole;
use App\Enums\WebsiteSettingKey;
use App\Events\Realtime\PublicCacheInvalidated;
use App\Models\CmsPage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Product\ProductServiceInterface;
use App\Services\PublicCache\PublicCacheVersionService;
use App\Services\PublicCache\PublicCacheVersionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicCacheVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_endpoint_returns_current_public_cache_version(): void
    {
        $version = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        $this->getJson(route('api.v1.app-bootstrap.show'))
            ->assertOk()
            ->assertJsonPath('data.cache_version', $version)
            ->assertJsonPath('data.catalog_version', $version)
            ->assertJsonPath('data.content_version', $version)
            ->assertJsonPath('data.media_version', $version);
    }

    public function test_public_cms_update_invalidates_public_cache_version(): void
    {
        $manager = User::factory()->manager()->create();
        $about = CmsPage::query()->where('key', CmsPageKey::About->value)->firstOrFail();
        $before = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.cms-pages.update', $about), [
                'title' => 'Our story',
                'body' => '<p>Updated about</p>',
                'is_published' => '1',
            ])
            ->assertRedirect();

        $after = app(PublicCacheVersionServiceInterface::class)->currentVersion();
        $this->assertNotSame($before, $after);
        $this->getJson(route('api.v1.app-bootstrap.show'))
            ->assertOk()
            ->assertJsonPath('data.cache_version', $after);
    }

    public function test_staff_password_change_does_not_invalidate_public_cache_version(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create([
            'password' => Hash::make('oldsecret'),
        ]);
        $before = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.users.update', $customer), [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'role' => UserRole::Customer->value,
                'is_active' => '1',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])
            ->assertRedirect();

        $this->assertSame($before, app(PublicCacheVersionServiceInterface::class)->currentVersion());
    }

    public function test_referral_settings_save_does_not_invalidate_public_cache_version(): void
    {
        $manager = User::factory()->manager()->create();
        $before = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), [
                'section' => 'award',
                WebsiteSettingKey::ReferralEnabled->value => '0',
            ])
            ->assertRedirect();

        $this->assertSame($before, app(PublicCacheVersionServiceInterface::class)->currentVersion());
    }

    public function test_branding_settings_save_invalidates_public_cache_version(): void
    {
        $manager = User::factory()->manager()->create();
        $before = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), [
                'section' => 'branding',
                WebsiteSettingKey::BusinessName->value => 'Sip The Soul',
                WebsiteSettingKey::BrandDisplayMode->value => BrandDisplayMode::LogoName->value,
            ])
            ->assertRedirect();

        $this->assertNotSame($before, app(PublicCacheVersionServiceInterface::class)->currentVersion());
    }

    public function test_dining_settings_save_invalidates_public_cache_version_on_and_off(): void
    {
        $manager = User::factory()->manager()->create();

        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            ['value' => '1'],
        );

        $beforeOff = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), [
                'section' => 'dining',
                WebsiteSettingKey::FulfilmentDineInEnabled->value => '0',
            ])
            ->assertRedirect(route('administrator.website-settings.edit', ['section' => 'dining']));

        $afterOff = app(PublicCacheVersionServiceInterface::class)->currentVersion();
        $this->assertNotSame($beforeOff, $afterOff);
        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.fulfilment.dining_enabled', false)
            ->assertJsonPath('data.fulfilment.dine_in_enabled', false);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), [
                'section' => 'dining',
                WebsiteSettingKey::FulfilmentDineInEnabled->value => '1',
            ])
            ->assertRedirect();

        $afterOn = app(PublicCacheVersionServiceInterface::class)->currentVersion();
        $this->assertNotSame($afterOff, $afterOn);
        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.fulfilment.dining_enabled', true);
    }

    public function test_admin_refresh_customer_cache_increments_version_and_broadcasts_cache_version_only(): void
    {
        Event::fake([PublicCacheInvalidated::class]);

        $manager = User::factory()->manager()->create();
        $before = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.website-settings.cache.refresh-customer'))
            ->assertRedirect(route('administrator.cache-management.index'));

        $after = app(PublicCacheVersionServiceInterface::class)->currentVersion();
        $this->assertNotSame($before, $after);

        Event::assertDispatched(PublicCacheInvalidated::class, function (PublicCacheInvalidated $event) use ($after): bool {
            $payload = $event->broadcastWith();

            return $event->broadcastAs() === 'public.cache.invalidated'
                && $event->broadcastOn()[0]->name === 'public.cache'
                && $payload === ['cache_version' => $after]
                && array_keys($payload) === ['cache_version'];
        });
    }

    public function test_clear_server_cache_does_not_change_public_cache_version(): void
    {
        $manager = User::factory()->manager()->create();
        Cache::forever('campaigns.active.popup.v1', ['demo' => true]);
        $before = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        $this->actingAs($manager, 'admin')
            ->post(route('administrator.website-settings.cache.clear-server'))
            ->assertRedirect(route('administrator.cache-management.index'));

        $this->assertSame($before, app(PublicCacheVersionServiceInterface::class)->currentVersion());
        $this->assertFalse(Cache::has('campaigns.active.popup.v1'));
        $this->assertTrue(Cache::has(PublicCacheVersionService::VERSION_KEY));
    }

    public function test_unauthorized_users_cannot_invalidate_public_cache(): void
    {
        $waiter = User::factory()->waiter()->create();

        $this->post(route('administrator.website-settings.cache.refresh-customer'))
            ->assertRedirect();

        $this->actingAs($waiter, 'admin')
            ->post(route('administrator.website-settings.cache.refresh-customer'))
            ->assertForbidden();

        $this->actingAs($waiter, 'admin')
            ->post(route('administrator.website-settings.cache.clear-all'))
            ->assertForbidden();
    }

    public function test_product_mutation_invalidates_public_cache_version(): void
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Cached Latte',
            'is_active' => true,
        ]);
        $before = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        app(ProductServiceInterface::class)->delete($product);

        $this->assertNotSame($before, app(PublicCacheVersionServiceInterface::class)->currentVersion());
    }

    public function test_cache_management_page_shows_actions(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.cache-management.index'))
            ->assertOk()
            ->assertSee('Cache Management')
            ->assertSee('Clear Server Cache')
            ->assertSee('Refresh Customer Cache')
            ->assertSee('Clear All Caches')
            ->assertSee('does not reach browsers directly');

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.website-settings.edit', ['section' => 'advanced']))
            ->assertRedirect(route('administrator.website-settings.edit', ['section' => 'award']));
    }

    public function test_hero_save_invalidates_public_cache_version(): void
    {
        $manager = User::factory()->manager()->create();
        $before = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.hero.update'), [
                WebsiteSettingKey::HeroTitle->value => 'New roast',
            ])
            ->assertRedirect(route('administrator.hero.edit'));

        $this->assertNotSame($before, app(PublicCacheVersionServiceInterface::class)->currentVersion());
    }

    public function test_cafe_schedule_hours_save_invalidates_public_cache_version(): void
    {
        $manager = User::factory()->manager()->create();
        $before = app(PublicCacheVersionServiceInterface::class)->currentVersion();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.cafe-schedule.hours.update'), [
                'days' => collect(range(0, 6))->mapWithKeys(fn (int $day): array => [
                    $day => ['enabled' => '1', 'opens_at' => '08:00', 'closes_at' => '22:00'],
                ])->all(),
            ])
            ->assertRedirect(route('administrator.cafe-schedule.index'));

        $this->assertNotSame($before, app(PublicCacheVersionServiceInterface::class)->currentVersion());

        $hours = $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->json('data.business.opening_hours');

        $this->assertIsString($hours);
        $this->assertStringContainsString('08:00', $hours);
    }
}
