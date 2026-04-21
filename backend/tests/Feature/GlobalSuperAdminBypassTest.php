<?php

namespace Tests\Feature;

use App\Models\AuthToken;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\TenantContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Locks the Global Super Admin (developer / platform maintainer) bypass
 * contract. A user with `users.is_super_admin = 1` must:
 *
 *  - Resolve tenant context for ANY company, even without a `company_users`
 *    membership row (via synthesized virtual membership).
 *  - List employees across ALL companies, not just the active one.
 *  - Never be blocked by subscription feature gates (tickets, asset mgmt, …).
 */
class GlobalSuperAdminBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_resolver_synthesizes_membership_for_global_admin_on_foreign_company(): void
    {
        $globalAdmin = User::factory()->create([
            'email' => 'platform.dev@example.com',
            'is_super_admin' => true,
        ]);

        $foreignCompany = Company::create([
            'code' => 'FOREIGN1',
            'name' => 'Foreign Tenant',
            'slug' => 'foreign-tenant',
        ]);

        $request = Request::create('/api/whatever', 'GET', server: [
            'HTTP_X-Company-Id' => (string) $foreignCompany->id,
        ]);

        $resolver = app(TenantContextResolver::class);
        $result = $resolver->resolve($request, $globalAdmin);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame($foreignCompany->id, $result['company']->id);
        $this->assertNotNull($result['membership']);
        $this->assertSame('super_admin', $result['membership']->role);

        $this->assertDatabaseMissing('company_users', [
            'user_id' => $globalAdmin->id,
            'company_id' => $foreignCompany->id,
        ]);
    }

    public function test_employee_list_returns_employees_from_every_tenant_for_global_admin(): void
    {
        [, $companyA, $token] = $this->seedGlobalAdminWithToken();

        $companyB = Company::create([
            'code' => 'OTHERC1',
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
        ]);

        $employeeA = User::factory()->create(['name' => 'Alice From A']);
        EmployeeProfile::create([
            'user_id' => $employeeA->id,
            'company_id' => $companyA->id,
            'employment_status' => 'active',
        ]);

        $employeeB = User::factory()->create(['name' => 'Bob From B']);
        EmployeeProfile::create([
            'user_id' => $employeeB->id,
            'company_id' => $companyB->id,
            'employment_status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->getJson('/v1/hcm/employees?perPage=50');

        $response->assertOk();
        $ids = collect($response->json('data') ?? [])
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $this->assertContains($employeeA->id, $ids, 'Global admin must see Company A employees.');
        $this->assertContains($employeeB->id, $ids, 'Global admin must see Company B employees across tenants.');
    }

    public function test_ticket_feature_gate_does_not_block_global_admin(): void
    {
        [, $company, $token] = $this->seedGlobalAdminWithToken();

        // Company intentionally has NO subscription, hence no ticket feature.
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/v1/hcm/tickets');

        $response->assertOk();
        $this->assertNotSame('SUBSCRIPTION_REQUIRED', $response->json('error.code'));
    }

    /**
     * @return array{0: User, 1: Company, 2: string}
     */
    private function seedGlobalAdminWithToken(): array
    {
        $company = Company::create([
            'code' => 'HOMEC01',
            'name' => 'Home Tenant',
            'slug' => 'home-tenant',
        ]);

        $admin = User::factory()->create([
            'email' => 'platform.dev@example.com',
            'is_super_admin' => true,
        ]);

        CompanyUser::create([
            'user_id' => $admin->id,
            'company_id' => $company->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $rawToken = bin2hex(random_bytes(32));
        AuthToken::query()->create([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDay(),
        ]);

        return [$admin, $company, $rawToken];
    }
}
