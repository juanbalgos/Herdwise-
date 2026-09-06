<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_responses_include_browser_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertHeader('cache-control', 'no-store, private')
            ->assertHeader('content-security-policy', "default-src 'self'; base-uri 'self'; connect-src 'self'; font-src 'self' https://fonts.gstatic.com; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; object-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com")
            ->assertHeader('cross-origin-opener-policy', 'same-origin')
            ->assertHeader('permissions-policy', 'camera=(), geolocation=(), microphone=()')
            ->assertHeader('referrer-policy', 'strict-origin-when-cross-origin')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('x-frame-options', 'DENY')
            ->assertHeaderMissing('strict-transport-security');
    }

    public function test_hsts_is_sent_only_when_enabled_for_a_secure_request(): void
    {
        config(['security.hsts_enabled' => true]);

        $this->get('https://localhost/login')
            ->assertOk()
            ->assertHeader('strict-transport-security', 'max-age=31536000');
    }

    public function test_authenticated_farm_pages_are_not_cacheable(): void
    {
        $user = User::factory()->create();
        $farm = Farm::factory()->create();
        $farm->users()->attach($user, ['role' => 'owner', 'is_active' => true]);

        $this->actingAs($user)
            ->withSession(['current_farm_id' => $farm->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private');
    }

    public function test_login_is_rate_limited_by_normalized_email_and_ip_address(): void
    {
        $credentials = ['email' => 'rate-limit@example.test', 'password' => 'incorrect'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->post('/login', $credentials)
                ->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post('/login', ['email' => 'RATE-LIMIT@example.test', 'password' => 'incorrect'])
            ->assertTooManyRequests();
    }

    public function test_registration_is_rate_limited_by_ip_address(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
                ->post('/register', [])
                ->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->post('/register', [])
            ->assertTooManyRequests();
    }
}
