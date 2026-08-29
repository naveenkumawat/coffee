<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('local', 'testing')) {
            $this->seedDevelopmentInternalUsers();
        }

        $this->call(IngredientCategorySeeder::class);

        if (app()->environment('local', 'testing')) {
            $this->call([
                IngredientBrandSeeder::class,
                IngredientSeeder::class,
                InventoryTransactionSeeder::class,
                ProductCategorySeeder::class,
                ProductFlavourSeeder::class,
                ProductSeeder::class,
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

        if (app()->environment('local', 'testing') && Product::query()->doesntExist()) {
            $this->call([
                ProductCategorySeeder::class,
                ProductFlavourSeeder::class,
                ProductSeeder::class,
            ]);
        }
    }

    protected function seedDevelopmentInternalUsers(): void
    {
        $users = [
            [
                'email' => 'admin@coffee.local',
                'name' => 'Coffee Administrator',
                'role' => UserRole::Owner,
            ],
            [
                'email' => 'barista@coffee.local',
                'name' => 'Coffee Barista',
                'role' => UserRole::Barista,
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'phone' => null,
                    'role' => $user['role'],
                    'is_active' => true,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
