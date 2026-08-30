<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\CustomerResetPasswordNotification;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerResetPasswordNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_password_reset_notification_uses_configured_pwa_url(): void
    {
        Notification::fake();

        config()->set('coffee.pwa.url', 'https://pwa.example.test');

        $customer = User::factory()->customer()->create([
            'email' => 'reset.customer@example.test',
        ]);

        $customer->sendPasswordResetNotification('sample-token');

        Notification::assertSentTo(
            $customer,
            CustomerResetPasswordNotification::class,
            function (CustomerResetPasswordNotification $notification, array $channels) use ($customer): bool {
                $mail = $notification->toMail($customer)->toArray();
                $query = [];

                parse_str((string) parse_url((string) $mail['actionUrl'], PHP_URL_QUERY), $query);

                $this->assertSame(['mail'], $channels);
                $this->assertSame('https://pwa.example.test/reset-password', strtok((string) $mail['actionUrl'], '?'));
                $this->assertSame('sample-token', $query['token'] ?? null);
                $this->assertSame($customer->email, $query['email'] ?? null);

                return true;
            }
        );
    }

    public function test_non_customer_password_reset_notification_remains_on_default_laravel_flow(): void
    {
        Notification::fake();

        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'email' => 'manager@example.test',
        ]);

        $manager->sendPasswordResetNotification('manager-reset-token');

        Notification::assertSentTo($manager, BaseResetPasswordNotification::class);
        Notification::assertNotSentTo($manager, CustomerResetPasswordNotification::class);
    }
}
