<?php

namespace Tests\Feature;

use App\Jobs\ProcessApprovedErasure;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeAiConsent;
use App\Models\EmployeeBiometricConsent;
use App\Models\EmployeeProfile;
use App\Models\ErasureRequest;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ErasureRequestApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token: string, companyId: int, user: User, profile: EmployeeProfile}
     */
    private function employeeContext(string $email = 'erasure.employee@example.com'): array
    {
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Erasure Employee',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        $companyId = (int) CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('company_id');
        $company = Company::query()->findOrFail($companyId);

        $profile = EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id, 'company_id' => $companyId],
            [
                'uuid' => (string) Str::uuid(),
                'user_uuid' => (string) $user->uuid,
                'company_uuid' => (string) $company->uuid,
            ]
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ])->assertOk();

        return [
            'token' => (string) $login->json('data.accessToken'),
            'companyId' => $companyId,
            'user' => $user,
            'profile' => $profile,
        ];
    }

    /**
     * @return array{token: string, companyId: int, user: User}
     */
    private function adminContext(): array
    {
        // Use base TestCase helper for proper RBAC setup
        $result = $this->createHcmAdminWithCompany([
            'email' => 'erasure.admin@example.com',
            'name' => 'Erasure Admin',
        ]);

        // Add user_management.manage permission (not in default set)
        $companyId = $result['company_id'];
        $permission = HcmPermission::query()->updateOrCreate(
            ['code' => 'user_management.manage'],
            ['module' => 'user_management', 'resource' => 'user_management', 'action' => 'manage', 'name' => 'Manage User Management', 'is_active' => true]
        );

        $role = HcmRole::query()->where('company_id', $companyId)->where('code', 'HCM_ADMIN')->first();
        if ($role) {
            $existingIds = $role->permissions()->pluck('hcm_permissions.id')->all();
            $role->permissions()->sync(array_merge($existingIds, [$permission->id]));
        }

        return [
            'token' => $result['token'],
            'companyId' => $companyId,
            'user' => $result['user'],
        ];
    }

    private function headers(string $token, int $companyId): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ];
    }

    // -------------------------------------------------------------------------
    // POST /v1/hcm/data-privacy/me/erasure-requests (employee request)
    // -------------------------------------------------------------------------

    public function test_employee_can_request_data_erasure(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/erasure-requests', [
                'reason' => 'I no longer work here and want my data removed.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('erasure_requests', [
            'subject_uuid' => (string) $ctx['user']->uuid,
            'company_id' => $ctx['companyId'],
            'status' => 'pending',
            'reason' => 'I no longer work here and want my data removed.',
        ]);
    }

    public function test_erasure_request_without_reason_is_allowed(): void
    {
        $ctx = $this->employeeContext();

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/erasure-requests', [])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_duplicate_pending_erasure_request_returns_422(): void
    {
        $ctx = $this->employeeContext();

        // Create first pending request
        ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) $ctx['user']->uuid,
            'company_id' => $ctx['companyId'],
            'status' => 'pending',
            'reason' => 'First request',
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/erasure-requests', [
                'reason' => 'Second request',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ERASURE_REQUEST_DUPLICATE');
    }

    public function test_employee_can_request_again_after_completed_erasure(): void
    {
        $ctx = $this->employeeContext();

        // Create a completed request
        ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) $ctx['user']->uuid,
            'company_id' => $ctx['companyId'],
            'status' => 'completed',
            'reason' => 'Old request',
            'completed_at' => now()->subMonth(),
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->postJson('/v1/hcm/data-privacy/me/erasure-requests', [
                'reason' => 'New request after completion',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_erasure_request_requires_authentication(): void
    {
        $this->postJson('/v1/hcm/data-privacy/me/erasure-requests', [
            'reason' => 'test',
        ])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // GET /v1/hcm/data-privacy/me/erasure-requests (employee list own)
    // -------------------------------------------------------------------------

    public function test_employee_can_list_own_erasure_requests(): void
    {
        $ctx = $this->employeeContext();

        ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) $ctx['user']->uuid,
            'company_id' => $ctx['companyId'],
            'status' => 'pending',
            'reason' => 'My request',
        ]);

        ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) $ctx['user']->uuid,
            'company_id' => $ctx['companyId'],
            'status' => 'rejected',
            'reason' => 'Old rejected',
            'admin_notes' => 'Not valid reason',
        ]);

        $this->withHeaders($this->headers($ctx['token'], $ctx['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/erasure-requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_employee_cannot_see_other_employee_erasure_requests(): void
    {
        $ctx1 = $this->employeeContext('erasure.emp1@example.com');
        $ctx2 = $this->employeeContext('erasure.emp2@example.com');

        // Create request for employee 1
        ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) $ctx1['user']->uuid,
            'company_id' => $ctx1['companyId'],
            'status' => 'pending',
        ]);

        // Employee 2 should see nothing (different company) or only their own
        $this->withHeaders($this->headers($ctx2['token'], $ctx2['companyId']))
            ->getJson('/v1/hcm/data-privacy/me/erasure-requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    // -------------------------------------------------------------------------
    // GET /v1/hcm/data-privacy/erasure-requests (admin list all)
    // -------------------------------------------------------------------------

    public function test_admin_can_list_all_erasure_requests(): void
    {
        $admin = $this->adminContext();
        $emp = $this->employeeContext();

        // Create requests in admin's company
        ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => $admin['companyId'],
            'status' => 'pending',
        ]);

        ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => $admin['companyId'],
            'status' => 'approved',
        ]);

        $this->withHeaders($this->headers($admin['token'], $admin['companyId']))
            ->getJson('/v1/hcm/data-privacy/erasure-requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_non_admin_forbidden_from_listing_all_erasure_requests(): void
    {
        $emp = $this->employeeContext();

        $this->withHeaders($this->headers($emp['token'], $emp['companyId']))
            ->getJson('/v1/hcm/data-privacy/erasure-requests')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    // -------------------------------------------------------------------------
    // POST /v1/hcm/data-privacy/erasure-requests/{uuid}/process (admin approve/reject)
    // -------------------------------------------------------------------------

    public function test_admin_can_approve_erasure_request(): void
    {
        Queue::fake();

        $admin = $this->adminContext();

        $erasureRequest = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => $admin['companyId'],
            'status' => 'pending',
            'reason' => 'Please delete my data',
        ]);

        $this->withHeaders($this->headers($admin['token'], $admin['companyId']))
            ->postJson('/v1/hcm/data-privacy/erasure-requests/'.$erasureRequest->uuid.'/process', [
                'action' => 'approve',
                'admin_notes' => 'Approved per policy.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('erasure_requests', [
            'uuid' => $erasureRequest->uuid,
            'status' => 'approved',
            'admin_notes' => 'Approved per policy.',
        ]);

        Queue::assertPushed(ProcessApprovedErasure::class);
    }

    public function test_admin_can_reject_erasure_request(): void
    {
        Queue::fake();

        $admin = $this->adminContext();

        $erasureRequest = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => $admin['companyId'],
            'status' => 'pending',
            'reason' => 'Please delete my data',
        ]);

        $this->withHeaders($this->headers($admin['token'], $admin['companyId']))
            ->postJson('/v1/hcm/data-privacy/erasure-requests/'.$erasureRequest->uuid.'/process', [
                'action' => 'reject',
                'admin_notes' => 'Request not valid — still active employee.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('erasure_requests', [
            'uuid' => $erasureRequest->uuid,
            'status' => 'rejected',
        ]);

        Queue::assertNotPushed(ProcessApprovedErasure::class);
    }

    public function test_admin_cannot_process_already_reviewed_request(): void
    {
        $admin = $this->adminContext();

        $erasureRequest = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => $admin['companyId'],
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        $this->withHeaders($this->headers($admin['token'], $admin['companyId']))
            ->postJson('/v1/hcm/data-privacy/erasure-requests/'.$erasureRequest->uuid.'/process', [
                'action' => 'approve',
            ])
            ->assertStatus(404); // firstOrFail on status=pending returns 404
    }

    public function test_process_erasure_validates_action_field(): void
    {
        $admin = $this->adminContext();

        $erasureRequest = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => $admin['companyId'],
            'status' => 'pending',
        ]);

        $this->withHeaders($this->headers($admin['token'], $admin['companyId']))
            ->postJson('/v1/hcm/data-privacy/erasure-requests/'.$erasureRequest->uuid.'/process', [
                'action' => 'invalid',
            ])
            ->assertStatus(422);
    }

    public function test_non_admin_forbidden_from_processing_erasure(): void
    {
        $emp = $this->employeeContext();

        $erasureRequest = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) $emp['user']->uuid,
            'company_id' => $emp['companyId'],
            'status' => 'pending',
        ]);

        $this->withHeaders($this->headers($emp['token'], $emp['companyId']))
            ->postJson('/v1/hcm/data-privacy/erasure-requests/'.$erasureRequest->uuid.'/process', [
                'action' => 'approve',
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_admin_cannot_process_erasure_from_other_company(): void
    {
        $admin = $this->adminContext();

        // Create request in a different company
        $otherCompany = Company::factory()->create();
        $erasureRequest = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => $otherCompany->id,
            'status' => 'pending',
        ]);

        $this->withHeaders($this->headers($admin['token'], $admin['companyId']))
            ->postJson('/v1/hcm/data-privacy/erasure-requests/'.$erasureRequest->uuid.'/process', [
                'action' => 'approve',
            ])
            ->assertStatus(404); // where clause filters by company_id, so firstOrFail returns 404
    }

    public function test_process_erasure_with_unknown_uuid_returns_404(): void
    {
        $admin = $this->adminContext();

        $this->withHeaders($this->headers($admin['token'], $admin['companyId']))
            ->postJson('/v1/hcm/data-privacy/erasure-requests/'.(string) Str::uuid().'/process', [
                'action' => 'approve',
            ])
            ->assertStatus(404);
    }
}
