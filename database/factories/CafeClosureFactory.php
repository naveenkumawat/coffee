<?php

namespace Database\Factories;

use App\Enums\CafeClosureType;
use App\Models\CafeClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CafeClosure>
 */
class CafeClosureFactory extends Factory
{
    protected $model = CafeClosure::class;

    public function definition(): array
    {
        $starts = now()->addDays(7)->startOfDay();

        return [
            'title' => fake()->words(3, true),
            'type' => CafeClosureType::Holiday,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addDay()->endOfDay(),
            'customer_message' => 'Closed for a holiday.',
            'internal_note' => null,
            'is_active' => true,
        ];
    }

    public function past(): static
    {
        return $this->state(fn (): array => [
            'title' => 'Past maintenance window',
            'type' => CafeClosureType::Maintenance,
            'starts_at' => now()->subDays(10)->setTime(14, 0),
            'ends_at' => now()->subDays(10)->setTime(17, 0),
            'customer_message' => 'Closed for maintenance.',
            'is_active' => true,
        ]);
    }
}
