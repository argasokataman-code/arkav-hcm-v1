<?php

namespace Tests\Feature;

use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class FaqApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(string $token, int $companyId): array
    {
        return [
            'Authorization' => "Bearer {$token}",
            'X-Company-Id' => $companyId,
        ];
    }

    private function createTenantUserAuth(array $userData = []): array
    {
        $defaults = [
            'name' => 'Tenant Employee',
            'email' => 'tenant-employee-'.time().'@example.com',
            'password' => 'StrongPass1',
        ];
        $data = array_merge($defaults, $userData);

        $company = $this->createIsolatedTestCompany();

        $this->postJson('/v1/identity/auth/register', [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'confirmPassword' => $data['password'],
        ])->assertStatus(201);

        $user = User::query()->where('email', $data['email'])->firstOrFail();

        CompanyUser::query()->firstOrCreate(
            ['user_id' => $user->id, 'company_id' => $company->id],
            ['role' => 'employee', 'status' => 'active']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $data['email'],
            'password' => $data['password'],
            'companyCode' => $company->code,
        ])->assertOk();

        return [
            'token' => (string) $login->json('data.accessToken'),
            'company_id' => $company->id,
            'company' => $company,
            'user' => $user,
        ];
    }

    public function test_hcm_admin_can_crud_faq_and_bulk_delete(): void
    {
        $auth = $this->createHcmAdminWithCompany();
        $headers = $this->headers($auth['token'], $auth['company_id']);

        $created = $this->postJson('/v1/hcm/faqs', [
            'category' => 'General',
            'question' => 'What is FAQ?',
            'answer' => 'Frequently asked question.',
        ], $headers)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.category', 'General')
            ->json('data');

        $this->getJson('/v1/hcm/faqs', $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $this->putJson('/v1/hcm/faqs/'.$created['id'], [
            'question' => 'What is FAQ entry?',
            'answer' => 'Updated answer.',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.question', 'What is FAQ entry?');

        $another = $this->postJson('/v1/hcm/faqs', [
            'category' => 'Payroll',
            'question' => 'How to run payroll?',
            'answer' => 'Use payroll run module.',
        ], $headers)->assertStatus(201)->json('data');

        $third = $this->postJson('/v1/hcm/faqs', [
            'category' => 'Leave',
            'question' => 'How to approve leave?',
            'answer' => 'Use leave approval flow.',
        ], $headers)->assertStatus(201)->json('data');

        $this->postJson('/v1/hcm/faqs/bulk-delete', [
            'ids' => [$another['id'], $third['id']],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deletedCount', 2);

        $this->deleteJson('/v1/hcm/faqs/'.$created['id'], [], $headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/v1/hcm/faqs', $headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_non_admin_user_is_forbidden_to_manage_faq(): void
    {
        $auth = $this->createTenantUserAuth();
        $headers = $this->headers($auth['token'], $auth['company_id']);

        $this->getJson('/v1/hcm/faqs', $headers)
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->postJson('/v1/hcm/faqs', [
            'category' => 'General',
            'question' => 'Q',
            'answer' => 'A',
        ], $headers)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_admin_cannot_delete_other_tenant_faq(): void
    {
        $authA = $this->createHcmAdminWithCompany(['email' => 'faq-admin-a-'.time().'@example.com']);
        $headersA = $this->headers($authA['token'], $authA['company_id']);

        $created = $this->postJson('/v1/hcm/faqs', [
            'category' => 'General',
            'question' => 'Tenant A question',
            'answer' => 'Tenant A answer',
        ], $headersA)->assertStatus(201)->json('data');

        $authB = $this->createHcmAdminWithCompany(['email' => 'faq-admin-b-'.time().'@example.com']);
        $headersB = $this->headers($authB['token'], $authB['company_id']);

        $this->deleteJson('/v1/hcm/faqs/'.$created['id'], [], $headersB)
            ->assertNotFound();
    }
}
