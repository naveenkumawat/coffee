<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed strategy:
     *
     * - Always: safe structural taxonomy (ingredient categories, social platform shells) + optional ADMIN_* owner bootstrap.
     * - local/testing only: demo catalog, CMS sample content, customers, carts, orders, ratings, homepage sections.
     *
     * Production (`APP_ENV=production`) must not receive demo customers/orders/ratings/carts/favourites.
     * Configure real Website Settings, catalog, media, social URLs, and homepage sections in Administrator after migrate.
     */
    public function run(): void
    {
        $this->call([
            IngredientCategorySeeder::class,
            SocialLinkSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call([
                DemoUserSeeder::class,
                IngredientBrandSeeder::class,
                IngredientSeeder::class,
                InventoryTransactionSeeder::class,
                ProductCategorySeeder::class,
                ProductFlavourSeeder::class,
                ProductTagSeeder::class,
                ProductSeeder::class,
                RecipeSeeder::class,
                InventoryRefillRequestSeeder::class,
                WebsiteSettingSeeder::class,
                DemoCustomerActivitySeeder::class,
                DemoOrderSeeder::class,
                ProductRatingSeeder::class,
                HomeSectionSeeder::class,
            ]);
        }

        if (filled(env('ADMIN_EMAIL')) && filled(env('ADMIN_PASSWORD'))) {
            User::query()->updateOrCreate(
                ['email' => env('ADMIN_EMAIL')],
                [
                    'name' => env('ADMIN_NAME', 'Cafe Owner'),
                    'phone' => null,
                    'role' => UserRole::Owner,
                    'is_active' => true,
                    'password' => Hash::make((string) env('ADMIN_PASSWORD')),
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
