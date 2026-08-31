<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SanctumSpaCorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_cookie_endpoint_allows_configured_spa_origin_with_credentials(): void
    {
        $origin = 'http://192.168.29.175:4173';

        Config::set('cors.allowed_origins', [$origin, 'http://localhost:4173']);
        Config::set('sanctum.stateful', ['192.168.29.175:4173', 'localhost:4173']);

        $response = $this
            ->withHeader('Origin', $origin)
            ->withHeader('Accept', 'application/json')
            ->get('/sanctum/csrf-cookie');

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', $origin);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $response->assertHeader('Access-Control-Expose-Headers', 'X-CSRF-TOKEN');
        $response->assertHeader('X-CSRF-TOKEN');
        $this->assertNotSame('', (string) $response->headers->get('X-CSRF-TOKEN'));
        $response->assertCookie('XSRF-TOKEN');
    }

    public function test_login_from_configured_spa_origin_does_not_return_csrf_mismatch(): void
    {
        $origin = 'http://192.168.29.175:4173';

        Config::set('cors.allowed_origins', [$origin]);
        Config::set('sanctum.stateful', ['192.168.29.175:4173']);

        $customer = User::factory()->customer()->create([
            'email' => 'lan-customer@example.com',
            'password' => 'password',
        ]);

        $this
            ->withHeader('Origin', $origin)
            ->withHeader('Accept', 'application/json')
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();

        $login = $this
            ->withHeader('Origin', $origin)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Referer', $origin.'/')
            ->postJson('/api/v1/auth/login', [
                'login' => $customer->email,
                'password' => 'password',
            ]);

        $this->assertNotSame(419, $login->status(), $login->getContent());
        $login->assertOk();
        $login->assertJsonPath('data.email', $customer->email);
    }
}
