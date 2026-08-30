<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $users = [
            [
                'email' => 'admin@coffee.local',
                'name' => 'Coffee Administrator',
                'phone' => '9000000001',
                'role' => UserRole::Owner,
            ],
            [
                'email' => 'manager@coffee.local',
                'name' => 'Coffee Manager',
                'phone' => '9000000002',
                'role' => UserRole::Manager,
            ],
            [
                'email' => 'barista@coffee.local',
                'name' => 'Coffee Barista',
                'phone' => '9000000003',
                'role' => UserRole::Barista,
            ],
            [
                'email' => 'barista2@coffee.local',
                'name' => 'Evening Barista',
                'phone' => '9000000004',
                'role' => UserRole::Barista,
            ],
            [
                'email' => 'customer@coffee.local',
                'name' => 'Demo Customer',
                'phone' => '9111111111',
                'role' => UserRole::Customer,
            ],
            [
                'email' => 'priya@coffee.local',
                'name' => 'Priya Sharma',
                'phone' => '9222222222',
                'role' => UserRole::Customer,
            ],
            [
                'email' => 'arjun@coffee.local',
                'name' => 'Arjun Mehta',
                'phone' => '9333333333',
                'role' => UserRole::Customer,
            ],
            [
                'email' => 'empty@coffee.local',
                'name' => 'Empty Cart Customer',
                'phone' => '9444444444',
                'role' => UserRole::Customer,
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'phone' => $user['phone'],
                    'role' => $user['role'],
                    'is_active' => true,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
