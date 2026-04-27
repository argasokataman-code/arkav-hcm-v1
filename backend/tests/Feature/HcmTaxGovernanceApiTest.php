<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmTaxGovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function employeeTokenForCompany(Company $company, string $email): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Tax Employee',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        CompanyUser::query()->firstOrCreate(
            ['user_id' => $user->id, 'company_id' => $company->id],
            ['role' => 'member', 'status' => 'active']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
            'companyCode' => $company->code,
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_policy_lifecycle_enforces_sod_and_tenant_boundary(): void
    {
        $maker = $this->createHcmAdminWithCompany(['email' => 'tax-maker@example.com']);
        $approver = $this->createHcmAdminWithCompany(['email' => 'tax-approver@example.com'], $maker['company']);
        $otherTenant = $this->createHcmAdminWithCompany(['email' => 'tax-other-tenant@example.com']);

        $payload = [
            'policyCode' => 'PPh21-TER-2026',
            'name' => 'PPh 21 TER 2026',
            'effectiveStartDate' => '2026-01-01',
            'effectiveEndDate' => null,
            'rules' => [
                'scheme' => 'TER',
                'currency' => 'IDR',
            ],
            'rateSchedules' => [
                ['bracket' => 'A', 'rate' => 5],
            ],
        ];

        $create = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$maker['token'],
        ], $maker['company_id']))->postJson('/v1/hcm/tax-governance/policies', $payload)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version', 1);

        $policyUuid = (string) $create->json('data.uuid');
        $this->assertNotSame('', $policyUuid);

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$maker['token'],
        ], $maker['company_id']))->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/submit', [
            'submissionNote' => 'Ready for reviewer approval',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'submitted');

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$maker['token'],
        ], $maker['company_id']))->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/approve', [
            'approvalNote' => 'Maker should not approve',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TAX_POLICY_SOD_VIOLATION');

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$approver['token'],
        ], $maker['company_id']))->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/approve', [
            'approvalNote' => 'Approved by different approver',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$maker['token'],
        ], $maker['company_id']))->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/publish', [
            'publishReason' => 'Maker should not publish own policy',
            'effectiveStartDate' => '2026-01-15',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TAX_POLICY_SOD_VIOLATION');

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$approver['token'],
        ], $maker['company_id']))->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/publish', [
            'publishReason' => 'Approved and now published',
            'effectiveStartDate' => '2026-01-15',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published');

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$otherTenant['token'],
        ], $otherTenant['company_id']))->getJson('/v1/hcm/tax-governance/policies/'.$policyUuid)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'TAX_POLICY_NOT_FOUND');
    }

    public function test_index_requires_permission_for_tax_policy_view(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-admin-index@example.com']);
        $employeeToken = $this->employeeTokenForCompany($admin['company'], 'tax-employee@example.com');

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$employeeToken,
        ], $admin['company_id']))->getJson('/v1/hcm/tax-governance/policies')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_reject_requires_note_and_returns_policy_to_draft(): void
    {
        $maker = $this->createHcmAdminWithCompany(['email' => 'tax-maker-reject@example.com']);
        $approver = $this->createHcmAdminWithCompany(['email' => 'tax-approver-reject@example.com'], $maker['company']);

        $create = $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$maker['token'],
        ], $maker['company_id']))->postJson('/v1/hcm/tax-governance/policies', [
            'policyCode' => 'PPh21-TER-REJECT',
            'name' => 'PPh 21 TER Reject Flow',
            'effectiveStartDate' => '2026-02-01',
            'effectiveEndDate' => null,
            'rules' => [
                'scheme' => 'TER',
                'currency' => 'IDR',
            ],
            'rateSchedules' => [
                ['bracket' => 'A', 'rate' => 6],
            ],
        ])->assertStatus(201);

        $policyUuid = (string) $create->json('data.uuid');

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$maker['token'],
        ], $maker['company_id']))->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/submit', [
            'submissionNote' => 'Submit for reject test',
        ])->assertOk()->assertJsonPath('data.status', 'submitted');

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$approver['token'],
        ], $maker['company_id']))->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/reject', [])
            ->assertStatus(422);

        $this->withHeaders($this->withCompanyContext([
            'Authorization' => 'Bearer '.$approver['token'],
        ], $maker['company_id']))->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/reject', [
            'rejectionNote' => 'Rate schedule must be revised before approval',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');
    }
}
