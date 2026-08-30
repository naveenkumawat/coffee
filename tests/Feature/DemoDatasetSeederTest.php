<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Ingredient;
use App\Models\InventoryRefillRequest;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductFavourite;
use App\Models\Recipe;
use App\Models\User;
use App\Models\WebsiteSetting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDatasetSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_builds_a_usable_demo_dataset(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::query()->where('email', 'admin@coffee.local')->exists());
        $this->assertTrue(User::query()->where('email', 'barista@coffee.local')->exists());
        $this->assertTrue(User::query()->where('email', 'customer@coffee.local')->exists());

        $this->assertGreaterThanOrEqual(12, Ingredient::query()->count());
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

        $this->assertGreaterThanOrEqual(15, Product::query()->count());
        $this->assertTrue(Product::query()->where('is_new', true)->exists());
        $this->assertTrue(Product::query()->where('is_bestseller', true)->exists());
        $this->assertTrue(Product::query()->where('is_vegetarian', true)->exists());
        $this->assertTrue(Product::query()->where('is_customizable', true)->exists());
        $this->assertTrue(Product::query()->where('is_available', false)->exists());
        $this->assertTrue(Product::query()->whereNotNull('customer_ingredient_summary')->exists());

        $this->assertGreaterThanOrEqual(5, Recipe::query()->whereHas('lines')->count());
        $this->assertGreaterThanOrEqual(4, InventoryRefillRequest::query()->count());
        $this->assertGreaterThanOrEqual(4, ProductFavourite::query()->count());

        foreach (OrderStatus::cases() as $status) {
            $this->assertTrue(
                Order::query()->where('status', $status)->exists(),
                "Expected a seeded order with status [{$status->value}].",
            );
        }

        $this->assertTrue(Order::query()->where('status', OrderStatus::PendingPayment)->exists());

        $this->assertTrue(
            WebsiteSetting::query()->where('key', 'hero_title')->whereNotNull('value')->exists(),
        );
        $this->assertTrue(
            WebsiteSetting::query()->where('key', 'pages_faq')->whereNotNull('value')->exists(),
        );
    }
}
