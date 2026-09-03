<?php

namespace Database\Seeders;

use App\Services\Product\ProductCatalogServiceInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Local/testing demo catalog + operational dataset only.
 *
 * Hard-guarded: throws unless APP_ENV is local or testing.
 * Prefer `php artisan migrate:fresh --seed` (DatabaseSeeder routes here for local/testing).
 * Never run in production — even `php artisan db:seed --class=DemoSeeder` must fail loudly.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'DemoSeeder refused: demo/catalog data must never be seeded outside local/testing (APP_ENV='.app()->environment().').',
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
            DemoFoodCatalogSeeder::class,
            RecipeSeeder::class,
            DemoAddOnSeeder::class,
            InventoryRefillRequestSeeder::class,
            CafeTableSeeder::class,
            DemoPromotionSeeder::class,
            WebsiteSettingSeeder::class,
            CafeScheduleSeeder::class,
            DemoSocialLinkSeeder::class,
            DemoCustomerActivitySeeder::class,
            DemoOrderSeeder::class,
            DemoDiningSeeder::class,
            DemoReferralSeeder::class,
            ProductRatingSeeder::class,
            HomeSectionSeeder::class,
            DemoStaffNotificationSeeder::class,
        ]);

        app(ProductCatalogServiceInterface::class)->flushPublicCache();
    }
}
