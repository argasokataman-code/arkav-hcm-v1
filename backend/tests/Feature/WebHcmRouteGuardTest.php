<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class WebHcmRouteGuardTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(array $overrides = []): Company
    {
        return Company::query()->create(array_merge([
            'code' => 'web_guard_'.str()->lower((string) str()->random(8)),
            'name' => 'Web Guard Company',
            'legal_name' => 'Web Guard Company PT',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ], $overrides));
    }

    private function attachUserToCompany(Company $company, User $user, string $role = 'admin', string $designation = 'HCM Admin'): void
    {
        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employment_status' => 'active',
            'designation' => $designation,
            'team' => 'HCM',
            'nik' => 'EMP-'.str()->upper((string) str()->random(10)),
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => now()->subDay(),
        ]);
    }

    private function cookieName(): string
    {
        return (string) config('auth.api_token_cookie.name', 'arcav_access_token');
    }

    private function readCookieValueFromLoginResponse(TestResponse $response): string
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
    private function tenantAdminWebPaths(): array
    {
        return [
            '/promotion',
            '/resignation',
            '/termination',
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
        $company = $this->createCompany(['code' => 'webguard_api_admin']);

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'QA Admin Web',
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $admin = User::query()->where('email', 'qa.login@example.com')->firstOrFail();
        $this->attachUserToCompany($company, $admin);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ]);

        $loginResponse->assertOk()->assertCookie($this->cookieName());
        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token);

        $cookieHeader = $this->cookieName().'='.$token;

        $adminPaths = $this->tenantAdminWebPaths();
        foreach ($adminPaths as $path) {
            $response = $this->withHeader('Cookie', $cookieHeader)
                ->withHeader('X-Company-Code', $company->code)
                ->followingRedirects()
                ->get($path);

            $response->assertOk("HCM admin + cookie API harus 200 setelah redirect normal: {$path}");
        }
    }

    public function test_hcm_admin_api_cookie_can_open_payslip_report(): void
    {
        $company = $this->createCompany(['code' => 'webguard_payslip_admin']);

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'QA Payslip Admin',
            'email' => 'qa.payslip@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $admin = User::query()->where('email', 'qa.payslip@example.com')->firstOrFail();
        $this->attachUserToCompany($company, $admin);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.payslip@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $company->code,
        ]);

        $loginResponse->assertOk()->assertCookie($this->cookieName());
        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token);

        $this->withHeader('Cookie', $this->cookieName().'='.$token)
            ->withHeader('X-Company-Code', $company->code)
            ->followingRedirects()
            ->get('/payslip-report')
            ->assertOk();

        $this->withHeader('Cookie', $this->cookieName().'='.$token)
            ->withHeader('X-Company-Code', $company->code)
            ->followingRedirects()
            ->get('/monthly-report')
            ->assertOk();
    }

    public function test_non_hcm_admin_api_cookie_redirected_from_promotion_resignation_termination(): void
    {
        $company = $this->createCompany(['code' => 'webguard_api_member']);

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Web Guard Employee',
            'email' => 'webguard-employee@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $employee = User::query()->where('email', 'webguard-employee@example.com')->firstOrFail();
        $this->attachUserToCompany($company, $employee, 'employee', 'Staff');

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'webguard-employee@example.com',
            'password' => 'StrongPass1',
        ]);

        $loginResponse->assertOk()->assertCookie($this->cookieName());
        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token);

        $cookieHeader = $this->cookieName().'='.$token;

        $adminPaths = $this->tenantAdminWebPaths();
        foreach ($adminPaths as $path) {
            $this->withHeader('Cookie', $cookieHeader)
                ->withHeader('X-Company-Code', $company->code)
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
        $company = $this->createCompany(['code' => 'webguard_session_admin']);

        $admin = User::factory()->create([
            'email' => 'qa.login@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->attachUserToCompany($company, $admin);

        $this->actingAs($admin);
        $adminPaths = $this->tenantAdminWebPaths();
        foreach ($adminPaths as $path) {
            $this->followingRedirects()
                ->withHeader('X-Company-Code', $company->code)
                ->get($path)
                ->assertOk("HCM admin + sesi web harus 200 setelah redirect normal: {$path}");
        }
    }

    public function test_hcm_admin_web_session_can_open_team_members_page(): void
    {
        $company = Company::query()->create([
            'code' => 'team_members_guard',
            'name' => 'Team Members Guard Company',
            'legal_name' => 'Team Members Guard Company PT',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $admin = User::factory()->create([
            'email' => 'team-members-admin@example.com',
            'password' => bcrypt('password'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'employment_status' => 'active',
            'designation' => 'HCM Admin',
            'team' => 'HCM',
            'nik' => 'EMP-TEAM-GUARD',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/teams/123/members')
            ->assertOk();
    }

    public function test_non_hcm_admin_web_session_redirected_from_promotion_resignation_termination(): void
    {
        $company = $this->createCompany(['code' => 'webguard_session_member']);

        $user = User::factory()->create([
            'email' => 'sessiononly-employee@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->attachUserToCompany($company, $user, 'employee', 'Staff');

        $this->actingAs($user);
        $adminPaths = $this->tenantAdminWebPaths();
        foreach ($adminPaths as $path) {
            $this->withHeader('X-Company-Code', $company->code)
                ->get($path)
                ->assertRedirect(url('employee-dashboard'));
        }
    }

    public function test_admin_only_redirect_sets_error_flash_message(): void
    {
        $user = User::factory()->create([
            'email' => 'guard-flash-employee@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user)
            ->get('/roles-permissions')
            ->assertRedirect(url('employee-dashboard'))
            ->assertSessionHas('error');
    }

    public function test_legacy_employee_shortcuts_redirect_to_employee_pages_for_non_admin_users(): void
    {
        $user = User::factory()->create([
            'email' => 'legacy-shortcut-employee@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user)->get('/tickets')->assertRedirect('/tickets-employee');
        $this->actingAs($user)->get('/ticket-details')->assertRedirect('/tickets-employee');
        $this->actingAs($user)->get('/leave-request')->assertRedirect('/leaves-employee');
        $this->actingAs($user)->get('/overtime-request')->assertRedirect('/overtime-employee');
        $this->actingAs($user)->get('/schedules')->assertRedirect('/attendance-employee');
    }

    public function test_legacy_employee_shortcuts_redirect_to_admin_pages_for_hcm_admin(): void
    {
        $company = Company::query()->create([
            'code' => 'legacy_shortcut_admin',
            'name' => 'Legacy Shortcut Admin Co',
            'legal_name' => 'Legacy Shortcut Admin Co PT',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $admin = User::factory()->create([
            'email' => 'legacy-shortcut-admin@example.com',
            'password' => bcrypt('password'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'employment_status' => 'active',
            'designation' => 'HCM Admin',
            'team' => 'HCM',
            'nik' => 'EMP-LEGACY-SHORTCUT-ADM',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/tickets')
            ->assertRedirect('/tickets-admin');

        $this->actingAs($admin)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/ticket-details')
            ->assertRedirect('/tickets-admin');

        $this->actingAs($admin)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/leave-request')
            ->assertRedirect('/leaves');

        $this->actingAs($admin)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/overtime-request')
            ->assertRedirect('/overtime');

        $this->actingAs($admin)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/schedules')
            ->assertRedirect('/schedule-timing');
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
            '/saas/renewal-monitoring',
            '/saas/transactions',
            '/companies',
            '/packages',
            '/domain',
            '/purchase-transaction',
            '/cronjob-schedule',
            '/business-settings',
            '/bussiness-settings',
            '/seo-settings',
            '/localization-settings',
            '/language',
            '/language-web',
            '/add-language',
            '/authentication-settings',
            '/ai-settings',
            '/saas/pricing',
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

    public function test_primary_global_admin_can_open_renewal_monitoring_page(): void
    {
        config(['hcm.admin_email' => 'qa.login@example.com']);

        $company = Company::query()->create([
            'name' => 'Renewal Monitoring Guard Co',
            'code' => 'RMG1',
            'email' => 'rmg1@example.com',
            'phone' => '0219991001',
            'address' => 'Jakarta',
            'city' => 'Jakarta',
            'state' => 'DKI',
            'country' => 'ID',
            'postal_code' => '10110',
            'status' => 'active',
        ]);

        $primary = User::query()->create([
            'name' => 'Primary Admin',
            'email' => 'qa.login@example.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $primary->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()->subDay(),
        ]);

        $this->actingAs($primary)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/saas/renewal-monitoring')
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
            // Template showcase pages are still behind auth (not public) and can render for authenticated users.
            '/ui-buttons',
        ];

        foreach ($paths as $path) {
            $this->withHeader('Cookie', $cookieHeader)
                ->get($path)
                ->assertOk("Dengan cookie API harus 200: {$path}");
        }
    }

    public function test_pages_blogs_testimonials_and_activity_only_primary_super_admin_code_one_can_access(): void
    {
        config(['hcm.admin_email' => 'qa.login@example.com']);

        $company = Company::query()->create([
            'name' => 'Route Guard Co',
            'code' => 'RG1',
            'email' => 'rg1@example.com',
            'phone' => '0219990001',
            'address' => 'Jakarta',
            'city' => 'Jakarta',
            'state' => 'DKI',
            'country' => 'ID',
            'postal_code' => '10110',
            'status' => 'active',
        ]);

        $primary = User::query()->create([
            'name' => 'Primary Admin',
            'email' => 'qa.login@example.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $primary->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()->subDay(),
        ]);

        $secondary = User::query()->create([
            'name' => 'Secondary Admin',
            'email' => 'secondary.guard@example.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $secondary->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()->subDay(),
        ]);

        foreach (['/pages', '/blogs', '/testimonials', '/activity'] as $path) {
            $this->actingAs($primary)
                ->withHeader('X-Company-Code', $company->code)
                ->get($path)
                ->assertOk();

            $this->actingAs($secondary)
                ->withHeader('X-Company-Code', $company->code)
                ->get($path)
                ->assertRedirect(url('employee-dashboard'));
        }
    }
}
