<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CustomDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomDomainServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private string $userToken;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'code' => 'TEST001',
            'name' => 'Test Company',
            'email' => 'test@company.com',
            'country' => 'US',
            'industry' => 'Technology',
            'currency' => 'USD',
        ]);

        // Register and login admin user
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Admin User',
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $adminLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ]);

        $this->adminToken = $adminLogin->json('data.accessToken');

        // Register and login regular user
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $userLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass1',
        ]);

        $this->userToken = $userLogin->json('data.accessToken');
    }

    private function adminRequest()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminToken);
    }

    private function userRequest()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->userToken);
    }

    public function test_can_list_domains_with_pagination()
    {
        CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'app1.example.com',
            'status' => 'verified',
        ]);

        CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'app2.example.com',
            'status' => 'pending',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/domains');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_show_domain_details()
    {
        $domain = CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'app.example.com',
            'status' => 'verified',
        ]);

        $response = $this->adminRequest()->getJson("/v1/saas/domains/{$domain->id}");

        $response->assertStatus(200);
        $this->assertEquals('app.example.com', $response->json('data.domain'));
    }

    public function test_admin_can_create_domain()
    {
        $response = $this->adminRequest()->postJson('/v1/saas/domains', [
            'company_id' => $this->company->id,
            'domain' => 'newapp.example.com',
            'verification_method' => 'dns',
            'notes' => 'Production domain',
        ]);

        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));
        $this->assertDatabaseHas('custom_domains', [
            'domain' => 'newapp.example.com',
        ]);
    }

    public function test_non_admin_cannot_create_domain()
    {
        $response = $this->userRequest()->postJson('/v1/saas/domains', [
            'company_id' => $this->company->id,
            'domain' => 'newapp.example.com',
            'verification_method' => 'dns',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('ADMIN_REQUIRED', $response->json('error.code'));
    }

    public function test_admin_can_update_domain()
    {
        $domain = CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'app.example.com',
            'status' => 'pending',
            'verification_method' => 'dns',
            'notes' => 'Old note',
        ]);

        $response = $this->adminRequest()->putJson("/v1/saas/domains/{$domain->id}", [
            'status' => 'verified',
            'notes' => 'Updated note',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated note', $response->json('data.notes'));
    }

    public function test_admin_can_delete_domain()
    {
        $domain = CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'app.example.com',
            'status' => 'verified',
        ]);

        $response = $this->adminRequest()->deleteJson("/v1/saas/domains/{$domain->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('custom_domains', ['id' => $domain->id]);
    }

    public function test_verify_domain_endpoint()
    {
        $domain = CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'app.example.com',
            'status' => 'pending',
            'verification_method' => 'dns',
        ]);

        $response = $this->adminRequest()->postJson("/v1/saas/domains/{$domain->id}/verify");

        $response->assertStatus(200);
        $this->assertDatabaseHas('domain_verification_logs', [
            'domain_id' => $domain->id,
        ]);
    }

    public function test_non_admin_cannot_verify_domain()
    {
        $domain = CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'app.example.com',
            'status' => 'pending',
            'verification_method' => 'dns',
        ]);

        $response = $this->userRequest()->postJson("/v1/saas/domains/{$domain->id}/verify");

        $response->assertStatus(403);
        $this->assertEquals('ADMIN_REQUIRED', $response->json('error.code'));
    }

    public function test_cannot_create_duplicate_domain()
    {
        CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'app.example.com',
            'status' => 'verified',
        ]);

        $response = $this->adminRequest()->postJson('/v1/saas/domains', [
            'company_id' => $this->company->id,
            'domain' => 'app.example.com',
            'verification_method' => 'dns',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_filter_domains_by_status()
    {
        CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'app1.example.com',
            'status' => 'verified',
        ]);

        CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'app2.example.com',
            'status' => 'pending',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/domains?status=pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('pending', $response->json('data.0.status'));
    }

    public function test_can_search_domains_by_name()
    {
        CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'staging.example.com',
            'status' => 'verified',
        ]);

        CustomDomain::create([
            'company_id' => $this->company->id,
            'domain' => 'prod-app.example.com',
            'status' => 'verified',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/domains?domain=staging');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('staging', $response->json('data.0.domain'));
    }
}
