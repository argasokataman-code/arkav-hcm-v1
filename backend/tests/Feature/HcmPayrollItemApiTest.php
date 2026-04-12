<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\HcmPayrollItem;
use App\Models\HcmSalaryComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmPayrollItemApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'PI Admin',
            'email' => 'pi-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'pi-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => 'HR Admin', 'employment_status' => 'active'],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'pi-admin@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function employeeToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'PI Emp',
            'email' => 'pi-emp@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'pi-emp@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_index_requires_hcm_admin(): void
    {
        $this->adminToken();
        $emp = $this->employeeToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$emp])
            ->getJson('/v1/hcm/payroll-items')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders(['Authorization' => 'Bearer '.$emp])
            ->postJson('/v1/hcm/payroll-items', [
                'name' => 'X',
                'kind' => 'addition',
                'category' => 'other_addition',
            ])
            ->assertStatus(403);
    }

    public function test_index_returns_payroll_items_only(): void
    {
        $token = $this->adminToken();

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/payroll-items')
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = $res->json('data.payrollItems');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertArrayNotHasKey('masterSalaryAdditions', $res->json('data'));
        $this->assertIsArray($res->json('meta.linkedSalaryComponentIds'));

        $linked = collect($items)->where('linkedToMaster', true);
        $custom = collect($items)->where('linkedToMaster', false);
        $this->assertNotEmpty($linked);
        $this->assertNotEmpty($custom);

        $this->assertSame(
            1,
            HcmPayrollItem::query()->where('code', 'tunjangan_proyek_internal')->count(),
        );
    }

    public function test_admin_crud_custom_and_link(): void
    {
        $token = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-items', [
                'name' => 'Bonus proyek API',
                'code' => 'bonus_proyek_api_test',
                'kind' => 'addition',
                'category' => 'bonus',
                'sortOrder' => 100,
                'isActive' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $row = HcmPayrollItem::query()->where('code', 'bonus_proyek_api_test')->firstOrFail();
        $this->assertNull($row->hcm_salary_component_id);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/payroll-items/'.$row->id, [
                'name' => 'Bonus proyek API (ubah)',
                'notes' => 'catatan',
            ])
            ->assertOk();

        $row->refresh();
        $this->assertSame('Bonus proyek API (ubah)', $row->name);
        $this->assertSame('catatan', $row->notes);

        $comp = HcmSalaryComponent::query()->where('code', 'upah_pokok')->firstOrFail();
        $existingLink = HcmPayrollItem::query()->where('hcm_salary_component_id', $comp->id)->first();
        $this->assertNotNull($existingLink);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-items', [
                'salaryComponentId' => $comp->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYROLL_ITEM_LINK_TAKEN');

        $free = HcmSalaryComponent::query()
            ->where('kind', 'addition')
            ->whereNotIn('id', HcmPayrollItem::query()->whereNotNull('hcm_salary_component_id')->pluck('hcm_salary_component_id'))
            ->first();
        $this->assertNotNull($free, 'Need an unlinked salary component in seed');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-items', [
                'salaryComponentId' => $free->id,
                'notes' => 'via link',
            ])
            ->assertStatus(201);

        $linkedItem = HcmPayrollItem::query()->where('hcm_salary_component_id', $free->id)->firstOrFail();
        $this->assertSame($free->code, $linkedItem->code);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/payroll-items/'.$linkedItem->id, [
                'notes' => 'hanya catatan',
                'isActive' => false,
            ])
            ->assertOk();
        $linkedItem->refresh();
        $this->assertSame('hanya catatan', $linkedItem->notes);
        $this->assertFalse($linkedItem->is_active);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/v1/hcm/payroll-items/'.$row->id)
            ->assertOk();

        $this->assertNull(HcmPayrollItem::query()->find($row->id));
    }

    public function test_unlink_payroll_item(): void
    {
        $token = $this->adminToken();
        $free = HcmSalaryComponent::query()
            ->where('kind', 'addition')
            ->whereNotIn('id', HcmPayrollItem::query()->whereNotNull('hcm_salary_component_id')->pluck('hcm_salary_component_id'))
            ->first();
        $this->assertNotNull($free);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-items', ['salaryComponentId' => $free->id])
            ->assertStatus(201);
        $item = HcmPayrollItem::query()->where('hcm_salary_component_id', $free->id)->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/payroll-items/'.$item->id, [
                'salaryComponentId' => null,
                'name' => 'Kustom setelah lepas',
                'kind' => 'addition',
                'category' => 'other_addition',
                'code' => 'setelah_lepas_unik',
            ])
            ->assertOk();

        $item->refresh();
        $this->assertNull($item->hcm_salary_component_id);
        $this->assertSame('Kustom setelah lepas', $item->name);
        $this->assertSame('setelah_lepas_unik', $item->code);
    }

    public function test_index_filters_by_kind_and_meta_lists_linked_components(): void
    {
        $token = $this->adminToken();

        $add = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/payroll-items?kind=addition')
            ->assertOk();
        foreach ($add->json('data.payrollItems') as $row) {
            $this->assertSame('addition', $row['kind']);
        }

        $ded = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/payroll-items?kind=deduction')
            ->assertOk();
        foreach ($ded->json('data.payrollItems') as $row) {
            $this->assertSame('deduction', $row['kind']);
        }

        $this->assertIsArray($ded->json('meta.linkedSalaryComponentIds'));
        $this->assertNotEmpty($ded->json('meta.linkedSalaryComponentIds'));
    }

    public function test_export_supports_csv_and_xlsx(): void
    {
        $token = $this->adminToken();

        $csv = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get('/v1/hcm/payroll-items/export?kind=addition&format=csv');
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('ID,"Salary Component ID","Linked To Master"', $csv->streamedContent());

        $xlsx = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get('/v1/hcm/payroll-items/export?kind=deduction&format=xlsx');
        $xlsx->assertOk();
        $xlsx->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('PK', substr($xlsx->streamedContent(), 0, 2));
    }

    public function test_cannot_link_inactive_salary_component(): void
    {
        $token = $this->adminToken();

        $inactive = HcmSalaryComponent::query()->create([
            'code' => 'inactive_component_test',
            'name' => 'Inactive Component Test',
            'kind' => 'addition',
            'category' => 'other_addition',
            'is_active' => false,
            'is_system_locked' => false,
            'sort_order' => 999,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/payroll-items', [
                'salaryComponentId' => $inactive->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYROLL_ITEM_MASTER_INACTIVE');
    }
}
