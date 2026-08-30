<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerPhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_login_with_email_or_phone(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'maya@example.test',
            'phone' => '9876543210',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson(route('api.v1.auth.login'), [
            'login' => 'maya@example.test',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id);

        $this->postJson(route('api.v1.auth.logout'))->assertOk();

        $this->postJson(route('api.v1.auth.login'), [
            'login' => '+91 98765-43210',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id);

        $this->postJson(route('api.v1.auth.logout'))->assertOk();

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'maya@example.test',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id);
    }

    public function test_invalid_identifier_or_password_is_rejected(): void
    {
        User::factory()->customer()->create([
            'email' => 'maya@example.test',
            'phone' => '9876543210',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson(route('api.v1.auth.login'), [
            'login' => 'maya@example.test',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login', 'email']);

        $this->postJson(route('api.v1.auth.login'), [
            'login' => '0000000000',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login', 'email']);

        $this->postJson(route('api.v1.auth.login'), [
            'login' => 'not-an-email-or-phone',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login', 'email']);
    }

    public function test_customer_phone_must_be_unique_when_registering(): void
    {
        User::factory()->customer()->create([
            'phone' => '9111222333',
        ]);

        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Other Customer',
            'email' => 'other@example.test',
            'phone' => '911-122-2333',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }
}
