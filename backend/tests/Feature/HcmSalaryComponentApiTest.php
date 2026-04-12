<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\HcmSalaryComponent;
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
        $row = HcmSalaryComponent::query()->where('code', 'upah_pokok')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/salary-components/'.$row->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'upah_pokok')
            ->assertJsonPath('data.integrationLocked', true);

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
        $this->assertTrue(collect($rows)->contains(fn ($r) => ($r['code'] ?? '') === 'upah_pokok'));
        $upahPokok = collect($rows)->firstWhere('code', 'upah_pokok');
        $this->assertNotNull($upahPokok);
        $this->assertTrue((bool) ($upahPokok['integrationLocked'] ?? false));
        $this->assertTrue(collect($upahPokok['integrations'] ?? [])->contains(fn ($it) => ($it['label'] ?? null) === 'Employee Salary'));
        $bpjsKes = collect($rows)->firstWhere('code', 'iuran_bpjs_kes_pekerja');
        $this->assertNotNull($bpjsKes);
        $this->assertSame('1.0000', $bpjsKes['defaultPercent'] ?? null);
        $this->assertSame('wage_bpjs_health', $bpjsKes['percentBasis'] ?? null);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/salary-components', [
                'name' => 'Bad percent',
                'kind' => 'addition',
                'category' => 'other_addition',
                'defaultPercent' => 5,
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
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/salary-components', [
                'name' => 'Tunjangan proyek X',
                'code' => 'tunj_proyek_x',
                'kind' => 'addition',
                'category' => 'irregular_allowance',
                'description' => 'test',
                'legalBasis' => 'Kebijakan internal',
                'defaultPercent' => 10,
                'percentBasis' => 'basic_wage',
                'includeBpjsHealthWageBase' => false,
                'includeBpjsTkWageBase' => false,
                'includeThrCalculationBase' => false,
                'includePph21TerGross' => true,
                'includePph21AnnualReconciliation' => false,
                'subjectOvertimeRegulation' => false,
                'affectsNetPay' => true,
                'employerCostLine' => false,
                'isActive' => true,
                'sortOrder' => 900,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $newId = (int) $create->json('data.id');
        $this->assertGreaterThan(0, $newId);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/salary-components/'.$newId, [
                'code' => 'tunj_proyek_x',
                'name' => 'Tunjangan proyek X (ubah)',
                'kind' => 'addition',
                'category' => 'irregular_allowance',
                'description' => null,
                'legalBasis' => 'Kebijakan internal',
                'legalNotes' => null,
                'includeBpjsHealthWageBase' => false,
                'includeBpjsTkWageBase' => false,
                'includeThrCalculationBase' => false,
                'includePph21TerGross' => true,
                'includePph21AnnualReconciliation' => false,
                'subjectOvertimeRegulation' => false,
                'affectsNetPay' => true,
                'employerCostLine' => false,
                'isActive' => false,
                'sortOrder' => 901,
                'defaultPercent' => 12.5,
                'percentBasis' => 'basic_wage',
            ])
            ->assertOk();

        $row = HcmSalaryComponent::query()->findOrFail($newId);
        $this->assertSame('12.5000', (string) $row->default_percent);
        $this->assertSame('basic_wage', $row->percent_basis);

        $locked = HcmSalaryComponent::query()->where('code', 'tunjangan_tetap_jabatan')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/salary-components/'.$locked->id, [
                'name' => 'Tunjangan tetap jabatan (label)',
                'description' => 'updated desc',
                'isActive' => true,
                'sortOrder' => 5,
                'defaultPercent' => null,
                'percentBasis' => null,
            ])
            ->assertOk();

        $locked->refresh();
        $this->assertSame('Tunjangan tetap jabatan (label)', $locked->name);
        $this->assertSame('fixed_allowance', $locked->category);

        $integrationLocked = HcmSalaryComponent::query()->where('code', 'upah_pokok')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/salary-components/'.$integrationLocked->id, [
                'name' => 'Upah pokok (label)',
                'description' => 'updated desc',
                'isActive' => true,
                'sortOrder' => 5,
                'defaultPercent' => null,
                'percentBasis' => null,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INTEGRATION_LOCKED');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/v1/hcm/salary-components/'.$integrationLocked->id)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INTEGRATION_LOCKED');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/v1/hcm/salary-components/'.$newId)
            ->assertOk();

        $this->assertNull(HcmSalaryComponent::query()->find($newId));
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
            ->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/salary-components', [
                'name' => 'nama duplikat komponen',
                'code' => 'dup_sc_2',
                'kind' => 'addition',
                'category' => 'other_addition',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
