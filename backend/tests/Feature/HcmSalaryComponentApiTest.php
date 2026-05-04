<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeBenefit;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmBpjsGovernancePolicy;
use App\Models\HcmEmployeeAllowancePolicy;
use App\Models\HcmEmployeePayrollItemAssignment;
use App\Models\HcmPayrollItem;
use App\Models\HcmSalaryComponent;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmSalaryComponentApiTest extends TestCase
{
    use RefreshDatabase;

    private function hcmAdminBearerToken(string $email = 'sc-admin@example.com'): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'SC Admin',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => 'HR Admin']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function employeeBearerToken(string $email = 'sc-emp@example.com'): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'SC Emp',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_show_returns_one_component(): void
    {
        $token = $this->hcmAdminBearerToken();
        $row = HcmSalaryComponent::query()->where('code', 'iuran_bpjs_kes_pekerja')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/salary-components/'.$row->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'iuran_bpjs_kes_pekerja')
            ->assertJsonPath('data.integrationLocked', true)
            ->assertJsonPath('data.sourceModule', HcmSalaryComponent::SOURCE_MODULE_BPJS);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/salary-components/999999')
            ->assertNotFound();
    }

    public function test_index_requires_hcm_admin(): void
    {
        $this->hcmAdminBearerToken();
        $empToken = $this->employeeBearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$empToken])
            ->getJson('/v1/hcm/salary-components')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_admin_crud_and_locked_rules(): void
    {
        $token = $this->hcmAdminBearerToken();

        $list = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/salary-components')
            ->assertOk()
            ->assertJsonPath('success', true);

        $rows = $list->json('data');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $this->assertTrue(collect($rows)->contains(fn ($r) => ($r['code'] ?? '') === 'iuran_bpjs_kes_pekerja'));
        $bpjsKes = collect($rows)->firstWhere('code', 'iuran_bpjs_kes_pekerja');
        $this->assertNotNull($bpjsKes);
        $this->assertTrue((bool) ($bpjsKes['integrationLocked'] ?? false));
        $this->assertTrue(collect($bpjsKes['integrations'] ?? [])->contains(fn ($it) => ($it['label'] ?? null) === 'BPJS Governance'));
        $this->assertSame(HcmSalaryComponent::SOURCE_MODULE_BPJS, $bpjsKes['sourceModule'] ?? null);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/salary-components', [
                'name' => 'Bad payload',
                'kind' => 'addition',
                'category' => 'other_addition',
                'includeBpjsHealthWageBase' => false,
                'includeBpjsTkWageBase' => false,
                'includeThrCalculationBase' => false,
                'includePph21TerGross' => true,
                'includePph21AnnualReconciliation' => false,
                'subjectOvertimeRegulation' => false,
                'affectsNetPay' => true,
                'employerCostLine' => false,
                'isActive' => true,
                'sortOrder' => 1,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MANUAL_COMPONENT_CREATION_DISABLED');

        $locked = HcmSalaryComponent::query()->where('code', 'tunjangan_tetap_jabatan')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/salary-components/'.$locked->id, [
                'code' => 'tunjangan_tetap_jabatan',
                'name' => 'Tunjangan tetap jabatan (label)',
                'kind' => 'addition',
                'category' => 'fixed_allowance',
                'includeBpjsHealthWageBase' => true,
                'includeBpjsTkWageBase' => true,
                'includeThrCalculationBase' => true,
                'includePph21TerGross' => true,
                'includePph21AnnualReconciliation' => true,
                'subjectOvertimeRegulation' => false,
                'affectsNetPay' => true,
                'employerCostLine' => false,
                'isActive' => true,
                'sortOrder' => 5,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SYSTEM_LOCKED');

        $locked->refresh();
        $this->assertNotSame('Tunjangan tetap jabatan (label)', $locked->name);
        $this->assertSame('fixed_allowance', $locked->category);

        $integrationLocked = HcmSalaryComponent::query()->where('code', 'iuran_bpjs_kes_pekerja')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/salary-components/'.$integrationLocked->id, [
                'code' => 'iuran_bpjs_kes_pekerja',
                'name' => 'Iuran BPJS Kesehatan Pekerja (label)',
                'kind' => 'deduction',
                'category' => 'bpjs_health_employee',
                'includeBpjsHealthWageBase' => true,
                'includeBpjsTkWageBase' => false,
                'includeThrCalculationBase' => false,
                'includePph21TerGross' => false,
                'includePph21AnnualReconciliation' => false,
                'subjectOvertimeRegulation' => false,
                'affectsNetPay' => true,
                'employerCostLine' => false,
                'isActive' => true,
                'sortOrder' => 5,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SYSTEM_LOCKED');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/v1/hcm/salary-components/'.$integrationLocked->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'SYSTEM_LOCKED');

        $this->assertNotNull(HcmSalaryComponent::query()->find($integrationLocked->id));
    }

    public function test_prevent_duplicate_name_in_same_kind_and_category(): void
    {
        $token = $this->hcmAdminBearerToken('dup-sc-admin@example.com');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/salary-components', [
                'name' => 'Nama Duplikat Komponen',
                'code' => 'dup_sc_1',
                'kind' => 'addition',
                'category' => 'other_addition',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MANUAL_COMPONENT_CREATION_DISABLED');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/salary-components', [
                'name' => 'nama duplikat komponen',
                'code' => 'dup_sc_2',
                'kind' => 'addition',
                'category' => 'other_addition',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MANUAL_COMPONENT_CREATION_DISABLED');
    }

    public function test_admin_category_endpoints_are_read_only(): void
    {
        $token = $this->hcmAdminBearerToken('cat-sc-admin@example.com');

        $list = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/salary-component-categories')
            ->assertOk()
            ->assertJsonPath('success', true);

        $rows = collect($list->json('data'));
        $this->assertTrue($rows->contains(fn ($r) => ($r['code'] ?? null) === 'fixed_allowance'));

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/salary-component-categories', [
                'kind' => 'addition',
                'code' => 'team_allowance',
                'name' => 'Tunjangan tim',
                'description' => 'Kategori custom untuk tunjangan berbasis tim.',
                'sortOrder' => 510,
                'isActive' => true,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'CATEGORY_MASTER_READ_ONLY');

        $fixed = $rows->firstWhere('code', 'fixed_allowance');
        $this->assertNotNull($fixed);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/salary-component-categories/'.$fixed['id'], [
                'kind' => 'addition',
                'code' => 'fixed_allowance',
                'name' => 'Tunjangan Tetap',
                'description' => 'Kategori sistem read-only.',
                'sortOrder' => 20,
                'isActive' => true,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'CATEGORY_MASTER_READ_ONLY');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/v1/hcm/salary-component-categories/'.$fixed['id'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'CATEGORY_MASTER_READ_ONLY');
    }

    public function test_patch_tax_flags_supports_explicit_tax_treatment_code(): void
    {
        $token = $this->hcmAdminBearerToken('sc-tax-treatment@example.com');
        $component = HcmSalaryComponent::query()->where('code', 'tunjangan_tetap_jabatan')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/v1/hcm/salary-components/'.$component->id.'/tax-flags', [
                'taxTreatmentCode' => HcmSalaryComponent::TAX_TREATMENT_DEDUCTIBLE,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.taxTreatmentCode', HcmSalaryComponent::TAX_TREATMENT_DEDUCTIBLE)
            ->assertJsonPath('data.includePph21TerGross', false)
            ->assertJsonPath('data.includePph21AnnualReconciliation', true)
            ->assertJsonPath('data.employerCostLine', false);

        $component->refresh();

        $this->assertSame(HcmSalaryComponent::TAX_TREATMENT_DEDUCTIBLE, $component->tax_treatment_code);
        $this->assertFalse((bool) $component->include_pph21_ter_gross);
        $this->assertTrue((bool) $component->include_pph21_annual_reconciliation);
        $this->assertFalse((bool) $component->employer_cost_line);
    }

    public function test_employee_profiles_endpoint_returns_identity_and_assignment_summary(): void
    {
        $token = $this->hcmAdminBearerToken('sc-profile-admin@example.com');
        $admin = User::query()->where('email', 'sc-profile-admin@example.com')->firstOrFail();
        $company = Company::query()->firstOrFail();
        $companyId = (int) $company->id;

        CompanyUser::query()->updateOrCreate(
            ['company_id' => $companyId, 'user_id' => $admin->id],
            ['role' => 'hcm_admin', 'status' => 'active']
        );

        $employee = User::query()->create([
            'name' => 'Profile Integration Employee',
            'email' => 'profile-employee@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyId,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $owner = User::query()->create([
            'name' => 'Tenant Owner',
            'email' => 'tenant-owner-profile@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee->id],
            [
                'company_id' => $companyId,
                'phone' => '0811111111',
                'designation' => 'Staff Payroll',
                'team' => 'Compensation',
                'base_salary' => 6000000,
            ]
        );
        $employeeProfile = EmployeeProfile::query()->where('user_id', $employee->id)->firstOrFail();

        EmployeeTaxProfile::query()->create([
            'employee_id' => (int) $employeeProfile->id,
            'npwp' => '123456789012345',
            'tax_status' => 'TK0',
            'ptkp_status' => 'TK0',
            'effective_date' => now()->toDateString(),
        ]);

        EmployeeBenefit::query()->create([
            'employee_id' => (int) $employeeProfile->id,
            'bpjs_kesehatan_no' => 'KES-12345',
            'bpjs_ketenagakerjaan_no' => 'TK-12345',
            'effective_date' => now()->toDateString(),
        ]);

        HcmTaxGovernancePolicy::query()->create([
            'company_id' => $companyId,
            'company_uuid' => (string) $company->uuid,
            'policy_code' => 'PPH21-TEST-SC',
            'name' => 'PPh21 Test Salary Component',
            'status' => HcmTaxGovernancePolicy::STATUS_PUBLISHED,
            'effective_start_date' => now()->toDateString(),
            'effective_end_date' => null,
            'rules' => ['scheme' => 'STATUTORY_PPH21'],
            'rate_schedules' => [['category' => 'A', 'calculationMode' => 'ter_lookup']],
        ]);

        $bpjsPairs = [
            ['bpjs_kesehatan', 'employee', '1.0000', 'wage_bpjs_health'],
            ['bpjs_kesehatan', 'employer', '4.0000', 'wage_bpjs_health'],
            ['jht', 'employee', '2.0000', 'wage_bpjs_tk'],
            ['jht', 'employer', '3.7000', 'wage_bpjs_tk'],
            ['jp', 'employee', '1.0000', 'wage_bpjs_tk'],
            ['jp', 'employer', '2.0000', 'wage_bpjs_tk'],
            ['jkk', 'employer', '0.2400', 'wage_bpjs_tk'],
            ['jkm', 'employer', '0.3000', 'wage_bpjs_tk'],
        ];

        foreach ($bpjsPairs as [$programCode, $party, $ratePercent, $wageBase]) {
            HcmBpjsGovernancePolicy::query()->create([
                'company_id' => $companyId,
                'company_uuid' => (string) $company->uuid,
                'program_code' => $programCode,
                'contribution_party' => $party,
                'rate_percent' => $ratePercent,
                'wage_base' => $wageBase,
                'effective_start_date' => now()->toDateString(),
                'effective_end_date' => null,
                'legal_basis' => 'Regulasi BPJS test',
                'is_active' => true,
            ]);
        }

        $allowanceComponent = HcmSalaryComponent::ensureComponent(
            $companyId,
            'allowance_test_profile',
            'Allowance Test Profile',
            'addition',
            'fixed_allowance',
            HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE,
            [
                'is_system_locked' => true,
                'is_active' => true,
            ]
        );

        $item = HcmPayrollItem::query()->create([
            'company_id' => $companyId,
            'hcm_salary_component_id' => $allowanceComponent->id,
            'code' => $allowanceComponent->code,
            'name' => $allowanceComponent->name,
            'kind' => $allowanceComponent->kind,
            'category' => $allowanceComponent->category,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        HcmEmployeeAllowancePolicy::query()->create([
            'company_id' => $companyId,
            'company_uuid' => (string) $company->uuid,
            'code' => $allowanceComponent->code,
            'name' => $allowanceComponent->name,
            'allowance_type' => 'fixed',
            'is_taxable' => true,
            'is_mandatory' => true,
            'default_amount' => 250000,
            'frequency' => 'monthly',
            'effective_start_date' => now()->toDateString(),
            'effective_end_date' => null,
            'status' => 'active',
            'is_active' => true,
        ]);

        HcmEmployeePayrollItemAssignment::query()->create([
            'company_id' => $companyId,
            'user_id' => $employee->id,
            'hcm_payroll_item_id' => $item->id,
            'amount' => 250000,
            'is_active' => true,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/salary-components/employee-profiles')
            ->assertOk()
            ->assertJsonPath('success', true);

        $row = collect($response->json('data.rows'))->firstWhere('userId', $employee->id);
        $this->assertNotNull($row);
        $this->assertSame('Profile Integration Employee', $row['fullName']);
        $this->assertSame('EMP-'.$employee->id, $row['employeeCode']);
        $this->assertIsArray($row['identityGaps']);
        $this->assertIsArray($row['assignmentSummary']['componentCodes'] ?? null);
        $this->assertGreaterThanOrEqual(1, (int) ($row['assignmentSummary']['allowanceAssignments'] ?? 0));
        $this->assertSame('partial', $row['integrationStatus']);
        $this->assertIsArray($row['integrationSummary']['checks'] ?? null);
        $this->assertContains('department', $row['identityGaps']);
        $checks = collect($row['integrationSummary']['checks'] ?? [])->keyBy('key');
        $this->assertTrue((bool) ($checks->get('pph21')['ready'] ?? false));
        $this->assertTrue((bool) ($checks->get('bpjs')['ready'] ?? false));
        $this->assertTrue((bool) ($checks->get('allowance')['ready'] ?? false));
        $this->assertTrue((bool) ($checks->get('payroll')['ready'] ?? false));
        $this->assertNull(collect($response->json('data.rows'))->firstWhere('userId', $owner->id));
    }
}
