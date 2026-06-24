<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Admin User',
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $adminLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $this->adminToken = (string) $adminLogin->json('data.accessToken');
    }

    private function adminRequest()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->adminToken);
    }

    public function test_list_domains_requires_admin()
    {
        $response = $this->getJson('/v1/saas/domains');
        $response->assertStatus(401);
    }

    public function test_list_domains_as_admin()
    {
        Domain::factory()->count(3)->for($this->company)->create();

        $response = $this->adminRequest()->getJson('/v1/saas/domains');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['*' => ['id', 'domainName', 'status', 'verificationType']],
                'pagination',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_create_domain_requires_admin()
    {
        $response = $this->postJson('/v1/saas/domains', [
            'domain_name' => 'example.com',
            'company_id' => $this->company->id,
            'verification_type' => 'dns',
        ]);
        $response->assertStatus(401);
    }

    public function test_create_domain_as_admin()
    {
        $response = $this->adminRequest()->postJson('/v1/saas/domains', [
            'domain_name' => 'example.com',
            'company_id' => $this->company->uuid,
            'verification_type' => 'dns',
            'notes' => 'Test domain',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['success', 'data' => ['id', 'domainName', 'status', 'verificationType', 'verificationToken']])
            ->assertJsonFragment(['domainName' => 'example.com', 'status' => 'pending']);

        $this->assertDatabaseHas('domains', ['domain_name' => 'example.com']);
    }

    public function test_update_domain_as_admin()
    {
        $domain = Domain::factory()->for($this->company)->create(['status' => 'pending']);

        $response = $this->adminRequest()->putJson('/v1/saas/domains/'.$domain->uuid, [
            'status' => 'verified',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'verified']);

        $domain->refresh();
        $this->assertEquals('verified', $domain->status);
    }

    public function test_delete_domain_as_admin()
    {
        $domain = Domain::factory()->for($this->company)->create();

        $response = $this->adminRequest()->deleteJson('/v1/saas/domains/'.$domain->uuid);

        $response->assertOk();
        $this->assertDatabaseMissing('domains', ['id' => $domain->id]);
    }

    public function test_verify_domain()
    {
        $domain = Domain::factory()->for($this->company)->create(['status' => 'pending']);

        $response = $this->adminRequest()->postJson('/v1/saas/domains/'.$domain->uuid.'/verify');

        $response->assertOk()
            ->assertJsonFragment(['status' => 'verified']);

        $domain->refresh();
        $this->assertEquals('verified', $domain->status);
        $this->assertNotNull($domain->verified_at);
    }

    public function test_get_verification_details()
    {
        $domain = Domain::factory()->for($this->company)->create(['verification_type' => 'dns']);

        $response = $this->adminRequest()->getJson('/v1/saas/domains/'.$domain->uuid.'/verification-details');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['domainName', 'verificationType', 'instructions', 'token']])
            ->assertJsonFragment(['verificationType' => 'dns']);
    }

    public function test_create_domain_normalizes_host_and_rejects_invalid_domain_format(): void
    {
        $this->adminRequest()->postJson('/v1/saas/domains', [
            'domain_name' => '  HR-Demo.Example.Com ',
            'company_id' => $this->company->uuid,
            'verification_type' => 'dns',
            'notes' => '  Production domain  ',
        ])->assertCreated()
            ->assertJsonPath('data.domainName', 'hr-demo.example.com');

        $this->assertDatabaseHas('domains', [
            'domain_name' => 'hr-demo.example.com',
            'notes' => 'Production domain',
        ]);

        $this->adminRequest()->postJson('/v1/saas/domains', [
            'domain_name' => 'https://bad.example.com/path',
            'company_id' => $this->company->uuid,
            'verification_type' => 'dns',
        ])->assertStatus(422);
    }
}
