<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class WebHcmRouteGuardTest extends TestCase
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

    /**
     * Setiap route GET dengan middleware grup `web` (termasuk guard halaman):
     * tanpa auth hanya boleh 200/3xx jika path whitelist config; selain itu 404 tamu.
     */
    public function test_all_web_guarded_get_routes_public_or_guest_404(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $cfg = config('arcav_hcm_web_guard', []);
        $publicPaths = $cfg['public_paths'] ?? [];
        $publicPrefixes = $cfg['public_prefixes'] ?? [];

        $seenPaths = [];

        foreach ($router->getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }
            if ($route->isFallback) {
                continue;
            }
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            // Middleware grup `web` memuat EnsureHcmWebPagesAuthenticated (append di bootstrap).
            $routeMiddleware = $route->gatherMiddleware();
            if (! in_array('web', $routeMiddleware, true)) {
                continue;
            }

            $uri = $route->uri();
            $samplePath = '/'.ltrim((string) preg_replace('/\{[^}]+\}/', '1', $uri), '/');
            $samplePath = $samplePath === '//' ? '/' : (rtrim($samplePath, '/') ?: '/');
            if (isset($seenPaths[$samplePath])) {
                continue;
            }
            $seenPaths[$samplePath] = true;

            $normalized = trim($samplePath, '/');

            $response = $this->get($samplePath);

            if ($this->isPublicNormalizedPath($normalized, $publicPaths, $publicPrefixes)) {
                $this->assertNotSame(
                    404,
                    $response->status(),
                    "Whitelist publik tidak boleh 404: GET {$samplePath}"
                );
                $this->assertLessThan(
                    500,
                    $response->status(),
                    "Route publik error server: GET {$samplePath}"
                );
            } else {
                $this->assertSame(
                    404,
                    $response->status(),
                    "Tamu harus 404: GET {$samplePath}"
                );
                $response->assertDontSee('MAIN MENU', false);
                $cacheControl = (string) $response->headers->get('Cache-Control');
                $this->assertStringContainsString('no-store', $cacheControl, "Cache-Control no-store: {$samplePath}");
            }
        }

        $this->assertGreaterThan(50, count($seenPaths), 'Harus menguji cukup banyak path web (route terdaftar).');
    }

    /**
     * @param  array<int, mixed>  $publicPaths
     * @param  array<int, mixed>  $publicPrefixes
     */
    private function isPublicNormalizedPath(string $normalized, array $publicPaths, array $publicPrefixes): bool
    {
        foreach ($publicPaths as $p) {
            $p = trim((string) $p, '/');
            if ($normalized === $p) {
                return true;
            }
        }
        foreach ($publicPrefixes as $prefix) {
            $prefix = trim((string) $prefix, '/');
            if ($prefix === '') {
                continue;
            }
            if ($normalized === $prefix || str_starts_with($normalized, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public function test_head_on_protected_path_returns_404_without_auth(): void
    {
        $this->call('HEAD', '/employees')->assertStatus(404);
    }

    public function test_head_on_public_root_succeeds(): void
    {
        $response = $this->call('HEAD', '/');
        $this->assertNotSame(404, $response->status());
        $this->assertLessThan(500, $response->status());
    }

    public function test_up_health_check_reachable_without_auth(): void
    {
        $this->get('/up')->assertSuccessful();
    }

    public function test_hcm_admin_api_cookie_can_open_promotion_resignation_termination(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'QA Admin Web',
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ]);

        $loginResponse->assertOk()->assertCookie($this->cookieName());
        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token);

        $cookieHeader = $this->cookieName().'='.$token;

        $adminPaths = [
            '/promotion',
            '/resignation',
            '/termination',
            '/salary-component-master',
            '/employee-salary',
            '/payroll',
            '/payroll-overtime',
            '/payroll-deduction',
            '/payroll-thr',
            '/payroll-run',
            '/payroll-run-history',
        ];
        foreach ($adminPaths as $path) {
            $this->withHeader('Cookie', $cookieHeader)
                ->get($path)
                ->assertOk("HCM admin + cookie API harus 200: {$path}");
        }
    }

    public function test_non_hcm_admin_api_cookie_redirected_from_promotion_resignation_termination(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Web Guard Employee',
            'email' => 'webguard-employee@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'webguard-employee@example.com',
            'password' => 'StrongPass1',
        ]);

        $loginResponse->assertOk()->assertCookie($this->cookieName());
        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token);

        $cookieHeader = $this->cookieName().'='.$token;

        $adminPaths = [
            '/promotion',
            '/resignation',
            '/termination',
            '/salary-component-master',
            '/employee-salary',
            '/payroll',
            '/payroll-overtime',
            '/payroll-deduction',
            '/payroll-thr',
            '/payroll-run',
            '/payroll-run-history',
        ];
        foreach ($adminPaths as $path) {
            $this->withHeader('Cookie', $cookieHeader)
                ->get($path)
                ->assertRedirect(url('employee-dashboard'));
        }
    }

    public function test_security_headers_present_on_web_response(): void
    {
        $response = $this->get('/');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_hcm_admin_web_session_can_open_promotion_resignation_termination(): void
    {
        $admin = User::factory()->create([
            'email' => 'qa.login@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin);
        $adminPaths = [
            '/promotion',
            '/resignation',
            '/termination',
            '/salary-component-master',
            '/employee-salary',
            '/payroll',
            '/payroll-overtime',
            '/payroll-deduction',
            '/payroll-thr',
            '/payroll-run',
            '/payroll-run-history',
        ];
        foreach ($adminPaths as $path) {
            $this->get($path)->assertOk("HCM admin + sesi web harus 200: {$path}");
        }
    }

    public function test_non_hcm_admin_web_session_redirected_from_promotion_resignation_termination(): void
    {
        $user = User::factory()->create([
            'email' => 'sessiononly-employee@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);
        $adminPaths = [
            '/promotion',
            '/resignation',
            '/termination',
            '/salary-component-master',
            '/employee-salary',
            '/payroll',
            '/payroll-overtime',
            '/payroll-deduction',
            '/payroll-thr',
            '/payroll-run',
            '/payroll-run-history',
        ];
        foreach ($adminPaths as $path) {
            $this->get($path)->assertRedirect(url('employee-dashboard'));
        }
    }

    public function test_authenticated_api_cookie_can_open_sample_hcm_pages(): void
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Multi Page User',
            'email' => 'multipage@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'multipage@example.com',
            'password' => 'StrongPass1',
        ]);
        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token);
        $cookieHeader = $this->cookieName().'='.$token;

        $paths = [
            '/employees',
            '/employees-grid',
            '/employee-details',
            '/departments',
            '/holidays',
            '/leaves',
            '/attendance-admin',
            '/tickets-admin',
            '/performance-review',
            '/training',
            '/clients',
            '/ui-buttons',
        ];

        foreach ($paths as $path) {
            $this->withHeader('Cookie', $cookieHeader)
                ->get($path)
                ->assertOk("Dengan cookie API harus 200: {$path}");
        }
    }
}
