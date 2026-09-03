<?php

namespace Tests\Feature;

use App\Contracts\WhatsApp\WhatsAppNotificationProviderInterface;
use App\Models\AddOn;
use App\Models\CafeTable;
use App\Models\HomeSection;
use App\Models\IngredientCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductTag;
use App\Models\SocialLink;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Product\ProductCatalogService;
use App\Services\Product\ProductCatalogServiceInterface;
use App\Support\ProductMarketingTags;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\IngredientCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class DemoSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_refuses_production_environment(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DemoSeeder refused');

        // Call the seeder directly so Laravel's production SeedCommand confirm prompt is not involved.
        $this->app->make(DemoSeeder::class)->run();
    }

    public function test_demo_seeder_refuses_non_local_non_testing_environments(): void
    {
        $this->app['env'] = 'staging';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DemoSeeder refused');

        $this->app->make(DemoSeeder::class)->run();
    }

    public function test_production_database_seeder_seeds_structural_data_without_demo_content(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(count(IngredientCategorySeeder::CATEGORIES), IngredientCategory::query()->count());
        $this->assertGreaterThanOrEqual(3, SocialLink::query()->count());
        $this->assertTrue(ProductTag::query()->where('slug', ProductMarketingTags::NEW)->exists());
        $this->assertTrue(ProductTag::query()->where('slug', ProductMarketingTags::TOP_SELLER)->exists());
        $this->assertDatabaseHas('social_links', [
            'platform_key' => 'whatsapp',
            'url' => null,
        ]);

        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, CafeTable::query()->count());
        $this->assertSame(0, HomeSection::query()->count());
        $this->assertFalse(
            WebsiteSetting::query()->where('key', 'hero_title')->whereNotNull('value')->where('value', '!=', '')->exists(),
        );
        $this->assertFalse(
            WebsiteSetting::query()->where('key', 'tax_enabled')->where('value', '1')->exists(),
        );
        $this->assertFalse(User::query()->where('email', 'like', '%@coffee.local')->exists());
        $this->assertFalse(User::query()->where('email', 'customer@coffee.local')->exists());
        $this->assertFalse(User::query()->where('email', 'admin@coffee.local')->exists());
    }

    public function test_local_database_seeder_loads_demo_data_without_outbound_side_effects(): void
    {
        Mail::fake();
        Notification::fake();
        Http::fake();

        $whatsapp = $this->mock(WhatsAppNotificationProviderInterface::class);
        $whatsapp->shouldNotReceive('send');
        $this->app->instance(WhatsAppNotificationProviderInterface::class, $whatsapp);

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::query()->where('email', 'admin@coffee.local')->exists());
        $this->assertTrue(User::query()->where('email', 'inactive.staff@coffee.local')->where('is_active', false)->exists());
        $this->assertGreaterThanOrEqual(10, User::query()->where('role', 'customer')->count());
        $this->assertGreaterThanOrEqual(10, CafeTable::query()->count());
        $this->assertTrue(CafeTable::query()->where('is_active', false)->exists());
        $this->assertGreaterThanOrEqual(25, Product::query()->count());
        $this->assertTrue(AddOn::query()->where('name', 'Extra Espresso Shot')->where('is_active', true)->exists());
        $this->assertTrue(
            Product::query()->where('name', 'Cappuccino')->whereHas('addOns', function ($query): void {
                $query->where('add_ons.name', 'Extra Espresso Shot');
            })->exists(),
        );
        $this->assertTrue(Order::query()->where('fulfilment_method', 'dine_in')->exists());
        $this->assertTrue(
            WebsiteSetting::query()->where('key', 'fulfilment_dine_in_enabled')->where('value', '1')->exists(),
        );
        $this->assertTrue(
            WebsiteSetting::query()->where('key', 'tax_enabled')->where('value', '1')->exists(),
        );
        $this->assertTrue(
            Order::query()->where('tax_enabled_snapshot', true)->exists(),
        );
        $this->assertGreaterThanOrEqual(5, HomeSection::query()->count());
        $this->assertTrue(HomeSection::query()->where('is_active', false)->exists());

        Mail::assertNothingSent();
        Http::assertNothingSent();
        // Demo dining seed may dispatch in-app notifications; Notification::fake() prevents outbound delivery.
    }

    public function test_demo_seeder_flushes_stale_public_catalogue_cache(): void
    {
        Cache::put(
            ProductCatalogService::PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY,
            [['id' => 999, 'name' => 'Stale Ghost Drink']],
            now()->addMinutes(30),
        );

        $this->assertTrue(Cache::has(ProductCatalogService::PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY));

        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(Cache::has(ProductCatalogService::PUBLIC_PRODUCTS_PAYLOAD_CACHE_KEY));

        $payload = app(ProductCatalogServiceInterface::class)->listPublicProductPayload();

        $this->assertNotEmpty($payload);
        $this->assertFalse(
            collect($payload)->contains(fn (array $product): bool => ($product['name'] ?? null) === 'Stale Ghost Drink'),
        );
        $this->assertTrue(
            collect($payload)->contains(fn (array $product): bool => ($product['is_featured'] ?? false) === true
                || ($product['is_new'] ?? false) === true
                || ($product['is_bestseller'] ?? false) === true
                || filled($product['name'] ?? null)),
        );
    }
}
