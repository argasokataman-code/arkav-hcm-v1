<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmSyncTeamRoleDefaultsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_backfills_team_lead_and_manager_default_permissions_for_existing_tenant(): void
    {
        $company = Company::query()->firstOrCreate(
            ['code' => 'SYNC_TEAM_ROLE_DEFAULTS'],
            ['name' => 'Sync Team Role Defaults', 'domain' => 'sync-team-role-defaults.local']
        );

        HcmPermission::query()->create([
            'code' => 'employee.view',
            'module' => 'employee',
            'resource' => 'employee',
            'action' => 'view',
            'name' => 'View Employees',
            'is_active' => true,
        ]);
        HcmPermission::query()->create([
            'code' => 'team.lead',
            'module' => 'organization',
            'resource' => 'team',
            'action' => 'lead',
            'name' => 'Lead Team Scope',
            'is_active' => true,
        ]);
        HcmPermission::query()->create([
            'code' => 'report.view',
            'module' => 'report',
            'resource' => 'report',
            'action' => 'view',
            'name' => 'View Reports',
            'is_active' => true,
        ]);

        HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'MANAGER',
            'name' => 'Manager',
            'status' => 'active',
            'is_system' => false,
        ]);

        $this->artisan('hcm:sync-team-role-defaults', [
            '--company_id' => [(string) $company->id],
        ])->assertExitCode(0);

        $teamLeadRoleId = (int) HcmRole::query()
            ->where('company_id', $company->id)
            ->where('code', 'TEAM_LEAD')
            ->value('id');

        $managerRoleId = (int) HcmRole::query()
            ->where('company_id', $company->id)
            ->where('code', 'MANAGER')
            ->value('id');

        $employeeViewPermissionId = (int) HcmPermission::query()->where('code', 'employee.view')->value('id');
        $teamLeadPermissionId = (int) HcmPermission::query()->where('code', 'team.lead')->value('id');
        $reportViewPermissionId = (int) HcmPermission::query()->where('code', 'report.view')->value('id');

        $this->assertDatabaseHas('hcm_role_permissions', [
            'role_id' => $teamLeadRoleId,
            'permission_id' => $employeeViewPermissionId,
            'company_id' => $company->id,
        ]);
        $this->assertDatabaseHas('hcm_role_permissions', [
            'role_id' => $teamLeadRoleId,
            'permission_id' => $teamLeadPermissionId,
            'company_id' => $company->id,
        ]);
        $this->assertDatabaseHas('hcm_role_permissions', [
            'role_id' => $teamLeadRoleId,
            'permission_id' => $reportViewPermissionId,
            'company_id' => $company->id,
        ]);

        $this->assertDatabaseHas('hcm_role_permissions', [
            'role_id' => $managerRoleId,
            'permission_id' => $employeeViewPermissionId,
            'company_id' => $company->id,
        ]);
        $this->assertDatabaseHas('hcm_role_permissions', [
            'role_id' => $managerRoleId,
            'permission_id' => $teamLeadPermissionId,
            'company_id' => $company->id,
        ]);
    }
}
