<?php

namespace Database\Factories;

use App\Enums\IngredientUnit;
use App\Enums\InventoryTransactionType;
use App\Models\Ingredient;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ingredient_id' => Ingredient::factory(),
            'transaction_type' => InventoryTransactionType::StockAdded,
            'quantity' => '10.000',
            'base_quantity' => '10.000',
            'measurement_unit' => IngredientUnit::Gram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'stock_before' => '15.000',
            'stock_after' => '25.000',
            'reference_type' => null,
            'reference_id' => null,
            'notes' => fake()->sentence(),
            'created_by' => User::factory()->manager(),
        ];
    }
}
