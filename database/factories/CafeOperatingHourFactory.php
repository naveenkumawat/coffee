<?php

namespace Database\Factories;

use App\Models\CafeOperatingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CafeOperatingHour>
 */
class CafeOperatingHourFactory extends Factory
{
    protected $model = CafeOperatingHour::class;

    public function definition(): array
    {
        return [
            'weekday' => fake()->numberBetween(0, 6),
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
            'sort_order' => 0,
        ];
    }
}
