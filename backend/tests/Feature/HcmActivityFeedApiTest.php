<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmManualActivity;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
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

    /**
     * @return array{token:string,companyCode:string}
     */
    private function tenantOwnerContext(): array
    {
        $email = 'tenant-owner-activity@example.com';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Tenant Owner Activity',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $owner = User::query()->where('email', $email)->firstOrFail();

        $company = Company::factory()->create([
            'status' => 'active',
            'owner_user_id' => $owner->id,
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $permission = HcmPermission::query()->firstOrCreate(
            ['code' => 'attendance.admin'],
            [
                'module' => 'attendance',
                'resource' => 'attendance',
                'action' => 'admin',
                'name' => 'Admin Attendance Management',
                'description' => null,
                'is_active' => true,
            ]
        );

        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'TENANT_ACTIVITY_ADMIN',
            'name' => 'Tenant Activity Admin',
            'description' => 'Role used for activity feed test scope',
            'status' => 'active',
            'is_system' => false,
        ]);
        $role->permissions()->sync([(int) $permission->id]);

        HcmUserRole::query()->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
            'companyCode' => (string) $company->code,
        ])->assertOk();

        return [
            'token' => (string) $login->json('data.accessToken'),
            'companyCode' => (string) $company->code,
        ];
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

    public function test_global_admin_can_view_cross_tenant_activity_and_company_filter_options(): void
    {
        $token = $this->adminToken();

        $companyA = Company::factory()->create([
            'code' => 'ACTGLOBALA',
            'name' => 'Activity Global A',
            'status' => 'active',
        ]);
        $companyB = Company::factory()->create([
            'code' => 'ACTGLOBALB',
            'name' => 'Activity Global B',
            'status' => 'active',
        ]);

        HcmManualActivity::query()->create([
            'company_id' => $companyA->id,
            'title' => 'Audit trail company A',
            'activity_kind' => 'note',
            'status' => 'planned',
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ]);

        HcmManualActivity::query()->create([
            'company_id' => $companyB->id,
            'title' => 'Audit trail company B',
            'activity_kind' => 'note',
            'status' => 'planned',
            'created_by_user_id' => null,
            'updated_by_user_id' => null,
        ]);

        $feed = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/activity-feed?type=manual&perPage=50')
            ->assertOk()
            ->assertJsonPath('success', true);

        $titles = collect($feed->json('data'))->pluck('title')->all();
        $companyCodes = collect($feed->json('data'))->pluck('companyCode')->unique()->values()->all();

        $this->assertContains('Audit trail company A', $titles);
        $this->assertContains('Audit trail company B', $titles);
        $this->assertContains('ACTGLOBALA', $companyCodes);
        $this->assertContains('ACTGLOBALB', $companyCodes);

        $companies = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/activity-feed-companies')
            ->assertOk()
            ->assertJsonPath('success', true);

        $listedCodes = collect($companies->json('data'))->pluck('code')->all();
        $this->assertContains('ACTGLOBALA', $listedCodes);
        $this->assertContains('ACTGLOBALB', $listedCodes);
    }

    public function test_tenant_owner_can_crud_manual_activity_for_active_company_context(): void
    {
        $ctx = $this->tenantOwnerContext();

        $create = $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Code' => $ctx['companyCode'],
        ])->postJson('/v1/hcm/activity-manual', [
            'title' => 'Tenant owner created manual activity',
            'activityKind' => 'task',
            'statusType' => 'planned',
            'dueDate' => '2026-04-21',
        ])->assertStatus(201)
            ->assertJsonPath('success', true);

        $manualId = (int) $create->json('data.id');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Code' => $ctx['companyCode'],
        ])->getJson('/v1/hcm/activity-feed?type=manual')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.manualActivityId', $manualId)
            ->assertJsonPath('data.0.canEdit', true)
            ->assertJsonPath('data.0.canDelete', true);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Code' => $ctx['companyCode'],
        ])->putJson('/v1/hcm/activity-manual/'.$manualId, [
            'title' => 'Tenant owner updated manual activity',
            'activityKind' => 'meeting',
            'statusType' => 'in_progress',
            'dueDate' => '2026-04-23',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Code' => $ctx['companyCode'],
        ])->deleteJson('/v1/hcm/activity-manual/'.$manualId)
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_company_owner_membership_can_crud_manual_activity_without_explicit_rbac_role(): void
    {
        $email = 'tenant-owner-membership-only@example.com';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Tenant Owner Membership Only',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $owner = User::query()->where('email', $email)->firstOrFail();

        $company = Company::factory()->create([
            'status' => 'active',
            'owner_user_id' => $owner->id,
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
            'companyCode' => (string) $company->code,
        ])->assertOk();

        $token = (string) $login->json('data.accessToken');
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Company-Code' => (string) $company->code,
        ];

        $create = $this->withHeaders($headers)
            ->postJson('/v1/hcm/activity-manual', [
                'title' => 'Owner membership manual activity',
                'activityKind' => 'task',
                'statusType' => 'planned',
                'dueDate' => '2026-04-21',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $manualId = (int) $create->json('data.id');

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/activity-feed?type=manual')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.manualActivityId', $manualId)
            ->assertJsonPath('data.0.canEdit', true)
            ->assertJsonPath('data.0.canDelete', true);
    }
}
