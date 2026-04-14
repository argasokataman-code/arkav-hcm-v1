<?php

namespace Tests\Feature;

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

    public function test_revenue_report_requires_admin(): void
    {
        $token = $this->employeeToken();
        $response = $this->getJson('/v1/saas/reports/revenue', [
            'Authorization' => 'Bearer ' . $token
        ]);

        $this->assertEquals(403, $response->status());
        $this->assertEquals('ADMIN_REQUIRED', $response->json('error.code'));
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
        $this->assertEquals('ADMIN_REQUIRED', $response->json('error.code'));
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
        $this->assertEquals('ADMIN_REQUIRED', $response->json('error.code'));
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
}
