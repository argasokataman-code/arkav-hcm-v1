<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeProfile;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmRolePermission;
use App\Models\HcmUserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmTeamApiTest extends TestCase
{
    use RefreshDatabase;

    private ?Company $company = null;

    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupAdmin();
    }

    private function setupAdmin(): void
    {
        $result = $this->createHcmAdminWithCompany([
            'name' => 'HCM Admin',
            'email' => 'admin@example.com',
            'password' => 'AdminPass1',
        ]);
        $this->company = $result['company'];
        $this->token = $result['token'];
    }

    private function grantCompanyPermission(User $user, string $permissionCode, array $attributes): void
    {
        $permission = HcmPermission::query()->firstOrCreate(
            ['code' => $permissionCode],
            array_merge([
                'name' => ucwords(str_replace('.', ' ', $permissionCode)),
                'description' => null,
                'is_active' => true,
            ], $attributes)
        );

        $role = HcmRole::query()->firstOrCreate(
            ['company_id' => $this->company->id, 'code' => 'TEAM_LEAD_SCOPE_TEST'],
            ['name' => 'Team Lead Scope Test', 'status' => 'active']
        );

        HcmRolePermission::withoutTimestamps(function () use ($role, $permission): void {
            HcmRolePermission::query()->firstOrCreate([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'company_id' => $this->company->id,
            ]);
        });

        HcmUserRole::query()->updateOrCreate(
            ['user_id' => $user->id, 'company_id' => $this->company->id],
            ['role_id' => $role->id, 'status' => 'active']
        );
    }

    /**
     * Test: List teams (empty).
     */
    public function test_list_teams_empty(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams');

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertCount(0, $response->json('data'));
    }

    /**
     * Test: Create team.
     */
    public function test_create_team(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Engineering',
        ], ['code' => 'ENG']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/teams', [
            'name' => 'Backend Team',
            'department_id' => $dept->id,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Backend Team', $response->json('data.name'));
    }

    /**
     * Test: Get team.
     */
    public function test_show_team(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Sales',
        ], ['code' => 'SALES']);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Sales Team A',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams/'.$team->id);

        $response->assertOk();
        $this->assertEquals('Sales Team A', $response->json('data.name'));
    }

    /**
     * Test: Update team.
     */
    public function test_update_team(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Marketing',
        ], ['code' => 'MKT']);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Marketing Team',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/teams/'.$team->id, [
            'name' => 'Marketing Team Updated',
            'is_active' => false,
        ]);

        $response->assertOk();
        $this->assertEquals('Marketing Team Updated', $response->json('data.name'));
        $this->assertFalse($response->json('data.is_active'));
    }

    /**
     * Test: Delete team.
     */
    public function test_delete_team(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'HR',
        ], ['code' => 'HR']);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'HR Team',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->deleteJson('/v1/hcm/teams/'.$team->id, []);

        $response->assertNoContent();
        $this->assertModelMissing($team);
    }

    /**
     * Test: Create team validation error.
     */
    public function test_create_team_validation_error(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/teams', [
            'name' => '',  // Empty name
            'department_id' => 999,
        ]);

        $response->assertUnprocessable();
    }

    /**
     * Test: Team not found.
     */
    public function test_team_not_found(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams/99999');

        $response->assertNotFound();
    }

    /**
     * Test: Non-admin blocked.
     */
    public function test_non_admin_blocked(): void
    {
        // Create regular user
        $user = User::factory()->create(['email' => 'user@test.com']);
        CompanyUser::firstOrCreate([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'user@test.com',
            'password' => 'password',
            'companyCode' => $this->company->code,
        ]);

        if ($login->status() !== 200) {
            $this->markTestSkipped('Auth setup failed');

            return;
        }

        $userToken = $login->json('data.accessToken');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$userToken,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams');

        $response->assertForbidden();
    }

    /**
     * Test: List teams with pagination.
     */
    public function test_list_teams_pagination(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Ops',
        ], ['code' => 'OPS']);

        // Create 5 teams
        for ($i = 1; $i <= 5; $i++) {
            Team::create([
                'company_id' => $this->company->id,
                'department_id' => $dept->id,
                'name' => 'Team '.$i,
                'is_active' => true,
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams?page=1&perPage=3');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.page'));
        $this->assertEquals(3, $response->json('meta.perPage'));
        $this->assertGreaterThanOrEqual(5, $response->json('meta.total'));
    }

    public function test_admin_can_list_team_members(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Product',
        ], ['code' => 'PROD']);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Platform Squad',
            'is_active' => true,
        ]);

        $member = User::factory()->create(['email' => 'member-1@example.com']);
        CompanyUser::firstOrCreate([
            'user_id' => $member->id,
            'company_id' => $this->company->id,
        ]);

        EmployeeProfile::create([
            'company_id' => $this->company->id,
            'user_id' => $member->id,
            'team_id' => $team->id,
            'department_id' => $dept->id,
            'employment_status' => 'active',
            'nik' => 'EMP001',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams/'.$team->id.'/members');

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertEquals('Platform Squad', $response->json('data.team.name'));
        $this->assertCount(1, $response->json('data.members'));
    }

    public function test_team_lead_can_list_own_team_members(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Operations',
        ], ['code' => 'OPS2']);

        $teamLead = User::factory()->create(['email' => 'lead@example.com']);
        CompanyUser::firstOrCreate([
            'user_id' => $teamLead->id,
            'company_id' => $this->company->id,
        ]);

        EmployeeProfile::create([
            'company_id' => $this->company->id,
            'user_id' => $teamLead->id,
            'team_id' => null,
            'department_id' => $dept->id,
            'employment_status' => 'active',
            'nik' => 'LEAD001',
        ]);

        $this->grantCompanyPermission($teamLead, 'team.lead', [
            'module' => 'organization',
            'resource' => 'team',
            'action' => 'lead',
        ]);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'team_lead_id' => $teamLead->id,
            'name' => 'Ops Night Shift',
            'is_active' => true,
        ]);

        $member = User::factory()->create(['email' => 'member-2@example.com']);
        CompanyUser::firstOrCreate([
            'user_id' => $member->id,
            'company_id' => $this->company->id,
        ]);
        EmployeeProfile::create([
            'company_id' => $this->company->id,
            'user_id' => $member->id,
            'team_id' => $team->id,
            'department_id' => $dept->id,
            'employment_status' => 'active',
            'nik' => 'EMP002',
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'lead@example.com',
            'password' => 'password',
            'companyCode' => $this->company->code,
        ]);
        $login->assertOk();

        $leadToken = $login->json('data.accessToken');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$leadToken,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams/'.$team->id.'/members');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('member-2@example.com', $response->json('data.members.0.email'));
    }

    public function test_team_lead_without_team_lead_permission_cannot_list_team_members(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Operations East',
        ], ['code' => 'OPSE']);

        $teamLead = User::factory()->create(['email' => 'lead-no-perm@example.com']);
        CompanyUser::firstOrCreate([
            'user_id' => $teamLead->id,
            'company_id' => $this->company->id,
        ]);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'team_lead_id' => $teamLead->id,
            'name' => 'Ops East Team',
            'is_active' => true,
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'lead-no-perm@example.com',
            'password' => 'password',
            'companyCode' => $this->company->code,
        ]);
        $login->assertOk();

        $leadToken = $login->json('data.accessToken');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$leadToken,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams/'.$team->id.'/members');

        $response->assertForbidden();
    }

    public function test_non_lead_non_admin_cannot_list_team_members(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Finance',
        ], ['code' => 'FIN2']);

        $teamLead = User::factory()->create(['email' => 'lead-2@example.com']);
        CompanyUser::firstOrCreate([
            'user_id' => $teamLead->id,
            'company_id' => $this->company->id,
        ]);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'team_lead_id' => $teamLead->id,
            'name' => 'Finance Ops',
            'is_active' => true,
        ]);

        $outsider = User::factory()->create(['email' => 'outsider@example.com']);
        CompanyUser::firstOrCreate([
            'user_id' => $outsider->id,
            'company_id' => $this->company->id,
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'outsider@example.com',
            'password' => 'password',
            'companyCode' => $this->company->code,
        ]);
        $login->assertOk();

        $outsiderToken = $login->json('data.accessToken');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$outsiderToken,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams/'.$team->id.'/members');

        $response->assertForbidden();
    }

    public function test_admin_can_bulk_reassign_team_members(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'People Ops',
        ], ['code' => 'POPS']);

        $sourceTeam = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Ops A',
            'is_active' => true,
        ]);

        $targetTeam = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Ops B',
            'is_active' => true,
        ]);

        $userOne = User::factory()->create(['email' => 'bulk-1@example.com']);
        $userTwo = User::factory()->create(['email' => 'bulk-2@example.com']);
        CompanyUser::firstOrCreate(['user_id' => $userOne->id, 'company_id' => $this->company->id]);
        CompanyUser::firstOrCreate(['user_id' => $userTwo->id, 'company_id' => $this->company->id]);

        $employeeOne = EmployeeProfile::create([
            'company_id' => $this->company->id,
            'user_id' => $userOne->id,
            'team_id' => $sourceTeam->id,
            'department_id' => $dept->id,
            'employment_status' => 'active',
            'nik' => 'BULK001',
        ]);

        $employeeTwo = EmployeeProfile::create([
            'company_id' => $this->company->id,
            'user_id' => $userTwo->id,
            'team_id' => $sourceTeam->id,
            'department_id' => $dept->id,
            'employment_status' => 'active',
            'nik' => 'BULK002',
        ]);

        EmployeeAssignment::create([
            'employee_id' => $employeeOne->id,
            'department_id' => $dept->id,
            'team_id' => $sourceTeam->id,
            'is_primary' => true,
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        EmployeeAssignment::create([
            'employee_id' => $employeeTwo->id,
            'department_id' => $dept->id,
            'team_id' => $sourceTeam->id,
            'is_primary' => true,
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/teams/reassign-members', [
            'employee_ids' => [$employeeOne->id, $employeeTwo->id],
            'source_team_id' => $sourceTeam->id,
            'target_team_id' => $targetTeam->id,
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertEquals(2, $response->json('data.affected_count'));
        $this->assertEquals($targetTeam->id, $response->json('data.target_team.id'));

        $this->assertEquals($targetTeam->id, EmployeeProfile::query()->findOrFail($employeeOne->id)->team_id);
        $this->assertEquals($targetTeam->id, EmployeeProfile::query()->findOrFail($employeeTwo->id)->team_id);

        $this->assertEquals(
            $targetTeam->id,
            EmployeeAssignment::query()->where('employee_id', $employeeOne->id)->value('team_id')
        );

        $this->assertDatabaseHas('hcm_manual_activities', [
            'company_id' => $this->company->id,
            'activity_kind' => 'team_mutation',
            'status' => 'done',
        ]);
    }

    public function test_bulk_reassign_rejects_employee_outside_source_filter(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Operations Core',
        ], ['code' => 'OPSC']);

        $sourceTeam = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Ops Source',
            'is_active' => true,
        ]);

        $otherTeam = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Ops Other',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['email' => 'mismatch@example.com']);
        CompanyUser::firstOrCreate(['user_id' => $user->id, 'company_id' => $this->company->id]);

        $employee = EmployeeProfile::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'team_id' => $otherTeam->id,
            'department_id' => $dept->id,
            'employment_status' => 'active',
            'nik' => 'BULK003',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/teams/reassign-members', [
            'employee_ids' => [$employee->id],
            'source_team_id' => $sourceTeam->id,
            'target_team_id' => null,
        ]);

        $response->assertUnprocessable();
        $this->assertEquals('EMPLOYEE_SCOPE_MISMATCH', $response->json('error.code'));
    }

    public function test_bulk_reassign_rejects_inactive_target_team(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Ops Inactive Guard',
        ], ['code' => 'OPSIG']);

        $sourceTeam = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Ops Source Inactive Guard',
            'is_active' => true,
        ]);

        $inactiveTargetTeam = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Ops Target Inactive',
            'is_active' => false,
        ]);

        $user = User::factory()->create(['email' => 'inactive-target-member@example.com']);
        CompanyUser::firstOrCreate(['user_id' => $user->id, 'company_id' => $this->company->id]);

        $employee = EmployeeProfile::create([
            'company_id' => $this->company->id,
            'user_id' => $user->id,
            'team_id' => $sourceTeam->id,
            'department_id' => $dept->id,
            'employment_status' => 'active',
            'nik' => 'BULK004',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/teams/reassign-members', [
            'employee_ids' => [$employee->id],
            'source_team_id' => $sourceTeam->id,
            'target_team_id' => $inactiveTargetTeam->id,
        ]);

        $response->assertUnprocessable();
        $this->assertEquals('TEAM_INACTIVE_NOT_ASSIGNABLE', $response->json('error.code'));
    }

    public function test_non_admin_cannot_bulk_reassign_members(): void
    {
        $user = User::factory()->create(['email' => 'reassign-user@test.com']);
        CompanyUser::firstOrCreate([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'reassign-user@test.com',
            'password' => 'password',
            'companyCode' => $this->company->code,
        ]);
        $login->assertOk();

        $userToken = $login->json('data.accessToken');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$userToken,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/teams/reassign-members', [
            'employee_ids' => [1],
            'target_team_id' => null,
        ]);

        $response->assertForbidden();
    }

    public function test_delete_team_with_members_returns_409(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Support 409',
        ], ['code' => 'SUPP409']);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Support Team 409',
            'is_active' => true,
        ]);

        $member = User::factory()->create(['email' => 'support-409@example.com']);
        CompanyUser::firstOrCreate([
            'user_id' => $member->id,
            'company_id' => $this->company->id,
        ]);
        EmployeeProfile::create([
            'company_id' => $this->company->id,
            'user_id' => $member->id,
            'team_id' => $team->id,
            'department_id' => $dept->id,
            'employment_status' => 'active',
            'nik' => 'SUPP409',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->deleteJson('/v1/hcm/teams/'.$team->id);

        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
            'error' => ['code' => 'TEAM_DELETION_BLOCKED'],
        ]);
        $this->assertModelExists($team);
    }

    public function test_create_team_duplicate_name_returns_422(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Eng Duplicate',
        ], ['code' => 'ENGDUP']);

        Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Platform Team',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/teams', [
            'name' => 'Platform Team',
            'department_id' => $dept->id,
            'is_active' => true,
        ]);

        $response->assertUnprocessable();
    }

    public function test_update_team_duplicate_name_returns_422(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Mkt Duplicate',
        ], ['code' => 'MKTDUP']);

        Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Social Media Team',
            'is_active' => true,
        ]);

        $target = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Brand Team',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/teams/'.$target->id, [
            'name' => 'Social Media Team',
        ]);

        $response->assertUnprocessable();
    }

    public function test_show_team_by_uuid(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Finance UUID',
        ], ['code' => 'FINUUID']);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Finance Team UUID',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams/'.$team->uuid);

        $response->assertOk();
        $this->assertEquals($team->name, $response->json('data.name'));
    }

    public function test_update_team_by_uuid(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'HR UUID',
        ], ['code' => 'HRUUID']);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'HR Team UUID',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/teams/'.$team->uuid, [
            'name' => 'HR Team UUID Updated',
        ]);

        $response->assertOk();
        $this->assertEquals('HR Team UUID Updated', $response->json('data.name'));
    }

    public function test_delete_team_by_uuid(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Ops UUID',
        ], ['code' => 'OPSUUID']);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Ops Team UUID',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->deleteJson('/v1/hcm/teams/'.$team->uuid);

        $response->assertNoContent();
        $this->assertModelMissing($team);
    }

    public function test_cross_company_isolation_list_teams(): void
    {
        $deptA = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Cross List Dept',
        ], ['code' => 'CRLIST']);

        Team::create([
            'company_id' => $this->company->id,
            'department_id' => $deptA->id,
            'name' => 'Team Alpha',
            'is_active' => true,
        ]);

        $resultB = $this->createHcmAdminWithCompany([
            'name' => 'Admin B List',
            'email' => 'admin-b-list-'.rand(1000, 9999).'@example.com',
            'password' => 'AdminPass1',
        ]);

        $responseB = $this->withHeaders([
            'Authorization' => 'Bearer '.$resultB['token'],
            'X-Company-Id' => (string) $resultB['company']->id,
        ])->getJson('/v1/hcm/teams');

        $responseB->assertOk();
        $teamNames = collect($responseB->json('data'))->pluck('name')->all();
        $this->assertNotContains('Team Alpha', $teamNames);
    }

    public function test_cross_company_cannot_access_other_company_team_by_id(): void
    {
        $deptA = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Cross Access Dept',
        ], ['code' => 'CRACCESS']);

        $teamInA = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $deptA->id,
            'name' => 'Private Team A',
            'is_active' => true,
        ]);

        $resultB = $this->createHcmAdminWithCompany([
            'name' => 'Admin B Access',
            'email' => 'admin-b-access-'.rand(1000, 9999).'@example.com',
            'password' => 'AdminPass1',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$resultB['token'],
            'X-Company-Id' => (string) $resultB['company']->id,
        ])->getJson('/v1/hcm/teams/'.$teamInA->id);

        $response->assertNotFound();
    }

    public function test_bulk_reassign_exceeds_max_employees(): void
    {
        $employeeIds = range(1, 201);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/teams/reassign-members', [
            'employee_ids' => $employeeIds,
            'target_team_id' => null,
        ]);

        $response->assertUnprocessable();
    }

    public function test_teams_search_no_results_returns_empty(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams?search=ZZZZNONEXISTENT');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_create_team_rejects_cross_company_team_lead(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Cross Lead Dept',
        ], ['code' => 'CRLEAD']);

        $otherCompany = Company::create(['name' => 'Other Co', 'code' => 'OTHER_'.rand(1000, 9999)]);
        $otherUser = User::factory()->create(['email' => 'other-user-'.rand(1000, 9999).'@example.com']);
        CompanyUser::firstOrCreate([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/teams', [
            'name' => 'Cross Lead Team',
            'department_id' => $dept->id,
            'team_lead_id' => $otherUser->id,
            'is_active' => true,
        ]);

        $response->assertUnprocessable();
    }

    public function test_create_team_without_department_succeeds(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/teams', [
            'name' => 'Cross Department Team',
            'department_id' => null,
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertTrue($response->json('success'));
        $this->assertNull($response->json('data.department_id'));
    }

    public function test_update_team_clear_department_succeeds(): void
    {
        $dept = Department::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Clear Dept',
        ], ['code' => 'CLRDEPT']);

        $team = Team::create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Team With Dept',
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/teams/'.$team->id, [
            'department_id' => null,
        ]);

        $response->assertOk();
        $this->assertNull($response->json('data.department_id'));
    }
}
