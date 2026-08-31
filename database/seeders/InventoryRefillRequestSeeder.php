<?php

namespace Database\Seeders;

use App\Enums\InventoryRefillRequestStatus;
use App\Models\Ingredient;
use App\Models\InventoryRefillRequest;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryRefillRequestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $barista = User::query()->where('email', 'barista@coffee.local')->first();
        $admin = User::query()->where('email', 'admin@coffee.local')->first();

        if (! $barista || ! $admin) {
            return;
        }

        foreach ($this->requests() as $definition) {
            $ingredient = Ingredient::query()->where('name', $definition['ingredient'])->first();

            if (! $ingredient) {
                continue;
            }

            InventoryRefillRequest::query()->updateOrCreate(
                [
                    'ingredient_id' => $ingredient->id,
                    'notes' => $definition['notes'],
                    'requested_by' => $barista->id,
                ],
                [
                    'quantity' => $definition['quantity'],
                    'base_quantity' => $definition['base_quantity'],
                    'measurement_unit' => $ingredient->measurement_unit,
                    'base_measurement_unit' => $ingredient->base_measurement_unit,
                    'status' => $definition['status'],
                    'reviewed_by' => $definition['reviewed'] ? $admin->id : null,
                    'reviewed_at' => $definition['reviewed'] ? now()->subDays($definition['days_ago']) : null,
                    'review_notes' => $definition['review_notes'],
                    'created_at' => now()->subDays($definition['days_ago']),
                    'updated_at' => now()->subDays(max($definition['days_ago'] - 1, 0)),
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function requests(): array
    {
        return [
            [
                'ingredient' => 'Oat Milk',
                'quantity' => '6.000',
                'base_quantity' => '6000.000',
                'notes' => 'DEMO: Oat milk running low before weekend rush.',
                'status' => InventoryRefillRequestStatus::Pending,
                'reviewed' => false,
                'review_notes' => null,
                'days_ago' => 1,
            ],
            [
                'ingredient' => 'Whipped Cream',
                'quantity' => '2.000',
                'base_quantity' => '2000.000',
                'notes' => 'DEMO: Whipped cream below minimum stock.',
                'status' => InventoryRefillRequestStatus::Approved,
                'reviewed' => true,
                'review_notes' => 'Approved for next dairy delivery.',
                'days_ago' => 2,
            ],
            [
                'ingredient' => 'White Sugar',
                'quantity' => '5.000',
                'base_quantity' => '5000.000',
                'notes' => 'DEMO: Sugar fully out of stock.',
                'status' => InventoryRefillRequestStatus::Rejected,
                'reviewed' => true,
                'review_notes' => 'Use pantry bulk order instead of cafe refill.',
                'days_ago' => 3,
            ],
            [
                'ingredient' => 'Vanilla Syrup',
                'quantity' => '3.000',
                'base_quantity' => '3.000',
                'notes' => 'DEMO: Completed syrup refill for iced latte service.',
                'status' => InventoryRefillRequestStatus::Completed,
                'reviewed' => true,
                'review_notes' => 'Received and stocked. Stock already reflected in current balances.',
                'days_ago' => 5,
            ],
            [
                'ingredient' => 'Davidoff Espresso',
                'quantity' => '1.000',
                'base_quantity' => '1000.000',
                'notes' => 'DEMO: Extra espresso bags for morning peak.',
                'status' => InventoryRefillRequestStatus::Pending,
                'reviewed' => false,
                'review_notes' => null,
                'days_ago' => 0,
            ],
            [
                'ingredient' => 'Fresh Lime',
                'quantity' => '2.000',
                'base_quantity' => '2000.000',
                'notes' => 'DEMO: Mojito prep needs citrus restock.',
                'status' => InventoryRefillRequestStatus::Approved,
                'reviewed' => true,
                'review_notes' => 'Approved — order with produce run.',
                'days_ago' => 1,
            ],
            [
                'ingredient' => 'Caramel Syrup',
                'quantity' => '2.000',
                'base_quantity' => '2.000',
                'notes' => 'DEMO: Caramel bottle nearly empty.',
                'status' => InventoryRefillRequestStatus::Completed,
                'reviewed' => true,
                'review_notes' => 'Restocked from back inventory.',
                'days_ago' => 4,
            ],
            [
                'ingredient' => 'Demo Butter Croissant',
                'quantity' => '24.000',
                'base_quantity' => '24.000',
                'notes' => 'DEMO: Weekend pastry tray request.',
                'status' => InventoryRefillRequestStatus::Rejected,
                'reviewed' => true,
                'review_notes' => 'Use existing morning bake schedule instead.',
                'days_ago' => 2,
            ],
        ];
    }
}
