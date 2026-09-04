<?php

namespace Tests\Feature;

use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\UserRole;
use App\Enums\WebsiteSettingKey;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Launch\LaunchReadinessServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_exits_nonzero_when_blockers_present_and_supports_json(): void
    {
        $this->artisan('coffee:launch-readiness', ['--json' => true])
            ->expectsOutputToContain('"ok": false')
            ->assertFailed();
    }

    public function test_detects_missing_critical_business_and_payment_config(): void
    {
        config()->set('coffee.payments.upi_id', null);
        config()->set('coffee.payments.qr_image_path', null);
        config()->set('coffee.payments.instructions', null);
        config()->set('coffee.payments.whatsapp_number', null);

        $json = app(LaunchReadinessServiceInterface::class)->evaluate()->toArray();

        $codes = collect($json['blockers'])->pluck('code')->all();
        $this->assertContains('business.name', $codes);
        $this->assertContains('payment.upi', $codes);
        $this->assertContains('payment.qr', $codes);
        $this->assertContains('catalog.empty', $codes);
        $this->assertContains('launch_menu.unconfirmed', $codes);
        $this->assertContains('cms.terms', $codes);
        $this->assertContains('cms.privacy', $codes);
        $this->assertFalse($json['ok']);
    }

    public function test_detects_active_product_with_incomplete_configuration_as_blocker(): void
    {
        $this->seedMinimalBusinessConfig();

        $category = ProductCategory::factory()->create(['is_active' => true]);
        Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Broken Launch Latte',
            'is_active' => true,
            'is_available' => true,
            'preparation_station' => PreparationStation::Bar,
            'image_path' => null,
        ]);

        $json = app(LaunchReadinessServiceInterface::class)->evaluate()->toArray();
        $messages = collect($json['blockers'])->pluck('message')->implode(' ');
        $this->assertStringContainsString('Broken Launch Latte', $messages);
        $this->assertFalse($json['ok']);
    }

    public function test_production_env_flags_demo_coffee_local_users_as_blocker(): void
    {
        $this->app['env'] = 'production';

        User::factory()->create([
            'email' => 'admin@coffee.local',
            'role' => UserRole::Owner,
            'is_active' => true,
            'password' => 'password',
        ]);

        $json = app(LaunchReadinessServiceInterface::class)->evaluate()->toArray();
        $codes = collect($json['blockers'])->pluck('code')->all();
        $this->assertContains('demo.users', $codes);
    }

    public function test_delivery_fee_is_optional_deferred_not_invented(): void
    {
        $json = app(LaunchReadinessServiceInterface::class)->evaluate()->toArray();
        $optional = collect($json['optional_deferred'])->pluck('code')->all();
        $this->assertContains('delivery.fee_not_collected', $optional);

        $statuses = collect($json['areas'])->keyBy('area');
        $this->assertSame('optional_deferred', $statuses['delivery_fee']['status']);
    }

    public function test_malformed_active_variant_price_surfaces_in_catalog_findings(): void
    {
        $this->seedMinimalBusinessConfig();

        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Zero Price Drink',
            'is_active' => true,
            'preparation_station' => PreparationStation::Bar,
            'image_path' => 'https://example.test/drink.jpg',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => '0.00',
            'is_active' => true,
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
        ]);

        $json = app(LaunchReadinessServiceInterface::class)->evaluate()->toArray();
        $joined = collect($json['blockers'])->pluck('message')->implode(' | ');
        $this->assertStringContainsString('Zero Price Drink', $joined);
        $this->assertTrue(
            str_contains(strtolower($joined), 'price')
            || str_contains(strtolower($joined), 'recipe')
            || str_contains(strtolower($joined), 'incomplete'),
        );
    }

    public function test_command_is_read_only(): void
    {
        $before = WebsiteSetting::query()->count();
        $this->artisan('coffee:launch-readiness')->assertFailed();
        $this->assertSame($before, WebsiteSetting::query()->count());
        $this->assertSame(0, Product::query()->count());
    }

    protected function seedMinimalBusinessConfig(): void
    {
        $this->putSetting(WebsiteSettingKey::BusinessName, 'The88Coffees');
        $this->putSetting(WebsiteSettingKey::PaymentUpiId, 'cafe@upi');
        $this->putSetting(WebsiteSettingKey::PaymentQrImagePath, 'https://example.test/qr.png');
        $this->putSetting(WebsiteSettingKey::PagesTerms, 'Terms approved');
        $this->putSetting(WebsiteSettingKey::PagesPrivacy, 'Privacy approved');
        $this->putSetting(WebsiteSettingKey::BusinessOpeningHours, 'Daily 8–22');
    }

    protected function putSetting(WebsiteSettingKey $key, string $value): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => $key->value],
            [
                'section' => $key->section(),
                'value_type' => $key->valueType(),
                'value' => $value,
            ],
        );
    }
}
