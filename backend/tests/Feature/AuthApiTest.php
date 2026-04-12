<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function cookieName(): string
    {
        return (string) config('auth.api_token_cookie.name', 'arcav_access_token');
    }

    private function readCookieValueFromLoginResponse(\Illuminate\Testing\TestResponse $response): string
    {
        $setCookies = $response->headers->getCookies();
        foreach ($setCookies as $cookie) {
            if ($cookie->getName() === $this->cookieName()) {
                return (string) $cookie->getValue();
            }
        }

        return '';
    }

    public function test_user_can_register_login_me_and_logout(): void
    {
        $registerResponse = $this->postJson('/v1/identity/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $registerResponse
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'john@example.com');

        $defaultCompany = Company::query()->where('code', 'default_company')->first();
        $this->assertNotNull($defaultCompany);

        $membership = CompanyUser::query()
            ->where('company_id', $defaultCompany->id)
            ->whereHas('user', function ($query): void {
                $query->where('email', 'john@example.com');
            })
            ->first();
        $this->assertNotNull($membership);
        $this->assertSame('member', $membership->role);
        $this->assertSame('active', $membership->status);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['accessToken', 'tokenType', 'expiresIn', 'user'],
            ])
            ->assertCookie($this->cookieName());

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token, 'Cookie token was not set.');

        $cookieHeader = $this->cookieName().'='.$token;

        $meResponse = $this->withHeader('Cookie', $cookieHeader)
            ->getJson('/v1/identity/auth/me');

        $meResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'john@example.com');

        $logoutResponse = $this->withHeader('Cookie', $cookieHeader)
            ->postJson('/v1/identity/auth/logout');

        $logoutResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertCookieExpired($this->cookieName());

        $this->getJson('/v1/identity/auth/me')->assertStatus(401);
    }

    public function test_login_returns_validation_error_for_bad_payload(): void
    {
        $response = $this->postJson('/v1/identity/auth/login', [
            'email' => 'invalid-email',
            'password' => 'short',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_remember_me_login_has_longer_expiry_than_regular_login(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $regular = $this->postJson('/v1/identity/auth/login', [
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
            'rememberMe' => false,
        ])->assertStatus(200);

        $remember = $this->postJson('/v1/identity/auth/login', [
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
            'rememberMe' => true,
        ])->assertStatus(200);

        $this->assertGreaterThan(
            (int) $regular->json('data.expiresIn'),
            (int) $remember->json('data.expiresIn')
        );
    }

    public function test_login_is_throttled_after_multiple_failed_attempts(): void
    {
        RateLimiter::clear('auth:login:john@example.com|127.0.0.1');

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/v1/identity/auth/login', [
                'email' => 'john@example.com',
                'password' => 'WrongPass1',
            ])->assertStatus(401);
        }

        $this->postJson('/v1/identity/auth/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPass1',
        ])->assertStatus(429)
            ->assertJsonPath('error.code', 'AUTH_TOO_MANY_ATTEMPTS');
    }
}
