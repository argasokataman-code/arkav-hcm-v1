<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\HcmActivityLog;
use App\Models\Team;
use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Models\WilayahVillage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function validEmployeePayload(int $companyId, array $overrides = []): array
    {
        $department = Department::query()->firstOrCreate(
            ['code' => 'ENG-AUDIT'],
            ['name' => 'Engineering Audit', 'is_active' => true],
        );
        $designation = Designation::query()->firstOrCreate(
            ['code' => 'DEV-AUDIT'],
            ['department_id' => $department->id, 'name' => 'Developer Audit', 'is_active' => true],
        );
        $team = Team::query()->firstOrCreate(
            ['company_id' => $companyId, 'name' => 'Audit Team'],
            ['department_id' => $department->id, 'is_active' => true],
        );
        $province = WilayahProvince::query()->firstOrCreate(['code' => '31'], ['name' => 'DKI Jakarta']);
        $regency = WilayahRegency::query()->firstOrCreate(['code' => '31.74'], ['province_id' => $province->id, 'name' => 'Jakarta Selatan']);
        $district = WilayahDistrict::query()->firstOrCreate(['code' => '31.74.09'], ['regency_id' => $regency->id, 'name' => 'Jagakarsa']);
        $village = WilayahVillage::query()->firstOrCreate(['code' => '31.74.09.1001'], ['district_id' => $district->id, 'name' => 'Jagakarsa']);

        return array_merge([
            'name' => 'Karyawan Audit',
            'email' => 'karyawan.audit.' . uniqid() . '@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
            'data_disclosure_acknowledged' => true,
            'team' => $team->name,
            'teamId' => $team->id,
            'departmentId' => $department->id,
            'designationId' => $designation->id,
            'employeeType' => 'permanent',
            'employmentStatus' => 'active',
            'phone' => '081234567890',
            'nik' => '3174011708980001',
            'placeOfBirth' => 'Jakarta',
            'dateOfBirth' => '1998-08-17',
            'gender' => 'female',
            'maritalStatus' => 'single',
            'religion' => 'Islam',
            'nationality' => 'Indonesia',
            'addressDetail' => 'Jl. Test No. 1',
            'baseSalary' => 6500000,
            'salaryType' => 'monthly',
            'contractType' => 'permanent',
            'contractStatus' => 'active',
            'contractStartDate' => '2025-01-01',
            'bankName' => 'BCA',
            'bankAccountNo' => '1234567890',
            'bankAccountHolderName' => 'Karyawan Audit',
            'emergencyContacts' => [
                ['name' => 'Ibu Test', 'relationship' => 'Mother', 'phone' => '081234567891'],
            ],
            'provinceId' => $province->id,
            'regencyId' => $regency->id,
            'districtId' => $district->id,
            'villageId' => $village->id,
        ], $overrides);
    }

    public function test_creating_employee_logs_activity(): void
    {
        $ctx = $this->createHcmAdminWithCompany(['email' => 'audit.emp.admin@example.com']);
        $token = $ctx['token'];
        $companyId = $ctx['company_id'];

        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload($companyId));

        $res->assertStatus(201)
            ->assertJsonPath('success', true);

        $employeeUuid = (string) $res->json('data.uuid');

        $this->assertDatabaseHas('hcm_activity_logs', [
            'company_id' => $companyId,
            'entity_type' => 'employee',
            'entity_uuid' => $employeeUuid,
            'action' => 'created',
        ]);
    }

    public function test_log_record_has_correct_company_isolation(): void
    {
        $ctx1 = $this->createHcmAdminWithCompany(['email' => 'audit.iso1@example.com']);
        $ctx2 = $this->createHcmAdminWithCompany(['email' => 'audit.iso2@example.com']);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $ctx1['token'],
            'X-Company-Id' => (string) $ctx1['company_id'],
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload($ctx1['company_id']))
            ->assertStatus(201);

        $logsC1 = HcmActivityLog::query()
            ->where('company_id', $ctx1['company_id'])
            ->where('entity_type', 'employee')
            ->where('action', 'created')
            ->count();

        $logsC2 = HcmActivityLog::query()
            ->where('company_id', $ctx2['company_id'])
            ->where('entity_type', 'employee')
            ->where('action', 'created')
            ->count();

        $this->assertGreaterThanOrEqual(1, $logsC1, 'Company 1 should have at least 1 employee created log');
        $this->assertSame(0, $logsC2, 'Company 2 should have zero employee logs — tenant isolation breach');
    }
}
