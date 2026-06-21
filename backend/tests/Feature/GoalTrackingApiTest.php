<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class GoalTrackingApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, string $name, ?string $designation = null): array
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        if ($designation !== null) {
            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['designation' => $designation]
            );
        }

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return [
            'user' => $user,
            'token' => (string) $login->json('data.accessToken'),
        ];
    }

    public function test_goal_types_admin_crud_and_employee_forbidden_mutation(): void
    {
        $admin = $this->login('goal-admin@example.com', 'Goal Admin', 'HR Admin');
        $employee = $this->login('goal-employee@example.com', 'Goal Employee', 'Staff');

        $hAdmin = ['Authorization' => 'Bearer '.$admin['token']];
        $hEmp = ['Authorization' => 'Bearer '.$employee['token']];

        // Everyone can list.
        $this->withHeaders($hEmp)->getJson('/v1/hcm/performance/goal-types')
            ->assertOk()->assertJsonPath('success', true);

        // Employee cannot mutate.
        $this->withHeaders($hEmp)->postJson('/v1/hcm/performance/goal-types', [
            'name' => 'Learning',
            'description' => 'desc',
            'isActive' => true,
        ])->assertStatus(403);

        // Admin creates.
        $create = $this->withHeaders($hAdmin)->postJson('/v1/hcm/performance/goal-types', [
            'name' => 'Learning',
            'description' => 'desc',
            'isActive' => true,
        ])->assertStatus(201)->assertJsonPath('success', true);
        $typeId = (int) $create->json('data.id');

        // Admin updates.
        $this->withHeaders($hAdmin)->putJson("/v1/hcm/performance/goal-types/{$typeId}", [
            'name' => 'Learning Updated',
            'description' => null,
            'isActive' => false,
        ])->assertOk()->assertJsonPath('success', true);

        // Admin deletes.
        $this->withHeaders($hAdmin)->deleteJson("/v1/hcm/performance/goal-types/{$typeId}")
            ->assertOk()->assertJsonPath('success', true);
    }

    public function test_employee_can_crud_own_goals_manager_can_update_team_admin_can_view_all(): void
    {
        $admin = $this->login('goal-admin2@example.com', 'Goal Admin Two', 'HR Admin');
        $manager = $this->login('goal-manager@example.com', 'Goal Manager', 'Manager');
        $employee = $this->login('goal-employee2@example.com', 'Goal Employee Two', 'Staff');
        $otherEmployee = $this->login('goal-employee3@example.com', 'Goal Employee Three', 'Staff');

        // Link employees to manager.
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee['user']->id],
            ['designation' => 'Staff', 'manager_user_id' => $manager['user']->id]
        );
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $otherEmployee['user']->id],
            ['designation' => 'Staff', 'manager_user_id' => $manager['user']->id]
        );

        $hAdmin = ['Authorization' => 'Bearer '.$admin['token']];
        $hMgr = ['Authorization' => 'Bearer '.$manager['token']];
        $hEmp = ['Authorization' => 'Bearer '.$employee['token']];
        $hOther = ['Authorization' => 'Bearer '.$otherEmployee['token']];

        // Admin creates goal type (active).
        $type = $this->withHeaders($hAdmin)->postJson('/v1/hcm/performance/goal-types', [
            'name' => 'Development',
            'description' => null,
            'isActive' => true,
        ])->assertStatus(201);
        $typeId = (int) $type->json('data.id');

        // Employee creates own goal.
        $goal = $this->withHeaders($hEmp)->postJson('/v1/hcm/performance/goals', [
            'goalTypeId' => $typeId,
            'subject' => 'Improve programming skills',
            'targetAchievement' => 'Complete course',
            'startDate' => '2026-01-01',
            'endDate' => '2026-03-01',
            'description' => 'desc',
            'status' => 'active',
            'progressPercent' => 10,
        ])->assertStatus(201)->assertJsonPath('success', true);
        $goalId = (int) $goal->json('data.id');

        // Other employee cannot delete employee's goal.
        $this->withHeaders($hOther)->deleteJson("/v1/hcm/performance/goals/{$goalId}")
            ->assertStatus(403);

        // Manager can list team and see employee goal.
        $team = $this->withHeaders($hMgr)->getJson('/v1/hcm/performance/goals?scope=team&perPage=50')
            ->assertOk()->assertJsonPath('success', true);
        $this->assertTrue(collect($team->json('data'))->pluck('id')->contains($goalId));

        // Manager updates employee goal progress.
        $this->withHeaders($hMgr)->putJson("/v1/hcm/performance/goals/{$goalId}", [
            'progressPercent' => 50,
            'status' => 'active',
        ])->assertOk()->assertJsonPath('success', true);

        // Employee can update own goal to completed.
        $this->withHeaders($hEmp)->putJson("/v1/hcm/performance/goals/{$goalId}", [
            'progressPercent' => 100,
            'status' => 'completed',
        ])->assertOk()->assertJsonPath('success', true);

        $meList = $this->withHeaders($hEmp)->getJson('/v1/hcm/performance/goals?scope=me&perPage=50')
            ->assertOk()->assertJsonPath('success', true);
        $updated = collect($meList->json('data'))->firstWhere('id', $goalId);
        $this->assertSame('completed', $updated['status'] ?? null);

        // Admin can view all (scope=all).
        $all = $this->withHeaders($hAdmin)->getJson('/v1/hcm/performance/goals?scope=all&perPage=50')
            ->assertOk()->assertJsonPath('success', true);
        $this->assertTrue(collect($all->json('data'))->pluck('id')->contains($goalId));
    }
}
