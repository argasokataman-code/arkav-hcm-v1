<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $email = 'qa.login@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'QA Admin',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $login->assertOk();
        return (string) $login->json('data.accessToken');
    }

    private function employeeToken(): string
    {
        $email = 'employee@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Regular Employee',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $login->assertOk();
        return (string) $login->json('data.accessToken');
    }

    private function activeCompanyIdFor(string $email): int
    {
        $user = User::query()->where('email', $email)->firstOrFail();

        $companyId = CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('company_id');

        $this->assertNotNull($companyId);

        return (int) $companyId;
    }

    public function test_revenue_report_requires_admin(): void
    {
        $token = $this->employeeToken();
        $response = $this->getJson('/v1/saas/reports/revenue', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertEquals(403, $response->status());
        $this->assertEquals('AUTH_FORBIDDEN', $response->json('error.code'));
    }

    public function test_admin_can_access_revenue_report(): void
    {
        $token = $this->adminToken();
        $response = $this->getJson('/v1/saas/reports/revenue', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertIsArray($response->json('data'));
        $this->assertArrayHasKey('period', $response->json('data'));
        $this->assertArrayHasKey('totalRevenue', $response->json('data'));
    }

    public function test_aging_report_requires_admin(): void
    {
        $token = $this->employeeToken();
        $response = $this->getJson('/v1/saas/reports/aging', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertEquals(403, $response->status());
        $this->assertEquals('AUTH_FORBIDDEN', $response->json('error.code'));
    }

    public function test_admin_can_access_aging_report(): void
    {
        $token = $this->adminToken();
        $response = $this->getJson('/v1/saas/reports/aging', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertIsArray($response->json('data'));
        $this->assertArrayHasKey('totalOverdue', $response->json('data'));
        $this->assertArrayHasKey('buckets', $response->json('data'));
    }

    public function test_churn_report_requires_admin(): void
    {
        $token = $this->employeeToken();
        $response = $this->getJson('/v1/saas/reports/churn', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertEquals(403, $response->status());
        $this->assertEquals('AUTH_FORBIDDEN', $response->json('error.code'));
    }

    public function test_admin_can_access_churn_report(): void
    {
        $token = $this->adminToken();
        $response = $this->getJson('/v1/saas/reports/churn', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertIsArray($response->json('data'));
        $this->assertArrayHasKey('activeSubscriptions', $response->json('data'));
        $this->assertArrayHasKey('churnRate', $response->json('data'));
    }

    public function test_unauthenticated_cannot_access_reports(): void
    {
        $response = $this->getJson('/v1/saas/reports/revenue');
        $this->assertEquals(401, $response->status());
    }

    public function test_revenue_report_scopes_to_active_tenant_header_when_present(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('qa.login@example.com');
        $otherCompanyId = Company::query()->create([
            'code' => 'REPORT-OTHER-01',
            'name' => 'Report Other Company',
            'legal_name' => 'Report Other Company PT',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ])->id;

        Payment::query()->create([
            'company_id' => $companyId,
            'amount' => 100000,
            'currency' => 'IDR',
            'status' => 'completed',
            'payment_method' => 'bank_transfer',
            'gateway' => 'manual',
            'paid_at' => now(),
            'verified_at' => now(),
        ]);

        Payment::query()->create([
            'company_id' => $otherCompanyId,
            'amount' => 500000,
            'currency' => 'IDR',
            'status' => 'completed',
            'payment_method' => 'bank_transfer',
            'gateway' => 'manual',
            'paid_at' => now(),
            'verified_at' => now(),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->getJson('/v1/saas/reports/revenue?period=monthly')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totalRevenue', 100000);
    }

    public function test_revenue_report_rejects_company_override_that_mismatches_active_tenant_header(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('qa.login@example.com');
        $otherCompanyId = $companyId + 999;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->getJson('/v1/saas/reports/revenue?company_id='.$otherCompanyId)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TENANT_SCOPE_MISMATCH')
            ->assertJsonPath('error.message', 'The requested company does not match the active tenant context.');
    }
}
