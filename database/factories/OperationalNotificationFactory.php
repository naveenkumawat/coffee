<?php

namespace Database\Factories;

use App\Enums\OperationalNotificationPriority;
use App\Models\OperationalNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OperationalNotification>
 */
class OperationalNotificationFactory extends Factory
{
    protected $model = OperationalNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'type' => 'ops.probe',
            'category' => 'system',
            'priority' => OperationalNotificationPriority::Normal,
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(8),
            'action_required' => false,
            'action_code' => null,
            'action_url' => null,
            'metadata' => null,
        ];
    }

    public function actionRequired(?string $actionCode = 'review'): static
    {
        return $this->state(fn () => [
            'action_required' => true,
            'action_code' => $actionCode,
            'priority' => OperationalNotificationPriority::High,
        ]);
    }
}
