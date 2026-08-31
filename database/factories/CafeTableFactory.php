<?php

namespace Database\Factories;

use App\Models\CafeTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CafeTable>
 */
class CafeTableFactory extends Factory
{
    protected $model = CafeTable::class;

    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999);

        return [
            'code' => 'T'.$number,
            'name' => 'Table '.$number,
            'sort_order' => $number,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
