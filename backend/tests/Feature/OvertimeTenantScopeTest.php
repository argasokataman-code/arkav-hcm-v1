<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class OvertimeTenantScopeTest extends TestCase
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

    public function test_overtime_list_is_scoped_to_active_company_for_admin(): void
    {
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

        $adminEmail = (string) config('hcm.admin_email', 'qa.login@example.com');
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'QA Admin',
            'email' => $adminEmail,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $admin = User::query()->where('email', $adminEmail)->first();
        $this->assertNotNull($admin);

        CompanyUser::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $admin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $admin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $userA = User::query()->create([
            'name' => 'Employee A',
            'email' => 'employee.a@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        EmployeeProfile::query()->create([
            'user_id' => $userA->id,
            'company_id' => $companyA->id,
            'employment_status' => 'active',
        ]);

        $userB = User::query()->create([
            'name' => 'Employee B',
            'email' => 'employee.b@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        EmployeeProfile::query()->create([
            'user_id' => $userB->id,
            'company_id' => $companyB->id,
            'employment_status' => 'active',
        ]);

        $otA = OvertimeRequest::query()->create([
            'user_id' => $userA->id,
            'work_date' => now()->toDateString(),
            'minutes' => 60,
            'status' => 'pending',
        ]);
        $otB = OvertimeRequest::query()->create([
            'user_id' => $userB->id,
            'work_date' => now()->toDateString(),
            'minutes' => 90,
            'status' => 'pending',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => $adminEmail,
            'password' => 'StrongPass1',
        ])->assertOk()->assertCookie($this->cookieName());

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token);
        $cookieHeader = $this->cookieName().'='.$token;

        $resA = $this->withHeader('Cookie', $cookieHeader)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->getJson('/v1/hcm/overtime-requests?perPage=50');

        $resA->assertOk()->assertJsonPath('success', true);
        $idsA = collect($resA->json('data'))->pluck('id')->all();
        $this->assertContains($otA->id, $idsA);
        $this->assertNotContains($otB->id, $idsA);

        $resB = $this->withHeader('Cookie', $cookieHeader)
            ->withHeader('X-Company-Id', (string) $companyB->id)
            ->getJson('/v1/hcm/overtime-requests?perPage=50');

        $resB->assertOk()->assertJsonPath('success', true);
        $idsB = collect($resB->json('data'))->pluck('id')->all();
        $this->assertContains($otB->id, $idsB);
        $this->assertNotContains($otA->id, $idsB);
    }
}

