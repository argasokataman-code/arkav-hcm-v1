<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminCompanyUserIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function loginAndGetToken(string $email, string $name): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertSuccessful();

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertSuccessful();

        return (string) $login->json('data.accessToken');
    }

    public function test_company_owner_membership_does_not_grant_super_admin_dashboard_access(): void
    {
        $superAdminToken = $this->loginAndGetToken('qa.login@example.com', 'QA Super Admin');
        $companyUserToken = $this->loginAndGetToken('owner.company@example.com', 'Company Owner User');

        /** @var User $companyUser */
        $companyUser = User::query()->where('email', 'owner.company@example.com')->firstOrFail();

        $company = Company::query()->create([
            'code' => 'iso_company_01',
            'name' => 'Isolation Company',
            'legal_name' => 'Isolation Company PT',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $companyUser->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $companyUserResponse = $this->withHeader('Authorization', 'Bearer '.$companyUserToken)
            ->getJson('/v1/saas/dashboard/kpi');

        $companyUserResponse->assertStatus(403);
        $companyUserResponse->assertJsonPath('error.code', 'ADMIN_REQUIRED');

        $superAdminResponse = $this->withHeader('Authorization', 'Bearer '.$superAdminToken)
            ->getJson('/v1/saas/dashboard/kpi');

        $superAdminResponse->assertOk();
        $superAdminResponse->assertJsonPath('success', true);
    }
}
