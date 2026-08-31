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
     * - Always: structural taxonomy (ingredient categories, social platform shells) + optional ADMIN_* owner.
     * - local/testing only: DemoSeeder (full demo catalog, CMS, customers, orders, tables, staff bell data).
     * - production: NEVER DemoSeeder — configure Website Settings / catalog in Administrator after migrate.
     */
    public function run(): void
    {
        $this->call([
            IngredientCategorySeeder::class,
            SocialLinkSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
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
