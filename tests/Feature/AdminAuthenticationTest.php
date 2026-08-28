<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_login_page_loads(): void
    {
        $this->get(route('administrator.login'))
            ->assertOk()
            ->assertSee('Administrator sign in');
    }

    public function test_owner_can_login_to_administrator_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Owner,
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->post(route('administrator.login.store'), [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('administrator.dashboard'));
        $this->assertAuthenticatedAs($user, 'admin');
    }

    public function test_barista_can_login_to_barista_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Barista,
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->post(route('barista.login.store'), [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('barista.dashboard'));
        $this->assertAuthenticatedAs($user, 'admin');
    }

    public function test_manager_cannot_login_to_barista_panel(): void
    {
        $user = User::factory()->manager()->create([
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->from(route('barista.login'))->post(route('barista.login.store'), [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response
            ->assertRedirect(route('barista.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }
}
