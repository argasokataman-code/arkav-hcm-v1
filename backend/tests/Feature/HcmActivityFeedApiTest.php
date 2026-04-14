<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\HcmUserRoleAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmActivityFeedApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $adminEmail = (string) config('hcm.admin_email', 'qa.login@example.com');

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Activity Admin',
            'email' => $adminEmail,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $adminEmail)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employment_status' => 'active',
                'base_salary' => 0,
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $adminEmail,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function employeeToken(): string
    {
        $email = 'activity-employee@example.com';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Activity Employee',
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

    public function test_hcm_admin_can_fetch_activity_feed_from_real_records(): void
    {
        $token = $this->adminToken();

        $me = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/identity/auth/me')
            ->assertOk();

        $companyId = (int) $me->json('data.activeCompany.id');
        $adminId = (int) $me->json('data.id');

        $target = User::factory()->create([
            'email' => 'activity-target@example.com',
        ]);

        HcmUserRoleAudit::query()->create([
            'company_id' => $companyId,
            'actor_user_id' => $adminId,
            'target_user_id' => $target->id,
            'role_id' => null,
            'action' => 'assigned',
            'notes' => 'Assigned HR Access',
            'metadata' => ['source' => 'test'],
            'created_at' => now(),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/activity-feed?type=user_access')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.activityType', 'user_access')
            ->assertJsonPath('data.0.sourceType', 'system')
            ->assertJsonPath('data.0.statusType', 'assigned')
            ->assertJsonPath('data.0.readOnlyReason', 'System-generated activity. Read-only.')
            ->assertJsonPath('data.0.title', 'Assigned HR Access');
    }

    public function test_non_admin_cannot_access_activity_feed(): void
    {
        $token = $this->employeeToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/activity-feed')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_hcm_admin_can_crud_manual_activity_while_feed_marks_it_editable(): void
    {
        $token = $this->adminToken();

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/activity-manual', [
                'title' => 'Follow up onboarding docs',
                'activityKind' => 'task',
                'statusType' => 'planned',
                'dueDate' => '2026-04-20',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $manualId = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/activity-feed?type=manual')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.manualActivityId', $manualId)
            ->assertJsonPath('data.0.canEdit', true)
            ->assertJsonPath('data.0.canDelete', true)
            ->assertJsonPath('data.0.sourceType', 'manual')
            ->assertJsonPath('data.0.readOnlyReason', null)
            ->assertJsonPath('data.0.statusType', 'planned');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/activity-manual/'.$manualId, [
                'title' => 'Follow up onboarding docs - updated',
                'activityKind' => 'meeting',
                'statusType' => 'in_progress',
                'dueDate' => '2026-04-22',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/activity-feed?type=manual&statusType=in_progress')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.manualActivityId', $manualId)
            ->assertJsonPath('data.0.title', 'Follow up onboarding docs - updated')
            ->assertJsonPath('data.0.activityKind', 'meeting')
            ->assertJsonPath('data.0.statusType', 'in_progress');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson('/v1/hcm/activity-manual/'.$manualId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/activity-feed?type=manual')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 0);
    }
}
