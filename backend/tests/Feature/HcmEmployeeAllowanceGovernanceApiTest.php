<?php

namespace Tests\Feature;

use App\Models\CompanyUser;
use App\Models\HcmEmployeeAllowanceAssignment;
use App\Models\HcmEmployeeAllowancePolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmEmployeeAllowanceGovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function createAllowancePolicy(array $headers, array $overrides = []): HcmEmployeeAllowancePolicy
    {
        $payload = array_merge([
            'code' => 'allowance_transport_test',
            'name' => 'Tunjangan Transport Test',
            'isTaxable' => true,
            'isMandatory' => false,
            'defaultAmount' => 150000,
            'effectiveStartDate' => now()->toDateString(),
            'status' => 'active',
        ], $overrides);

        $response = $this->withHeaders($headers)
            ->postJson('/v1/hcm/allowance-governance/policies', $payload)
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        return HcmEmployeeAllowancePolicy::query()->findOrFail((int) $response->json('data.id'));
    }

    public function test_policies_endpoint_returns_created_policies_without_runtime_seed(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'allowance-gov-admin@example.com']);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);

        $policy = $this->createAllowancePolicy($headers);

        $response = $this->withHeaders($headers)
            ->getJson('/v1/hcm/allowance-governance/policies?active_only=0')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items',
                    'meta' => ['total', 'as_of'],
                ],
            ]);

        $items = collect($response->json('data.items'));
        $this->assertGreaterThanOrEqual(1, $items->count());
        $this->assertTrue($items->pluck('code')->contains($policy->code));
    }

    public function test_policy_create_update_and_activate_flow(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'allowance-gov-policy-flow@example.com']);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);

        $create = $this->withHeaders($headers)
            ->postJson('/v1/hcm/allowance-governance/policies', [
                'code' => 'allowance_custom_test',
                'name' => 'Tunjangan Custom Test',
                'isTaxable' => true,
                'isMandatory' => false,
                'defaultAmount' => 350000,
                'effectiveStartDate' => now()->toDateString(),
                'status' => 'draft',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'allowance_custom_test')
            ->assertJsonPath('data.status', 'draft');

        $policyRef = (string) ($create->json('data.uuid') ?? '');
        $this->assertNotSame('', $policyRef);

        $this->withHeaders($headers)
            ->patchJson('/v1/hcm/allowance-governance/policies/' . $policyRef, [
                'defaultAmount' => 425000,
                'status' => 'active',
                'isActive' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.defaultAmount', '425000.00');

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/allowance-governance/policies/' . $policyRef . '/activate', [])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.isActive', true);
    }

    public function test_assignment_flow_and_compliance_report_structure(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'allowance-gov-assignment@example.com']);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);

        $employee = User::factory()->create(['email' => 'allowance-employee@example.com']);
        CompanyUser::query()->create([
            'company_id' => $admin['company_id'],
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $policy = $this->createAllowancePolicy($headers, [
            'code' => 'allowance_assignment_test',
            'name' => 'Tunjangan Assignment Test',
            'isMandatory' => true,
        ]);

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/allowance-governance/assignments', [
                'policyRef' => (string) $policy->uuid,
                'userId' => (int) $employee->id,
                'amountOverride' => 200000,
                'effectiveStartDate' => now()->toDateString(),
                'status' => 'active',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.userId', (int) $employee->id);

        $this->assertDatabaseHas('hcm_employee_allowance_assignments', [
            'company_id' => (int) $admin['company_id'],
            'user_id' => (int) $employee->id,
            'policy_id' => (int) $policy->id,
        ]);

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/allowance-governance/assignments?page=1&perPage=50')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items',
                    'meta' => ['page', 'perPage', 'total', 'as_of'],
                ],
            ]);

        $report = $this->withHeaders($headers)
            ->getJson('/v1/hcm/allowance-governance/reports/compliance')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reportingPeriod',
                    'activePolicyCount',
                    'mandatoryPolicyCount',
                    'employeeScopeCount',
                    'score',
                    'checks',
                ],
            ]);

        $checks = collect($report->json('data.checks'));
        $coverageCheck = $checks->firstWhere('code', 'mandatory_assignment_coverage');
        $this->assertIsArray($coverageCheck['evidence']['nonCompliantEmployees'] ?? null);
    }

    public function test_assignment_overlap_is_rejected_for_active_periods(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'allowance-gov-overlap@example.com']);

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);

        $employee = User::factory()->create(['email' => 'allowance-overlap-employee@example.com']);
        CompanyUser::query()->create([
            'company_id' => $admin['company_id'],
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $policy = $this->createAllowancePolicy($headers, [
            'code' => 'allowance_overlap_test',
            'name' => 'Tunjangan Overlap Test',
            'isMandatory' => true,
        ]);

        HcmEmployeeAllowanceAssignment::query()->create([
            'company_id' => (int) $admin['company_id'],
            'policy_id' => (int) $policy->id,
            'policy_uuid' => (string) $policy->uuid,
            'user_id' => (int) $employee->id,
            'user_uuid' => (string) $employee->uuid,
            'amount_override' => '100000.00',
            'effective_start_date' => now()->toDateString(),
            'effective_end_date' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/allowance-governance/assignments', [
                'policyRef' => (string) $policy->uuid,
                'userId' => (int) $employee->id,
                'amountOverride' => 150000,
                'effectiveStartDate' => now()->toDateString(),
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'errors' => ['effectiveStartDate'],
            ]);
    }
}
