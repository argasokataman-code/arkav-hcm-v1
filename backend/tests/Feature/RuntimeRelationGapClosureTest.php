<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmRolePermission;
use App\Models\HcmTrainer;
use App\Models\HcmTraining;
use App\Models\HcmTrainingType;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RuntimeRelationGapClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_uuid_is_synced_for_runtime_rows_created_after_gap_closure(): void
    {
        $company = Company::query()->create([
            'code' => 'gap_closure_company',
            'name' => 'Gap Closure Company',
            'legal_name' => 'Gap Closure Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $user = User::factory()->create();

        $ticket = Ticket::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'code' => 'GAP-TICKET-001',
            'subject' => 'Runtime FK closure ticket',
            'description' => 'Verifies company_uuid backfill on write.',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $trainingType = HcmTrainingType::query()->create([
            'company_id' => $company->id,
            'name' => 'Gap Closure Type',
            'description' => 'Type for UUID gap closure test',
            'is_active' => true,
        ]);

        $trainer = HcmTrainer::query()->create([
            'company_id' => $company->id,
            'name' => 'Gap Closure Trainer',
            'email' => 'gap-closure-trainer@example.com',
            'is_active' => true,
        ]);

        $training = HcmTraining::query()->create([
            'company_id' => $company->id,
            'training_type_id' => $trainingType->id,
            'trainer_id' => $trainer->id,
            'trainer_name' => $trainer->name,
            'start_date' => '2026-04-20',
            'end_date' => '2026-04-21',
            'description' => 'Training record for company_uuid sync validation.',
            'cost_cents' => 0,
            'status' => 'active',
        ]);

        $permission = HcmPermission::query()->create([
            'code' => 'gap.closure.manage',
            'module' => 'gap_closure',
            'resource' => 'gap_closure',
            'action' => 'manage',
            'name' => 'Gap Closure Manage',
            'description' => 'Permission for relation closure regression test',
            'is_active' => true,
        ]);

        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'GAP_CLOSURE_ADMIN',
            'name' => 'Gap Closure Admin',
            'description' => 'Role used to verify hcm_role_permissions company_uuid sync.',
            'status' => 'active',
            'is_system' => false,
        ]);

        $role->syncPermissionsForCompany([$permission->id]);

        $rolePermission = HcmRolePermission::query()->where('role_id', $role->id)->firstOrFail();

        $this->assertSame($company->uuid, $ticket->fresh()->company_uuid);
        $this->assertSame($company->uuid, $trainingType->fresh()->company_uuid);
        $this->assertSame($company->uuid, $trainer->fresh()->company_uuid);
        $this->assertSame($company->uuid, $training->fresh()->company_uuid);
        $this->assertSame($company->uuid, $rolePermission->company_uuid);
    }

    public function test_invalid_settlement_payroll_period_id_is_rejected_by_database_constraint(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite alter-table foreign key enforcement is not reliable for this migration regression test.');
        }

        $company = Company::query()->create([
            'code' => 'gap_closure_company_2',
            'name' => 'Gap Closure Company 2',
            'legal_name' => 'Gap Closure Company 2 LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('hcm_terminations')->insert([
            'company_id' => $company->id,
            'company_uuid' => $company->uuid,
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'termination_type' => 'layoff',
            'reason' => 'FK guard regression test',
            'notice_date' => '2026-04-20',
            'termination_date' => '2026-04-30',
            'status' => 'pending',
            'settlement_payroll_period_id' => 999999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
