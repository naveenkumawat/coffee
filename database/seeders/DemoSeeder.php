<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Local/testing demo catalog + operational dataset only.
 *
 * Never run in production. Prefer `php artisan migrate:fresh --seed`
 * which calls this via DatabaseSeeder when APP_ENV is local or testing.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'DemoSeeder refused: demo/catalog data must never be seeded in production.',
            );
        }

        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException(
                'DemoSeeder refused: only local and testing environments may receive demo data (APP_ENV='.app()->environment().').',
            );
        }

        Mail::fake();
        Notification::fake();
        Http::fake();

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
            CafeTableSeeder::class,
            WebsiteSettingSeeder::class,
            DemoSocialLinkSeeder::class,
            DemoCustomerActivitySeeder::class,
            DemoOrderSeeder::class,
            ProductRatingSeeder::class,
            HomeSectionSeeder::class,
            DemoStaffNotificationSeeder::class,
        ]);
    }
}
