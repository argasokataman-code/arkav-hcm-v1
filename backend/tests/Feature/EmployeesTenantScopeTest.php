<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class EmployeesTenantScopeTest extends TestCase
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

    public function test_employee_list_is_scoped_to_active_company(): void
    {
        // Trial-like owner login
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $owner = User::query()->where('email', 'owner@example.com')->first();
        $this->assertNotNull($owner);

        $companyA = Company::query()->create([
            'name' => 'Company A',
            'code' => 'company_a',
            'status' => 'active',
        ]);
        $companyB = Company::query()->create([
            'name' => 'Company B',
            'code' => 'company_b',
            'status' => 'active',
        ]);

        CompanyUser::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $employeeA = User::query()->create([
            'name' => 'Employee A',
            'email' => 'employee.a@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        EmployeeProfile::query()->create([
            'user_id' => $employeeA->id,
            'company_id' => $companyA->id,
            'employment_status' => 'active',
        ]);

        $employeeB = User::query()->create([
            'name' => 'Employee B',
            'email' => 'employee.b@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        EmployeeProfile::query()->create([
            'user_id' => $employeeB->id,
            'company_id' => $companyB->id,
            'employment_status' => 'active',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $companyA->code,
        ])->assertOk()->assertCookie($this->cookieName());

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token);

        $cookieHeader = $this->cookieName().'='.$token;

        $res = $this->withHeader('Cookie', $cookieHeader)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->getJson('/v1/hcm/employees?perPage=50&page=1');

        $res->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($employeeA->id, $ids);
        $this->assertNotContains($employeeB->id, $ids);
    }

    public function test_global_admin_can_switch_employee_scope_with_scope_query(): void
    {
        $globalAdmin = User::query()->create([
            'name' => 'Global Admin',
            'email' => 'global.admin@example.com',
            'password' => bcrypt('StrongPass1'),
            'is_super_admin' => true,
        ]);

        $companyA = Company::query()->create([
            'name' => 'Company A',
            'code' => 'company_a',
            'status' => 'active',
        ]);
        $companyB = Company::query()->create([
            'name' => 'Company B',
            'code' => 'company_b',
            'status' => 'active',
        ]);

        CompanyUser::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $globalAdmin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $employeeA = User::query()->create([
            'name' => 'Employee A',
            'email' => 'employee.a.scope@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        EmployeeProfile::query()->create([
            'user_id' => $employeeA->id,
            'company_id' => $companyA->id,
            'employment_status' => 'active',
        ]);

        $employeeB = User::query()->create([
            'name' => 'Employee B',
            'email' => 'employee.b.scope@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        EmployeeProfile::query()->create([
            'user_id' => $employeeB->id,
            'company_id' => $companyB->id,
            'employment_status' => 'active',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'global.admin@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $companyA->code,
        ])->assertOk()->assertCookie($this->cookieName());

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token);
        $cookieHeader = $this->cookieName().'='.$token;

        $globalRes = $this->withHeader('Cookie', $cookieHeader)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->getJson('/v1/hcm/employees?perPage=50&page=1&scope=global');

        $globalRes->assertOk()->assertJsonPath('success', true);
        $globalIds = collect($globalRes->json('data'))->pluck('id')->all();
        $this->assertContains($employeeA->id, $globalIds);
        $this->assertContains($employeeB->id, $globalIds);

        $activeCompanyRes = $this->withHeader('Cookie', $cookieHeader)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->getJson('/v1/hcm/employees?perPage=50&page=1&scope=active_company');

        $activeCompanyRes->assertOk()->assertJsonPath('success', true);
        $activeCompanyIds = collect($activeCompanyRes->json('data'))->pluck('id')->all();
        $this->assertContains($employeeA->id, $activeCompanyIds);
        $this->assertNotContains($employeeB->id, $activeCompanyIds);
    }
}

