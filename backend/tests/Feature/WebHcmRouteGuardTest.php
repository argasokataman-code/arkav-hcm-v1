<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
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
     * tanpa auth hanya boleh 200/3xx jika path whitelist config; selain itu redirect lock-screen.
     */
    public function test_all_web_guarded_get_routes_public_or_guest_redirect_lock_screen(): void
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
            if (str_contains($uri, '{')) {
                // Broad smoke test only covers static web paths. Parameterized routes can
                // 404 with synthetic sample values before the intended guard behavior is exercised.
                continue;
            }
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
                $response->assertRedirect(url('lock-screen'));
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

    /**
     * @return array<int, string>
     */
    private function criticalAdminWebPaths(): array
    {
        return [
            '/dashboard',
            '/saas-dashboard',
            '/saas/transactions',
            '/purchase-transaction',
            '/promotion',
            '/resignation',
            '/termination',
            '/leave-type',
            '/users',
            '/roles-permissions',
            '/salary-component-master',
            '/employee-salary',
            '/payroll',
            '/payroll-overtime',
            '/payroll-deduction',
            '/payroll-thr',
            '/payroll-run',
            '/payroll-run-history',
            '/expenses-report',
            '/invoice-report',
            '/payment-report',
            '/project-report',
            '/task-report',
            '/user-report',
            '/employee-report',
            '/payslip-report',
            '/attendance-report',
            '/leave-report',
            '/daily-report',
            '/bussiness-settings',
            '/business-settings',
            '/seo-settings',
            '/localization-settings',
            '/prefixes',
            '/preferences',
            '/appearance',
            '/language',
            '/authentication-settings',
            '/ai-settings',
            '/salary-settings',
            '/approval-settings',
            '/invoice-settings',
            '/custom-fields',
            '/email-settings',
            '/email-template',
            '/sms-settings',
            '/sms-template',
            '/otp-settings',
            '/gdpr',
            '/maintenance-mode',
            '/payment-gateways',
            '/tax-rates',
            '/currencies',
            '/custom-css',
            '/custom-js',
            '/cronjob',
            '/cronjob-schedule',
            '/storage-settings',
            '/ban-ip-address',
            '/backup',
            '/clear-cache',
        ];
    }

    public function test_head_on_protected_path_redirects_lock_screen_without_auth(): void
    {
        $this->call('HEAD', '/employees')->assertRedirect(url('lock-screen'));
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

    public function test_api_docs_swagger_uses_same_origin_spec_path(): void
    {
        $response = $this->get('/api-docs');

        $response->assertOk();
        $response->assertSee('/api-docs\/openapi.yaml', false);
        $response->assertDontSee('http://arkav.puree.id/api-docs/openapi.yaml', false);
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

        $adminPaths = $this->criticalAdminWebPaths();
        foreach ($adminPaths as $path) {
            $response = $this->withHeader('Cookie', $cookieHeader)
                ->followingRedirects()
                ->get($path);

            $response->assertOk("HCM admin + cookie API harus 200 setelah redirect normal: {$path}");
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

        $adminPaths = $this->criticalAdminWebPaths();
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
        $adminPaths = $this->criticalAdminWebPaths();
        foreach ($adminPaths as $path) {
            $this->followingRedirects()
                ->get($path)
                ->assertOk("HCM admin + sesi web harus 200 setelah redirect normal: {$path}");
        }
    }

    public function test_non_hcm_admin_web_session_redirected_from_promotion_resignation_termination(): void
    {
        $user = User::factory()->create([
            'email' => 'sessiononly-employee@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);
        $adminPaths = $this->criticalAdminWebPaths();
        foreach ($adminPaths as $path) {
            $this->get($path)->assertRedirect(url('employee-dashboard'));
        }
    }

    public function test_tenant_hcm_admin_without_global_signal_is_redirected_from_super_admin_dashboard(): void
    {
        $company = Company::query()->create([
            'code' => 'tenant_admin_guard',
            'name' => 'Tenant Guard Company',
            'legal_name' => 'Tenant Guard Company PT',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $user = User::query()->create([
            'name' => 'Tenant HCM Admin',
            'email' => 'qa.hcm@example.com',
            'password' => bcrypt('password'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employment_status' => 'active',
            'designation' => 'HCM Admin',
            'team' => 'HCM',
            'nik' => 'EMP-GLOBAL-GUARD',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
        ]);

        foreach ([
            '/dashboard',
            '/saas-dashboard',
            '/saas/subscriptions',
            '/saas/billing-overview',
            '/saas/transactions',
            '/companies',
            '/packages',
            '/domain',
            '/purchase-transaction',
            '/email-settings',
            '/cronjob-schedule',
            '/business-settings',
            '/bussiness-settings',
            '/seo-settings',
            '/localization-settings',
            '/currencies',
        ] as $path) {
            $this->actingAs($user)
                ->withHeader('X-Company-Code', $company->code)
                ->get($path)
                ->assertRedirect(url('employee-dashboard'));
        }

        $this->actingAs($user)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/subscription')
            ->assertOk();
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
            // Authenticated (non-admin) pages should render (web guard requires auth, but RBAC can still redirect).
            '/employee-dashboard',
            '/attendance-employee',
            '/tickets-employee',
            '/performance-review',
            '/goal-tracking',
            '/payslip',
            // Template showcase pages are still behind auth (not public) and can render for authenticated users.
            '/ui-buttons',
        ];

        foreach ($paths as $path) {
            $this->withHeader('Cookie', $cookieHeader)
                ->get($path)
                ->assertOk("Dengan cookie API harus 200: {$path}");
        }
    }
}
