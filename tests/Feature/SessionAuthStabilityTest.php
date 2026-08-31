<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionAuthStabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_lifetime_defaults_to_cafe_friendly_idle_window(): void
    {
        $this->assertSame(480, (int) config('session.lifetime'));
        $this->assertFalse((bool) config('session.expire_on_close'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertFalse((bool) config('session.secure'));
    }

    public function test_customer_api_session_persists_across_authenticated_requests(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'stable@example.test',
            'password' => 'password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => $customer->email,
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $customer->email);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $customer->email);
    }

    public function test_admin_and_barista_sessions_remain_authenticated_for_panel_requests(): void
    {
        $manager = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.dashboard'))
            ->assertOk();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.dashboard'))
            ->assertOk();

        $this->actingAs($barista, 'admin')
            ->get(route('barista.dashboard'))
            ->assertOk();

        $this->actingAs($barista, 'admin')
            ->get(route('barista.dashboard'))
            ->assertOk();
    }

    public function test_sanctum_stateful_domains_include_lan_api_host_when_configured(): void
    {
        Config::set('sanctum.stateful', [
            'localhost',
            'localhost:4173',
            '192.168.29.175',
            '192.168.29.175:4173',
        ]);

        $this->assertContains('192.168.29.175', config('sanctum.stateful'));
        $this->assertContains('192.168.29.175:4173', config('sanctum.stateful'));
    }

    public function test_unauthenticated_me_endpoint_returns_401_without_side_effects(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/v1/auth/me')->assertOk();
    }
}
