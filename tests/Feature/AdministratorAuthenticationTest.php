<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdministratorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_login_page_loads(): void
    {
        $this->get(route('administrator.login'))
            ->assertOk()
            ->assertSee('Administrator sign in');
    }

    public function test_administrator_root_redirects_guest_to_login(): void
    {
        $this->get(route('administrator.root'))
            ->assertRedirect(route('administrator.login'));
    }

    public function test_legacy_admin_alias_routes_do_not_exist(): void
    {
        $this->get('/admin')->assertNotFound();

        $legacyAdminRouteNames = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.'));

        $this->assertCount(0, $legacyAdminRouteNames);
    }

    public function test_guest_is_redirected_to_administrator_login_from_protected_administrator_route(): void
    {
        $this->get(route('administrator.menu.items.index'))
            ->assertRedirect(route('administrator.login'));
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

    public function test_barista_root_redirects_authenticated_barista_to_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Barista,
        ]);

        $this->actingAs($user, 'admin')
            ->get(route('barista.root'))
            ->assertRedirect(route('barista.dashboard'));
    }

    public function test_guest_is_redirected_to_barista_login_from_protected_barista_route(): void
    {
        $this->get(route('barista.dashboard'))
            ->assertRedirect(route('barista.login'));
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
