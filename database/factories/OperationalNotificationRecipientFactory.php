<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\OperationalNotification;
use App\Models\OperationalNotificationRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalNotificationRecipient>
 */
class OperationalNotificationRecipientFactory extends Factory
{
    protected $model = OperationalNotificationRecipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operational_notification_id' => OperationalNotification::factory(),
            'user_id' => User::factory(),
            'role' => UserRole::Customer,
            'reminder_count' => 0,
        ];
    }
}
