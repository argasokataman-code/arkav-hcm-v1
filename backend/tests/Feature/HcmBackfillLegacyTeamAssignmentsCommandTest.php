<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmBackfillLegacyTeamAssignmentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_dry_run_does_not_mutate_employee_profile(): void
    {
        [$company, $department] = $this->seedCompanyAndDepartment('DRY');
        $profile = $this->seedLegacyProfile($company, $department, 'Customer Service 24h');

        Team::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'name' => 'Customer Service 24h',
            'is_active' => true,
        ]);

        $this->artisan('hcm:teams-backfill-legacy', [
            '--company-id' => $company->id,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $profile->refresh();
        $this->assertNull($profile->team_id);
        $this->assertEquals('Customer Service 24h', $profile->team);
    }

    public function test_backfill_can_create_missing_team_and_assign_employee(): void
    {
        [$company, $department] = $this->seedCompanyAndDepartment('REAL');
        $profile = $this->seedLegacyProfile($company, $department, 'Ops Midnight Shift');

        EmployeeAssignment::query()->create([
            'employee_id' => $profile->id,
            'department_id' => $department->id,
            'is_primary' => true,
            'start_date' => now()->subWeek()->toDateString(),
        ]);

        $this->artisan('hcm:teams-backfill-legacy', [
            '--company-id' => $company->id,
            '--create-missing' => true,
        ])->assertExitCode(0);

        $profile->refresh();
        $this->assertNotNull($profile->team_id);

        $team = Team::query()->findOrFail($profile->team_id);
        $this->assertEquals('Ops Midnight Shift', $team->name);

        $this->assertEquals(
            $team->id,
            EmployeeAssignment::query()->where('employee_id', $profile->id)->value('team_id')
        );
    }

    /**
     * @return array{Company, Department}
     */
    private function seedCompanyAndDepartment(string $suffix): array
    {
        $company = Company::factory()->create([
            'name' => 'Team Backfill '.$suffix,
            'code' => 'team_backfill_'.strtolower($suffix),
        ]);

        $department = Department::query()->create([
            'company_id' => $company->id,
            'name' => 'Operations '.$suffix,
            'code' => 'OPS_'.$suffix,
            'is_active' => true,
        ]);

        return [$company, $department];
    }

    private function seedLegacyProfile(Company $company, Department $department, string $legacyTeam): EmployeeProfile
    {
        $user = User::factory()->create();
        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employment_status' => 'active',
            'nik' => 'LEGACY-'.(string) $user->id,
            'team' => $legacyTeam,
            'team_id' => null,
        ]);
    }
}
