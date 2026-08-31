<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ProductionSecurityConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_cors_requires_explicit_origins_and_supports_credentials(): void
    {
        $this->assertTrue((bool) config('cors.supports_credentials'));
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $this->assertContains('api/*', config('cors.paths'));
        $this->assertContains('sanctum/csrf-cookie', config('cors.paths'));
    }

    public function test_security_headers_are_present_on_responses(): void
    {
        $response = $this->getJson(route('api.v1.content.show'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_hsts_header_is_sent_when_enabled(): void
    {
        Config::set('app.send_hsts', true);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_customer_auth_endpoints_are_rate_limited(): void
    {
        RateLimiter::for('customer-auth', function (Request $request) {
            return Limit::perMinute(3)->by('production-security-test');
        });

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('api.v1.auth.login'), [
                'login' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->postJson(route('api.v1.auth.login'), [
            'login' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
