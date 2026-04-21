<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Domain;
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
        Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'app1.example.com',
            'verification_type' => 'dns',
            'status' => 'verified',
        ]);

        Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'app2.example.com',
            'verification_type' => 'dns',
            'status' => 'pending',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/domains');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_show_domain_details()
    {
        $domain = Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'app.example.com',
            'verification_type' => 'dns',
            'status' => 'verified',
        ]);

        $response = $this->adminRequest()->getJson("/v1/saas/domains/{$domain->uuid}");

        $response->assertStatus(200);
        $this->assertEquals('app.example.com', $response->json('data.domainName'));
    }

    public function test_admin_can_create_domain()
    {
        $response = $this->adminRequest()->postJson('/v1/saas/domains', [
            'company_id' => $this->company->uuid,
            'domain_name' => 'newapp.example.com',
            'verification_type' => 'dns',
            'notes' => 'Production domain',
        ]);

        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));
        $this->assertDatabaseHas('domains', [
            'domain_name' => 'newapp.example.com',
        ]);
    }

    public function test_non_admin_cannot_create_domain()
    {
        $response = $this->userRequest()->postJson('/v1/saas/domains', [
            'company_id' => $this->company->uuid,
            'domain_name' => 'newapp.example.com',
            'verification_type' => 'dns',
        ]);

        $response->assertStatus(403);
        $this->assertEquals('ADMIN_REQUIRED', $response->json('error.code'));
    }

    public function test_admin_can_update_domain()
    {
        $domain = Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'app.example.com',
            'status' => 'pending',
            'verification_type' => 'dns',
            'notes' => 'Old note',
        ]);

        $response = $this->adminRequest()->putJson("/v1/saas/domains/{$domain->uuid}", [
            'status' => 'verified',
            'notes' => 'Updated note',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated note', $response->json('data.notes'));
    }

    public function test_admin_can_delete_domain()
    {
        $domain = Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'app.example.com',
            'verification_type' => 'dns',
            'status' => 'verified',
        ]);

        $response = $this->adminRequest()->deleteJson("/v1/saas/domains/{$domain->uuid}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('domains', ['id' => $domain->id]);
    }

    public function test_verify_domain_endpoint()
    {
        $domain = Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'app.example.com',
            'status' => 'pending',
            'verification_type' => 'dns',
        ]);

        $response = $this->adminRequest()->postJson("/v1/saas/domains/{$domain->uuid}/verify");

        $response->assertStatus(200);
        $this->assertDatabaseHas('domains', [
            'id' => $domain->id,
            'status' => 'verified',
        ]);
    }

    public function test_non_admin_cannot_verify_domain()
    {
        $domain = Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'app.example.com',
            'status' => 'pending',
            'verification_type' => 'dns',
        ]);

        $response = $this->userRequest()->postJson("/v1/saas/domains/{$domain->uuid}/verify");

        $response->assertStatus(403);
        $this->assertEquals('ADMIN_REQUIRED', $response->json('error.code'));
    }

    public function test_cannot_create_duplicate_domain()
    {
        Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'app.example.com',
            'verification_type' => 'dns',
            'status' => 'verified',
        ]);

        $response = $this->adminRequest()->postJson('/v1/saas/domains', [
            'company_id' => $this->company->uuid,
            'domain_name' => 'app.example.com',
            'verification_type' => 'dns',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_filter_domains_by_status()
    {
        Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'app1.example.com',
            'verification_type' => 'dns',
            'status' => 'verified',
        ]);

        Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'app2.example.com',
            'verification_type' => 'dns',
            'status' => 'pending',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/domains?status=pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('pending', $response->json('data.0.status'));
    }

    public function test_can_search_domains_by_name()
    {
        Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'staging.example.com',
            'verification_type' => 'dns',
            'status' => 'verified',
        ]);

        Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'prod-app.example.com',
            'verification_type' => 'dns',
            'status' => 'verified',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/domains?search=staging');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('staging', $response->json('data.0.domainName'));
    }

    public function test_can_filter_domains_by_company_id(): void
    {
        $otherCompany = Company::create([
            'code' => 'TEST002',
            'name' => 'Other Company',
            'email' => 'other@company.com',
            'country' => 'US',
            'industry' => 'Technology',
            'currency' => 'USD',
        ]);

        Domain::create([
            'company_id' => $this->company->id,
            'domain_name' => 'tenant-a.example.com',
            'verification_type' => 'dns',
            'status' => 'verified',
        ]);

        Domain::create([
            'company_id' => $otherCompany->id,
            'domain_name' => 'tenant-b.example.com',
            'verification_type' => 'file',
            'status' => 'pending',
        ]);

        $response = $this->adminRequest()->getJson('/v1/saas/domains?company_id='.$otherCompany->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('tenant-b.example.com', $response->json('data.0.domainName'));
    }
}
