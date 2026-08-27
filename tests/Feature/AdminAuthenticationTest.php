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

    public function test_admin_login_page_loads(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Admin login');
    }

    public function test_owner_can_login_to_admin_guard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Owner,
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user, 'admin');
    }
}
