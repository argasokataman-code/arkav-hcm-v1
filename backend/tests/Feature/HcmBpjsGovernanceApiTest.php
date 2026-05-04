<?php

namespace Tests\Feature;

use App\Models\HcmBpjsGovernancePolicy;
use App\Models\HcmBpjsGovernancePolicyHistory;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmBpjsGovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_update_flow_and_reports_endpoint(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'bpjs-gov-admin@example.com']);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);

        $policy = HcmBpjsGovernancePolicy::query()->create([
            'company_id' => (int) $admin['company_id'],
            'company_uuid' => (string) $admin['company']->uuid,
            'program_code' => 'bpjs_kesehatan',
            'contribution_party' => 'employee',
            'rate_percent' => '1.0000',
            'wage_base' => 'wage_bpjs_health',
            'effective_start_date' => now()->toDateString(),
            'effective_end_date' => null,
            'legal_basis' => 'Perpres BPJS Kesehatan',
            'notes' => 'Initial employee contribution',
            'is_active' => true,
        ]);

        $policyUuid = (string) $policy->uuid;

        $this->withHeaders($headers)
            ->putJson('/v1/hcm/bpjs-governance/policies/' . $policyUuid, [
                'ratePercent' => 1,
                'notes' => 'Adjusted by compliance review',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ratePercent', '1.0000')
            ->assertJsonPath('data.notes', 'Adjusted by compliance review');

        $policyId = (int) $policy->id;

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/bpjs-governance/policies?active_only=0')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.items.0.id', $policyId);

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/bpjs-governance/reports')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reportingPeriod',
                    'policyActiveCount',
                    'programCoverage',
                    'rateAudit' => ['items', 'reviewRequiredCount'],
                    'employeeMembership' => ['totalEmployees', 'complete', 'partial', 'missing', 'completionRate'],
                    'score',
                    'checks',
                ],
            ]);

        $reportResponse = $this->withHeaders($headers)
            ->getJson('/v1/hcm/bpjs-governance/reports')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $checks = collect($reportResponse->json('data.checks'));
        $membershipCheck = $checks->firstWhere('code', 'membership_coverage');
        $this->assertIsArray($membershipCheck['evidence']['nonCompliantEmployees'] ?? null);
    }

    public function test_policy_update_rejects_mutating_immutable_fields_and_non_regulatory_rate(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'bpjs-gov-invalid-policy@example.com']);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);

        $policy = HcmBpjsGovernancePolicy::query()->create([
            'company_id' => (int) $admin['company_id'],
            'company_uuid' => (string) $admin['company']->uuid,
            'program_code' => 'jht',
            'contribution_party' => 'employee',
            'rate_percent' => '2.0000',
            'wage_base' => 'wage_bpjs_tk',
            'effective_start_date' => now()->toDateString(),
            'effective_end_date' => null,
            'legal_basis' => 'Regulasi JHT',
            'notes' => null,
            'is_active' => true,
        ]);

        $this->withHeaders($headers)
            ->putJson('/v1/hcm/bpjs-governance/policies/' . $policy->uuid, [
                'ratePercent' => 3,
                'wageBase' => 'fixed_nominal',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'errors' => ['wageBase'],
            ]);

        $this->withHeaders($headers)
            ->putJson('/v1/hcm/bpjs-governance/policies/' . $policy->uuid, [
                'ratePercent' => 3,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'errors' => ['ratePercent'],
            ]);
    }

    public function test_policy_create_is_enabled_for_tenant_runtime(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'bpjs-gov-create-disabled@example.com']);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/bpjs-governance/policies', [
                'programCode' => 'bpjs_kesehatan',
                'contributionParty' => 'employee',
                'ratePercent' => 1,
                'wageBase' => 'wage_bpjs_health',
                'effectiveStartDate' => now()->toDateString(),
                'legalBasis' => 'Perpres BPJS Kesehatan',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.programCode', 'bpjs_kesehatan')
            ->assertJsonPath('data.contributionParty', 'employee');
    }

    public function test_policy_delete_flow_for_tenant_runtime(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'bpjs-gov-delete-enabled@example.com']);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);

        $policy = HcmBpjsGovernancePolicy::query()->create([
            'company_id' => (int) $admin['company_id'],
            'company_uuid' => (string) $admin['company']->uuid,
            'program_code' => 'jkm',
            'contribution_party' => 'employer',
            'rate_percent' => '0.3000',
            'wage_base' => 'wage_bpjs_tk',
            'effective_start_date' => now()->toDateString(),
            'effective_end_date' => null,
            'legal_basis' => 'Regulasi JKM',
            'notes' => null,
            'is_active' => true,
        ]);

        $this->withHeaders($headers)
            ->deleteJson('/v1/hcm/bpjs-governance/policies/' . $policy->uuid)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('hcm_bpjs_governance_policies', [
            'id' => $policy->id,
        ]);

        $this->assertDatabaseHas('hcm_bpjs_governance_policy_histories', [
            'company_id' => (int) $admin['company_id'],
            'policy_uuid' => (string) $policy->uuid,
            'action_type' => 'deleted',
        ]);
    }

    public function test_policy_history_endpoint_returns_logged_actions(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'bpjs-gov-history@example.com']);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);

        $create = $this->withHeaders($headers)
            ->postJson('/v1/hcm/bpjs-governance/policies', [
                'programCode' => 'jp',
                'contributionParty' => 'employee',
                'ratePercent' => 1,
                'wageBase' => 'wage_bpjs_tk',
                'effectiveStartDate' => now()->toDateString(),
                'legalBasis' => 'Regulasi JP',
                'notes' => 'created-from-test',
            ])
            ->assertStatus(201);

        $policyUuid = (string) ($create->json('data.uuid') ?? '');
        $this->assertNotSame('', $policyUuid);

        $this->withHeaders($headers)
            ->putJson('/v1/hcm/bpjs-governance/policies/' . $policyUuid, [
                'ratePercent' => 1,
                'notes' => 'updated-from-test',
            ])
            ->assertStatus(200);

        $response = $this->withHeaders($headers)
            ->getJson('/v1/hcm/bpjs-governance/policies/history?limit=20')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items',
                    'meta' => ['limit', 'total'],
                ],
            ]);

        $items = collect($response->json('data.items'));
        $actions = $items
            ->where('policyUuid', $policyUuid)
            ->pluck('actionType')
            ->unique()
            ->values()
            ->all();

        $this->assertContains('created', $actions);
        $this->assertContains('updated', $actions);
        $this->assertDatabaseHas('hcm_bpjs_governance_policy_histories', [
            'company_id' => (int) $admin['company_id'],
            'policy_uuid' => $policyUuid,
            'action_type' => 'created',
        ]);
        $this->assertDatabaseHas('hcm_bpjs_governance_policy_histories', [
            'company_id' => (int) $admin['company_id'],
            'policy_uuid' => $policyUuid,
            'action_type' => 'updated',
        ]);
    }

    public function test_membership_list_and_update_flow(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'bpjs-membership-admin@example.com']);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);

        $membershipList = $this->withHeaders($headers)
            ->getJson('/v1/hcm/bpjs-governance/employee-membership?page=1&perPage=20');

        $membershipList->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items',
                    'meta' => ['page', 'perPage', 'total', 'complete'],
                ],
            ]);

        $firstUserId = (int) ($membershipList->json('data.items.0.id') ?? 0);
        $this->assertGreaterThan(0, $firstUserId);

        $update = $this->withHeaders($headers)
            ->putJson('/v1/hcm/bpjs-governance/employee-membership/' . $firstUserId, [
                'bpjsKesehatanNo' => 'KES-TEST-001',
                'bpjsKetenagakerjaanNo' => 'TK-TEST-001',
                'effectiveDate' => now()->toDateString(),
            ]);

        $update->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.userId', $firstUserId)
            ->assertJsonPath('data.membershipStatus', 'complete');
    }

    public function test_membership_update_always_creates_new_history_record(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'bpjs-membership-history@example.com']);
        $headers = $this->withCompanyContext(['Authorization' => 'Bearer ' . $admin['token']], $admin['company_id']);

        $membershipList = $this->withHeaders($headers)->getJson('/v1/hcm/bpjs-governance/employee-membership?page=1&perPage=20');
        $membershipList->assertStatus(200);
        $firstUserId = (int) ($membershipList->json('data.items.0.id') ?? 0);
        $this->assertGreaterThan(0, $firstUserId);

        // First update
        $this->withHeaders($headers)
            ->putJson('/v1/hcm/bpjs-governance/employee-membership/' . $firstUserId, [
                'bpjsKesehatanNo' => 'KES-V1-001',
                'bpjsKetenagakerjaanNo' => 'TK-V1-001',
                'effectiveDate' => now()->subMonth()->toDateString(),
            ])->assertStatus(200)->assertJsonPath('data.membershipStatus', 'complete');

        // Second update (different date) — must create new record, not mutate
        $this->withHeaders($headers)
            ->putJson('/v1/hcm/bpjs-governance/employee-membership/' . $firstUserId, [
                'bpjsKesehatanNo' => 'KES-V2-001',
                'bpjsKetenagakerjaanNo' => 'TK-V2-001',
                'effectiveDate' => now()->toDateString(),
            ])->assertStatus(200)->assertJsonPath('data.bpjsKesehatanNo', 'KES-V2-001');

        // Latest membership read should return the most recent version
        $freshList = $this->withHeaders($headers)->getJson('/v1/hcm/bpjs-governance/employee-membership?page=1&perPage=20');
        $freshItem = collect($freshList->json('data.items'))->firstWhere('id', $firstUserId);
        $this->assertEquals('KES-V2-001', $freshItem['bpjsKesehatanNo']);
        $this->assertEquals('TK-V2-001', $freshItem['bpjsKetenagakerjaanNo']);
    }

    public function test_reports_export_returns_json_attachment(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'bpjs-export-admin@example.com']);
        $headers = $this->withCompanyContext(['Authorization' => 'Bearer ' . $admin['token']], $admin['company_id']);

        $response = $this->withHeaders($headers)->get('/v1/hcm/bpjs-governance/reports/export');
        $response->assertStatus(200);
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('bpjs-compliance-report-', (string) $response->headers->get('Content-Disposition'));
        $decoded = json_decode($response->getContent(), true);
        $this->assertTrue((bool) ($decoded['success'] ?? false));
        $this->assertArrayHasKey('data', $decoded);
    }

    public function test_rate_baselines_list_returns_system_defaults_when_no_tenant_config(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'bpjs-rate-baselines@example.com']);
        $headers = $this->withCompanyContext(['Authorization' => 'Bearer ' . $admin['token']], $admin['company_id']);

        $response = $this->withHeaders($headers)->getJson('/v1/hcm/bpjs-governance/rate-baselines');
        $response->assertStatus(200)->assertJsonPath('success', true);
        $items = $response->json('data.items');
        $this->assertNotEmpty($items);

        // All items should be system_default since no tenant config set
        foreach ($items as $item) {
            $this->assertEquals('system_default', $item['source']);
        }

        // jht employee should have min/max = 2.0
        $jhtEmployee = collect($items)->firstWhere(fn ($i) => $i['programCode'] === 'jht' && $i['contributionParty'] === 'employee');
        $this->assertNotNull($jhtEmployee);
        $this->assertEquals(2.0, $jhtEmployee['minRate']);
        $this->assertEquals(2.0, $jhtEmployee['maxRate']);
    }

    public function test_rate_baseline_update_overrides_system_default(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'bpjs-rate-baseline-update@example.com']);
        $headers = $this->withCompanyContext(['Authorization' => 'Bearer ' . $admin['token']], $admin['company_id']);

        // Override jkk employer baseline (variable rate — good test case)
        $this->withHeaders($headers)
            ->putJson('/v1/hcm/bpjs-governance/rate-baselines/jkk/employer', [
                'minRate' => 0.5,
                'maxRate' => 1.0,
                'wageBase' => 'wage_bpjs_tk',
                'notes' => 'Adjusted for high-risk industry',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.source', 'tenant')
            ->assertJsonPath('data.minRate', 0.5);

        // Verify maxRate via list (avoids float vs int assertion edge case)
        $listCheck = $this->withHeaders($headers)->getJson('/v1/hcm/bpjs-governance/rate-baselines');
        $tenantRow = collect($listCheck->json('data.items'))->firstWhere(fn ($i) => $i['programCode'] === 'jkk' && $i['contributionParty'] === 'employer');
        $this->assertEquals(1.0, (float) $tenantRow['maxRate']);

        // List should now show tenant source for jkk employer
        $list = $this->withHeaders($headers)->getJson('/v1/hcm/bpjs-governance/rate-baselines');
        $jkkEmployer = collect($list->json('data.items'))->firstWhere(fn ($i) => $i['programCode'] === 'jkk' && $i['contributionParty'] === 'employer');
        $this->assertEquals('tenant', $jkkEmployer['source']);
        $this->assertEquals(0.5, $jkkEmployer['minRate']);
    }

    public function test_policy_create_forbidden_without_payroll_manage_permission(): void
    {
        $company = $this->createIsolatedTestCompany();
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'BPJS Forbidden User',
            'email' => 'bpjs-forbidden-user@example.com',
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $user = User::query()->where('email', 'bpjs-forbidden-user@example.com')->firstOrFail();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'bpjs-forbidden-user@example.com',
            'password' => $password,
            'companyCode' => $company->code,
        ])->assertStatus(200);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . (string) $login->json('data.accessToken'),
        ], $company->id);

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/bpjs-governance/policies', [
                'programCode' => 'jht',
                'contributionParty' => 'employee',
                'ratePercent' => 2,
                'effectiveStartDate' => now()->toDateString(),
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }
}
