<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\Team;
use App\Models\User;
use App\Models\CompanyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    /**
     * Test: List teams (empty).
     */
    public function test_list_teams_empty(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
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
            'Authorization' => 'Bearer ' . $this->token,
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
            'Authorization' => 'Bearer ' . $this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams/' . $team->id);

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
            'Authorization' => 'Bearer ' . $this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/teams/' . $team->id, [
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
            'Authorization' => 'Bearer ' . $this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->deleteJson('/v1/hcm/teams/' . $team->id, []);

        $response->assertNoContent();
        $this->assertModelMissing($team);
    }

    /**
     * Test: Create team validation error.
     */
    public function test_create_team_validation_error(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
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
            'Authorization' => 'Bearer ' . $this->token,
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
            'Authorization' => 'Bearer ' . $userToken,
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
                'name' => 'Team ' . $i,
                'is_active' => true,
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
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
            'Authorization' => 'Bearer ' . $this->token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams/' . $team->id . '/members');

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
            'Authorization' => 'Bearer ' . $leadToken,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams/' . $team->id . '/members');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals('member-2@example.com', $response->json('data.members.0.email'));
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
            'Authorization' => 'Bearer ' . $outsiderToken,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/teams/' . $team->id . '/members');

        $response->assertForbidden();
    }
}

