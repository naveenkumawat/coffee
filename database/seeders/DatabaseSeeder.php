<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\MenuCategory;
use App\Models\MenuItem;
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

        if (app()->environment('local', 'testing') && MenuCategory::query()->doesntExist()) {
            $espresso = MenuCategory::query()->create([
                'name' => 'Espresso Bar',
                'slug' => 'espresso-bar',
                'description' => 'Core coffee service built for busy mornings.',
                'sort_order' => 1,
                'is_active' => true,
            ]);

            $bakes = MenuCategory::query()->create([
                'name' => 'Bakery',
                'slug' => 'bakery',
                'description' => 'Fresh pastries and quick breakfast staples.',
                'sort_order' => 2,
                'is_active' => true,
            ]);

            MenuItem::query()->create([
                'menu_category_id' => $espresso->id,
                'name' => 'House Flat White',
                'slug' => 'house-flat-white',
                'description' => 'Double ristretto, textured milk, and a caramel finish.',
                'price' => '4.75',
                'sort_order' => 1,
                'is_available' => true,
                'is_featured' => true,
            ]);

            MenuItem::query()->create([
                'menu_category_id' => $bakes->id,
                'name' => 'Brown Butter Croissant',
                'slug' => 'brown-butter-croissant',
                'description' => 'Laminated pastry finished with sea salt and cane sugar.',
                'price' => '3.95',
                'sort_order' => 1,
                'is_available' => true,
                'is_featured' => true,
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
