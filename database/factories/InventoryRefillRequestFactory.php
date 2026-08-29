<?php

namespace Database\Factories;

use App\Enums\IngredientUnit;
use App\Enums\InventoryRefillRequestStatus;
use App\Models\Ingredient;
use App\Models\InventoryRefillRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryRefillRequest>
 */
class InventoryRefillRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ingredient_id' => Ingredient::factory(),
            'quantity' => '2.000',
            'base_quantity' => '2000.000',
            'measurement_unit' => IngredientUnit::Kilogram,
            'base_measurement_unit' => IngredientUnit::Gram,
            'notes' => fake()->sentence(),
            'requested_by' => User::factory()->barista(),
            'status' => InventoryRefillRequestStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ];
    }
}
