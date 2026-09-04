<?php

namespace Database\Factories;

use App\Enums\AudienceSegmentActor;
use App\Enums\AudienceSegmentStatus;
use App\Models\AudienceSegment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AudienceSegment>
 */
class AudienceSegmentFactory extends Factory
{
    protected $model = AudienceSegment::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->optional()->sentence(),
            'status' => AudienceSegmentStatus::Draft,
            'actor_scope' => AudienceSegmentActor::Both,
            'rules' => [
                'all' => [
                    ['type' => 'identity', 'op' => 'eq', 'value' => 'everyone'],
                ],
                'any' => [],
                'exclude' => [],
            ],
            'stable_key' => 'seg_'.Str::lower(Str::random(16)),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => AudienceSegmentStatus::Active,
        ]);
    }
}
