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
     * Seed strategy (gated by APP_ENV, never APP_DEBUG alone):
     *
     * Always (every environment):
     * - IngredientCategorySeeder — inventory taxonomy reference rows
     * - ProductTagSeeder — marketing tag definitions (New / Top Seller / …)
     * - SocialLinkSeeder — platform shells without fake URLs
     * - Optional Owner bootstrap when ADMIN_EMAIL + ADMIN_PASSWORD are set
     *
     * local / testing only:
     * - DemoSeeder — full café simulation (staff, customers, catalog, CMS, orders, …)
     *
     * production:
     * - Never calls DemoSeeder. Configure real catalog/CMS in Administrator after migrate.
     */
    public function run(): void
    {
        $this->call([
            IngredientCategorySeeder::class,
            ProductTagSeeder::class,
            SocialLinkSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoSeeder::class);
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
