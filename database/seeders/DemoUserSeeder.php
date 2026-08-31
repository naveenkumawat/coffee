<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

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
                'is_active' => true,
            ],
            [
                'email' => 'manager@coffee.local',
                'name' => 'Coffee Manager',
                'phone' => '9000000002',
                'role' => UserRole::Manager,
                'is_active' => true,
            ],
            [
                'email' => 'barista@coffee.local',
                'name' => 'Coffee Barista',
                'phone' => '9000000003',
                'role' => UserRole::Barista,
                'is_active' => true,
            ],
            [
                'email' => 'barista2@coffee.local',
                'name' => 'Evening Barista',
                'phone' => '9000000004',
                'role' => UserRole::Barista,
                'is_active' => true,
            ],
            [
                'email' => 'inactive.staff@coffee.local',
                'name' => 'Inactive Cashier',
                'phone' => '9000000005',
                'role' => UserRole::Cashier,
                'is_active' => false,
            ],
            [
                'email' => 'customer@coffee.local',
                'name' => 'Demo Customer',
                'phone' => '9111111111',
                'role' => UserRole::Customer,
                'is_active' => true,
            ],
            [
                'email' => 'priya@coffee.local',
                'name' => 'Priya Sharma',
                'phone' => '9222222222',
                'role' => UserRole::Customer,
                'is_active' => true,
            ],
            [
                'email' => 'arjun@coffee.local',
                'name' => 'Arjun Mehta',
                'phone' => '9333333333',
                'role' => UserRole::Customer,
                'is_active' => true,
            ],
            [
                'email' => 'empty@coffee.local',
                'name' => 'Empty Cart Customer',
                'phone' => '9444444444',
                'role' => UserRole::Customer,
                'is_active' => true,
            ],
            [
                'email' => 'neha@coffee.local',
                'name' => 'Neha Kapoor',
                'phone' => '9555555555',
                'role' => UserRole::Customer,
                'is_active' => true,
            ],
            [
                'email' => 'rohan@coffee.local',
                'name' => 'Rohan Iyer',
                'phone' => '9666666666',
                'role' => UserRole::Customer,
                'is_active' => true,
            ],
            [
                'email' => 'meera@coffee.local',
                'name' => 'Meera Nair',
                'phone' => '9777777777',
                'role' => UserRole::Customer,
                'is_active' => true,
            ],
            [
                'email' => 'kabir@coffee.local',
                'name' => 'Kabir Das',
                'phone' => '9888888888',
                'role' => UserRole::Customer,
                'is_active' => true,
            ],
            [
                'email' => 'ananya@coffee.local',
                'name' => 'Ananya Bose',
                'phone' => '9999990001',
                'role' => UserRole::Customer,
                'is_active' => true,
            ],
            [
                'email' => 'vikram@coffee.local',
                'name' => 'Vikram Joshi',
                'phone' => '9999990002',
                'role' => UserRole::Customer,
                'is_active' => true,
            ],
            [
                'email' => 'sara@coffee.local',
                'name' => 'Sara Khan',
                'phone' => '9999990003',
                'role' => UserRole::Customer,
                'is_active' => true,
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'phone' => $user['phone'],
                    'role' => $user['role'],
                    'is_active' => $user['is_active'],
                    'cash_takeaway_allowed' => $user['role'] === UserRole::Customer
                        && in_array($user['email'], ['priya@coffee.local', 'arjun@coffee.local'], true),
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
