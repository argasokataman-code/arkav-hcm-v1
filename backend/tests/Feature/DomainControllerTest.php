<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['email' => 'qa.login@example.com']);
        $this->company = Company::factory()->create();
    }

    public function test_list_domains_requires_admin()
    {
        $response = $this->getJson('/v1/saas/domains');
        $response->assertStatus(401);
    }

    public function test_list_domains_as_admin()
    {
        Domain::factory()->count(3)->for($this->company)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/v1/saas/domains');

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
        $response = $this->actingAs($this->admin)
            ->postJson('/v1/saas/domains', [
                'domain_name' => 'example.com',
                'company_id' => $this->company->id,
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

        $response = $this->actingAs($this->admin)
            ->putJson('/v1/saas/domains/' . $domain->id, [
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

        $response = $this->actingAs($this->admin)
            ->deleteJson('/v1/saas/domains/' . $domain->id);

        $response->assertOk();
        $this->assertDatabaseMissing('domains', ['id' => $domain->id]);
    }

    public function test_verify_domain()
    {
        $domain = Domain::factory()->for($this->company)->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin)
            ->postJson('/v1/saas/domains/' . $domain->id . '/verify');

        $response->assertOk()
            ->assertJsonFragment(['status' => 'verified']);

        $domain->refresh();
        $this->assertEquals('verified', $domain->status);
        $this->assertNotNull($domain->verified_at);
    }

    public function test_get_verification_details()
    {
        $domain = Domain::factory()->for($this->company)->create(['verification_type' => 'dns']);

        $response = $this->actingAs($this->admin)
            ->getJson('/v1/saas/domains/' . $domain->id . '/verification-details');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['domainName', 'verificationType', 'instructions', 'token']])
            ->assertJsonFragment(['verificationType' => 'dns']);
    }
}
