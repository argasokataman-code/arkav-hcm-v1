<?php

namespace Tests\Feature;

use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmTaxGovernanceBreakGlassRequest;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmTaxGovernanceWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private function taxHeaders(array $admin): array
    {
        return $this->withCompanyContext([
            'Authorization' => 'Bearer '.$admin['token'],
        ], $admin['company_id']);
    }

    private function elevateToGlobalAdmin(string $email): void
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $user->is_super_admin = true;
        $user->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function policyPayload(): array
    {
        return [
            'policyCode' => 'PPH21-WORKFLOW-001',
            'name' => 'Workflow Policy Tenant',
            'draftKey' => 'workflow-policy-tenant-001',
            'effectiveStartDate' => '2026-05-01',
            'effectiveEndDate' => null,
            'rules' => [
                'scheme' => 'STATUTORY_PPH21',
                'currency' => 'IDR',
                'regulationReference' => 'PP 58/2023 & PMK 168/PMK.03/2023',
                'regulationSourceType' => 'ministry_regulation',
                'calculationMethod' => 'monthly_ter_lookup',
            ],
            'rateSchedules' => [
                [
                    'category' => 'A',
                    'lookupTableCode' => 'A',
                    'calculationMode' => 'ter_lookup',
                    'effectiveStartDate' => '2026-05-01',
                    'effectiveEndDate' => null,
                    'regulationReference' => 'PP 58/2023 & PMK 168/PMK.03/2023',
                    'regulationSourceType' => 'ministry_regulation',
                ],
            ],
        ];
    }

    public function test_policy_workflow_endpoints_transition_statuses_and_reject_route_is_active(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-workflow-owner@example.com']);
        $this->elevateToGlobalAdmin('tax-workflow-owner@example.com');

        $create = $this->withHeaders($this->taxHeaders($admin))
            ->postJson('/v1/hcm/tax-governance/policies', $this->policyPayload())
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $policyUuid = (string) $create->json('data.uuid');
        $policyId = (int) HcmTaxGovernancePolicy::query()->where('uuid', $policyUuid)->value('id');

        $this->withHeaders($this->taxHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/policies/'.$policyId)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'TAX_POLICY_NOT_FOUND');

        $this->withHeaders($this->taxHeaders($admin))
            ->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/submit', [
                'submissionNote' => 'Submit for compliance review',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'submitted');

        $this->withHeaders($this->taxHeaders($admin))
            ->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/approve', [
                'approvalNote' => 'Approved by tenant owner',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $this->withHeaders($this->taxHeaders($admin))
            ->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/reject', [
                'rejectionNote' => 'Need rule update',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        $this->withHeaders($this->taxHeaders($admin))
            ->postJson('/v1/hcm/tax-governance/policies/'.$policyUuid.'/publish', [
                'publishReason' => 'Direct publish after owner review',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published');
    }

    public function test_global_dashboard_route_is_active(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-governance-dashboard@example.com']);
        $this->elevateToGlobalAdmin('tax-governance-dashboard@example.com');

        $this->withHeaders($this->taxHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/governance/dashboard?per_page=10')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary',
                    'risk_heatmap',
                    'billing_tax_health',
                    'tenants',
                ],
            ]);
    }

    public function test_break_glass_request_and_approval_endpoints_are_active(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-break-glass@example.com']);
        $this->elevateToGlobalAdmin('tax-break-glass@example.com');

        $request = $this->withHeaders($this->taxHeaders($admin))
            ->postJson('/v1/hcm/tax-governance/governance/break-glass/requests', [
                'targetTenantUuid' => $admin['company']->uuid,
                'reasonCode' => 'emergency_audit',
                'reason' => 'Need temporary elevated visibility for cross-tenant compliance incident.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'requested');

        $requestUuid = (string) $request->json('data.requestUuid');

        $this->withHeaders($this->taxHeaders($admin))
            ->postJson('/v1/hcm/tax-governance/governance/break-glass/requests/'.$requestUuid.'/approve', [
                'approvalNote' => 'Approved for 24-hour audit window.',
                'expiresAt' => now()->addDay()->toIso8601String(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', HcmTaxGovernanceBreakGlassRequest::query()->where('uuid', $requestUuid)->value('status'));
    }

    public function test_tenant_compliance_status_auto_derives_ptkp_from_marital_status_when_tax_profile_is_missing(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-auto-ptkp@example.com']);

        $employee = User::factory()->create(['email' => 'tax-auto-ptkp-employee@example.com']);
        CompanyUser::query()->create([
            'company_id' => $admin['company_id'],
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        EmployeeProfile::query()->create([
            'user_id' => $employee->id,
            'company_id' => $admin['company_id'],
            'employment_status' => 'active',
            'contract_type' => 'permanent',
            'marital_status' => 'married',
        ]);

        $this->withHeaders($this->taxHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/reports/tenant-compliance-status')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.compliance_status.employee_pph21_compliance.active_employees', 2)
            ->assertJsonPath('data.compliance_status.employee_pph21_compliance.missing_npwp', 2)
            ->assertJsonPath('data.compliance_status.employee_pph21_compliance.missing_ptkp_status', 1);
    }
}
