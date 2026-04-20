<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\EmployeeProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminationApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(bool $asAdmin): array
    {
        $email = $asAdmin ? 'qa.login@example.com' : 'employee@company.com';
        $this->postJson('/v1/identity/auth/register', [
            'name' => $asAdmin ? 'Admin User' : 'Employee User',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $user = User::query()->where('email', $email)->firstOrFail();

        $resp = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = $resp->json('data.accessToken');
        $this->assertIsString($token);

        return [$user, $token];
    }

    public function test_terminations_admin_crud_and_employee_forbidden(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $this->withHeaders(['Authorization' => 'Bearer '.$empToken])
            ->getJson('/v1/hcm/terminations')
            ->assertStatus(403);

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Workforce reduction',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'notes' => 'OK',
            ])->assertStatus(201);

        $id = $create->json('data.id');
        $this->assertIsInt($id);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->putJson('/v1/hcm/terminations/'.$id, [
                'status' => 'approved',
            ])->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->deleteJson('/v1/hcm/terminations/'.$id)
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_termination_show_and_per_user_list_self_only(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$empA, $empAToken] = $this->login(false);

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Emp B',
            'email' => 'empb@company.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);
        $empB = User::query()->where('email', 'empb@company.com')->firstOrFail();
        $empBToken = $this->postJson('/v1/identity/auth/login', [
            'email' => 'empb@company.com',
            'password' => 'StrongPass1',
        ])->assertOk()->json('data.accessToken');
        $this->assertIsString($empBToken);

        $body = [
            'terminationType' => 'Retirement',
            'reason' => 'End of contract',
            'noticeDate' => '2026-04-01',
            'terminationDate' => '2026-05-01',
            'notes' => 'ok',
        ];

        $idA = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', array_merge(['userId' => $empA->uuid], $body))
            ->assertStatus(201)
            ->json('data.id');

        $idB = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', array_merge(['userId' => $empB->uuid], $body))
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/terminations/'.$idA)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/terminations/'.$idB)
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/terminations/users/'.$empA->id.'/terminations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$empAToken])
            ->getJson('/v1/hcm/terminations/users/'.$empB->id.'/terminations')
            ->assertStatus(403);
    }

    public function test_termination_show_returns_404_when_not_found(): void
    {
        [$admin, $adminToken] = $this->login(true);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TERMINATION_NOT_FOUND');
    }

    public function test_termination_accepts_uuid_user_identifier_and_rejects_user_outside_active_company(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $created = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Workforce reduction',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'notes' => 'UUID payload accepted',
            ])
            ->assertStatus(201);

        $this->assertIsInt($created->json('data.id'));

        $outsider = User::query()->create([
            'name' => 'Outsider User',
            'email' => 'outsider@example.com',
            'password' => 'StrongPass1',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $outsider->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Cross tenant attempt',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'notes' => 'Should fail',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_termination_finalized_requires_settlement_snapshot_and_returns_computed_net_amount(): void
    {
        [$admin, $adminToken] = $this->login(true);
        [$emp, $empToken] = $this->login(false);

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Workforce reduction',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'status' => 'finalized',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $created = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->postJson('/v1/hcm/terminations', [
                'userId' => $emp->uuid,
                'department' => 'Finance',
                'terminationType' => 'Layoff',
                'reason' => 'Workforce reduction',
                'noticeDate' => '2026-04-01',
                'terminationDate' => '2026-04-30',
                'status' => 'finalized',
                'settlementPayrollPeriod' => '2026-05',
                'finalSalaryAmount' => 4500000,
                'finalAllowanceAmount' => 750000,
                'finalDeductionAmount' => 500000,
                'assetReturnNotes' => 'Laptop dan kartu akses sudah dikembalikan.',
                'clearanceNotes' => 'Settlement dibawa ke payroll periode terdekat setelah clearance selesai.',
            ])
            ->assertStatus(201);

        $id = $created->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
            ->getJson('/v1/hcm/terminations/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized')
            ->assertJsonPath('data.settlement.payrollPeriod', '2026-05')
            ->assertJsonPath('data.settlement.finalSalaryAmount', '4500000.00')
            ->assertJsonPath('data.settlement.finalAllowanceAmount', '750000.00')
            ->assertJsonPath('data.settlement.finalDeductionAmount', '500000.00')
            ->assertJsonPath('data.settlement.finalNetAmount', '4750000.00');
    }

    public function test_termination_settlement_preview_returns_compensation_and_clearance_and_finalized_store_auto_links_period(): void
    {
        Carbon::setTestNow('2026-04-19 10:00:00');

        try {
            [$admin, $adminToken] = $this->login(true);
            [$emp, $empToken] = $this->login(false);

            $profile = EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $emp->id],
                [
                    'company_id' => 1,
                    'base_salary' => 5_000_000,
                    'fixed_allowance' => 750_000,
                ]
            );

            $asset = Asset::query()->create([
                'company_id' => 1,
                'asset_code' => 'LAP-001',
                'name' => 'Laptop Kerja',
                'purchase_date' => '2026-03-01',
                'purchase_price' => 15_000_000,
                'condition' => 'good',
                'status' => 'assigned',
            ]);

            AssetAssignment::query()->create([
                'company_id' => 1,
                'asset_id' => $asset->id,
                'employee_id' => $profile->id,
                'assigned_date' => '2026-03-10',
                'condition_at_assign' => 'good',
                'active_token' => 'active',
                'notes' => 'Belum dikembalikan.',
            ]);

            $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->getJson('/v1/hcm/terminations/settlement-preview?userId='.$emp->uuid.'&terminationDate=2026-05-15')
                ->assertOk()
                ->assertJsonPath('data.resolvedPeriod.label', '2026-05')
                ->assertJsonPath('data.resolvedPeriod.isExisting', false)
                ->assertJsonPath('data.source', 'termination_policy_prorated')
                ->assertJsonPath('data.summary.finalSalaryAmount', '2419354.84')
                ->assertJsonPath('data.summary.finalAllowanceAmount', '362903.23')
                ->assertJsonPath('data.summary.finalDeductionAmount', '0.00')
                ->assertJsonPath('data.clearance.outstandingCount', 1)
                ->assertJsonPath('data.breakdown.0.componentCode', 'termination_prorated_salary')
                ->assertJsonPath('data.clearance.items.0.assetCode', 'LAP-001');

            $created = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->postJson('/v1/hcm/terminations', [
                    'userId' => $emp->uuid,
                    'department' => 'Finance',
                    'terminationType' => 'Layoff',
                    'reason' => 'Workforce reduction',
                    'noticeDate' => '2026-04-20',
                    'terminationDate' => '2026-05-15',
                    'status' => 'finalized',
                    'clearanceNotes' => 'Settlement akan dibayar pada payroll terdekat setelah seluruh clearance item selesai.',
                ])
                ->assertStatus(201);

            $id = $created->json('data.id');

            $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->getJson('/v1/hcm/terminations/'.$id)
                ->assertOk()
                ->assertJsonPath('data.status', 'finalized')
                ->assertJsonPath('data.settlement.payrollPeriod', '2026-05')
                ->assertJsonPath('data.settlement.payrollPeriodStatus', 'open')
                ->assertJsonPath('data.settlement.finalSalaryAmount', '2419354.84')
                ->assertJsonPath('data.settlement.finalAllowanceAmount', '362903.23')
                ->assertJsonPath('data.settlement.finalDeductionAmount', '0.00')
                ->assertJsonPath('data.settlement.finalNetAmount', '2782258.07')
                ->assertJsonPath('data.settlement.clearanceItems.0.assetCode', 'LAP-001')
                ->assertJsonPath('data.settlement.breakdown.0.componentCode', 'termination_prorated_salary');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_termination_clearance_item_can_be_returned_from_termination_context(): void
    {
        Carbon::setTestNow('2026-04-19 10:00:00');

        try {
            [$admin, $adminToken] = $this->login(true);
            [$emp, $empToken] = $this->login(false);

            $profile = EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $emp->id],
                [
                    'company_id' => 1,
                    'base_salary' => 5_000_000,
                    'fixed_allowance' => 750_000,
                ]
            );

            $asset = Asset::query()->create([
                'company_id' => 1,
                'asset_code' => 'LAP-002',
                'name' => 'Laptop Clearance',
                'purchase_date' => '2026-03-01',
                'purchase_price' => 15_000_000,
                'condition' => 'good',
                'status' => 'assigned',
            ]);

            $assignment = AssetAssignment::query()->create([
                'company_id' => 1,
                'asset_id' => $asset->id,
                'employee_id' => $profile->id,
                'assigned_date' => '2026-03-10',
                'condition_at_assign' => 'good',
                'active_token' => 'active',
                'notes' => 'Belum dikembalikan.',
            ]);

            $created = $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->postJson('/v1/hcm/terminations', [
                    'userId' => $emp->uuid,
                    'department' => 'Finance',
                    'terminationType' => 'Layoff',
                    'reason' => 'Workforce reduction',
                    'noticeDate' => '2026-04-20',
                    'terminationDate' => '2026-05-15',
                    'status' => 'finalized',
                    'clearanceNotes' => 'Asset harus kembali sebelum payroll final diposting.',
                ])
                ->assertStatus(201);

            $terminationId = $created->json('data.id');

            $this->withHeaders(['Authorization' => 'Bearer '.$adminToken])
                ->postJson('/v1/hcm/terminations/'.$terminationId.'/clearance-items/'.$assignment->id.'/return', [
                    'returnedDate' => '2026-04-19',
                    'conditionAtReturn' => 'good',
                    'notes' => 'Returned from termination workflow.',
                ])
                ->assertOk()
                ->assertJsonPath('data.termination.settlement.clearanceOutstandingCount', 0);

            $this->assertDatabaseHas('assets', [
                'id' => $asset->id,
                'status' => 'available',
            ]);

            $this->assertDatabaseHas('asset_assignments', [
                'id' => $assignment->id,
                'asset_id' => $asset->id,
                'active_token' => null,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }
}
