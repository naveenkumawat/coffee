<?php

namespace Tests\Feature;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Models\AudienceSegment;
use App\Models\CafeTable;
use App\Models\Campaign;
use App\Models\Ingredient;
use App\Models\InventoryRefillRequest;
use App\Models\InventoryTransaction;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFavourite;
use App\Models\ProductRating;
use App\Models\Promotion;
use App\Models\Recipe;
use App\Models\User;
use App\Models\WebsiteSetting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoDatasetSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_builds_a_usable_demo_dataset(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::query()->where('email', 'admin@coffee.local')->exists());
        $this->assertTrue(User::query()->where('email', 'barista@coffee.local')->exists());
        $this->assertTrue(User::query()->where('email', 'barista2@coffee.local')->exists());
        $this->assertTrue(User::query()->where('email', 'customer@coffee.local')->exists());
        $this->assertTrue(User::query()->where('email', 'inactive.staff@coffee.local')->where('is_active', false)->exists());
        $this->assertGreaterThanOrEqual(10, User::query()->where('role', 'customer')->count());

        $this->assertGreaterThanOrEqual(20, Ingredient::query()->count());
        $this->assertTrue(Ingredient::query()->where('name', 'White Sugar')->where('current_stock', '0.000')->exists());
        $this->assertTrue(Ingredient::query()->where('name', 'Oat Milk')->whereColumn('current_stock', '<', 'minimum_stock')->exists());
        $this->assertGreaterThan(
            0,
            InventoryTransaction::query()->where('reference_type', 'seeder_opening_balance')->count(),
        );
        $this->assertGreaterThanOrEqual(
            5,
            InventoryTransaction::query()->where('reference_type', 'seeder_demo_movement')->count(),
        );

        $this->assertGreaterThanOrEqual(25, Product::query()->count());
        $this->assertTrue(Product::query()->where('is_new', true)->exists());
        $this->assertTrue(Product::query()->where('is_bestseller', true)->exists());
        $this->assertTrue(Product::query()->where('is_vegetarian', true)->exists());
        $this->assertTrue(Product::query()->where('is_customizable', true)->exists());
        $this->assertTrue(Product::query()->where('is_available', false)->exists());
        $this->assertTrue(Product::query()->where('is_active', false)->exists());
        $this->assertTrue(Product::query()->whereNotNull('customer_ingredient_summary')->exists());

        $this->assertGreaterThanOrEqual(30, Recipe::query()->whereHas('lines')->count());
        $this->assertTrue(Product::query()->where('name', 'Draft Rose Latte')->where('is_active', false)->exists());
        $this->assertFalse(
            Recipe::query()
                ->whereHas('variant.product', fn ($query) => $query->where('name', 'Draft Rose Latte'))
                ->exists(),
        );
        $this->assertGreaterThanOrEqual(6, InventoryRefillRequest::query()->count());
        $this->assertGreaterThanOrEqual(4, ProductFavourite::query()->count());
        $this->assertGreaterThanOrEqual(30, ProductRating::query()->count());

        $this->assertGreaterThanOrEqual(10, CafeTable::query()->count());
        $this->assertTrue(CafeTable::query()->where('code', 'T1')->where('is_active', true)->exists());
        $this->assertTrue(CafeTable::query()->where('is_active', false)->exists());

        foreach (OrderStatus::cases() as $status) {
            $this->assertTrue(
                Order::query()->where('status', $status)->exists(),
                "Expected a seeded order with status [{$status->value}].",
            );
        }

        $this->assertTrue(Order::query()->where('fulfilment_method', OrderFulfilmentMethod::Takeaway)->exists());
        $this->assertTrue(Order::query()->where('fulfilment_method', OrderFulfilmentMethod::Delivery)->exists());
        $this->assertTrue(Order::query()->where('fulfilment_method', OrderFulfilmentMethod::DineIn)->exists());
        $this->assertTrue(
            Order::query()->where('fulfilment_method', OrderFulfilmentMethod::DineIn)
                ->whereNotNull('table_name_snapshot')
                ->exists(),
        );
        $this->assertTrue(Order::query()->where('payment_status', 'rejected')->exists());

        $this->assertTrue(
            WebsiteSetting::query()->where('key', 'hero_title')->whereNotNull('value')->exists(),
        );
        $this->assertTrue(
            WebsiteSetting::query()->where('key', 'pages_faq')->whereNotNull('value')->exists(),
        );
        $this->assertTrue(
            WebsiteSetting::query()->where('key', 'fulfilment_dine_in_enabled')->where('value', '1')->exists(),
        );

        $this->assertGreaterThanOrEqual(10, Promotion::query()->where('name', 'like', '[Demo]%')->count());
        $this->assertGreaterThanOrEqual(10, AudienceSegment::query()->where('name', 'like', '[Demo]%')->count());
        $this->assertGreaterThanOrEqual(8, LoyaltyReward::query()->where('name', 'like', '[Demo]%')->count());
        $this->assertGreaterThanOrEqual(10, Campaign::query()->where('name', 'like', '[Demo]%')->count());

        $this->assertGreaterThan(
            0,
            DB::table('notifications')->where('notifiable_type', User::class)->count(),
        );
    }
}
