<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmApprovalConfig;
use App\Models\HcmApprovalConfigApprover;
use App\Models\LeaveApproval;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\ApprovalConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalConfigService $service;
    private int $queryCount = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ApprovalConfigService::class);
    }

    // ===== HELPERS =====

    private function startQueryTracking(): void
    {
        $this->queryCount = 0;
        DB::listen(function ($query): void {
            ++$this->queryCount;
        });
    }

    private function assertQueryCountLessThan(int $max, string $label = 'operation'): void
    {
        $this->assertLessThanOrEqual(
            $max,
            $this->queryCount,
            "Query count exceeded limit for {$label}. Expected ≤{$max}, got {$this->queryCount}."
        );
    }

    private static int $companyCounter = 0;

    private function createCompanyWithUser(): array
    {
        self::$companyCounter++;
        $owner = User::query()->create([
            'name' => 'Owner '.self::$companyCounter,
            'email' => 'owner'.self::$companyCounter.uniqid().'@test.com',
            'password' => bcrypt('password'),
        ]);

        $company = Company::query()->create([
            'code' => 'TST' . fake()->unique()->randomNumber(4),
            'name' => 'Test Company',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => $owner->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        return ['company' => $company, 'owner' => $owner];
    }

    private function createApproverUsers(Company $company, int $count = 2): array
    {
        $users = [];
        for ($i = 1; $i <= $count; $i++) {
            $user = User::query()->create([
                'name' => "Approver {$i}",
                'email' => "approver{$i}@test.com",
                'password' => bcrypt('password'),
            ]);
            CompanyUser::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'status' => 'active',
            ]);
            $users[] = $user;
        }
        return $users;
    }

    private function createConfig(Company $company, string $module = 'leave', string $mode = 'sequence', array $approverUserIds = []): HcmApprovalConfig
    {
        return $this->service->upsertConfig((int) $company->id, $module, $mode, $approverUserIds);
    }

    // ========================
    // 1.1 — CRUD CONFIG
    // ========================

    public function test_get_config_returns_null_when_no_config(): void
    {
        $ctx = $this->createCompanyWithUser();
        $result = $this->service->getConfigForModule((int) $ctx['company']->id, 'leave');
        $this->assertNull($result);
    }

    public function test_get_config_returns_config_when_exists(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 2);
        $this->createConfig($ctx['company'], 'leave', 'sequence', [$approvers[0]->id, $approvers[1]->id]);

        $result = $this->service->getConfigForModule((int) $ctx['company']->id, 'leave');
        $this->assertNotNull($result);
        $this->assertSame('sequence', $result->approval_mode);
        $this->assertTrue($result->is_active);
        $this->assertCount(2, $result->approvers);
    }

    public function test_get_config_ignores_inactive_config(): void
    {
        $ctx = $this->createCompanyWithUser();
        $config = HcmApprovalConfig::query()->create([
            'company_id' => $ctx['company']->id,
            'module' => 'leave',
            'approval_mode' => 'sequence',
            'is_active' => false,
        ]);

        $result = $this->service->getConfigForModule((int) $ctx['company']->id, 'leave');
        $this->assertNull($result);
    }

    public function test_get_config_is_scoped_by_company(): void
    {
        $ctx1 = $this->createCompanyWithUser();
        $ctx2 = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx1['company'], 1);
        $this->createConfig($ctx1['company'], 'leave', 'sequence', [$approvers[0]->id]);

        $result = $this->service->getConfigForModule((int) $ctx2['company']->id, 'leave');
        $this->assertNull($result);
    }

    public function test_upsert_config_creates_new_config_with_approvers(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 3);

        $config = $this->service->upsertConfig(
            (int) $ctx['company']->id,
            'overtime',
            'simultaneous',
            [$approvers[0]->id, $approvers[1]->id, $approvers[2]->id]
        );

        $this->assertSame('overtime', $config->module);
        $this->assertSame('simultaneous', $config->approval_mode);
        $this->assertTrue($config->is_active);
        $this->assertCount(3, $config->approvers);
        $this->assertSame(1, $config->approvers[0]->sequence_order);
        $this->assertSame(2, $config->approvers[1]->sequence_order);
        $this->assertSame(3, $config->approvers[2]->sequence_order);
    }

    public function test_upsert_config_replaces_approvers_on_update(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 3);

        // Create with 2 approvers
        $config = $this->service->upsertConfig(
            (int) $ctx['company']->id,
            'leave',
            'sequence',
            [$approvers[0]->id, $approvers[1]->id]
        );
        $this->assertCount(2, $config->approvers);

        // Update with 1 approver (replacement)
        $updated = $this->service->upsertConfig(
            (int) $ctx['company']->id,
            'leave',
            'sequence',
            [$approvers[2]->id]
        );
        $this->assertCount(1, $updated->approvers);
        $this->assertSame($approvers[2]->id, $updated->approvers[0]->approver_user_id);
    }

    public function test_upsert_config_skips_non_existent_user_ids(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 1);

        $config = $this->service->upsertConfig(
            (int) $ctx['company']->id,
            'leave',
            'sequence',
            [$approvers[0]->id, 999999]
        );

        $this->assertCount(1, $config->approvers);
        $this->assertSame($approvers[0]->id, $config->approvers[0]->approver_user_id);
    }

    public function test_upsert_config_same_module_same_company_is_idempotent(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 2);

        $this->service->upsertConfig((int) $ctx['company']->id, 'leave', 'sequence', [$approvers[0]->id]);
        $this->service->upsertConfig((int) $ctx['company']->id, 'leave', 'simultaneous', [$approvers[0]->id, $approvers[1]->id]);

        $configs = HcmApprovalConfig::query()
            ->where('company_id', $ctx['company']->id)
            ->where('module', 'leave')
            ->count();
        $this->assertSame(1, $configs, 'Should only have one config per company+module');
    }

    // ========================
    // 1.2 — populateLeaveApprovals
    // ========================

    public function test_populate_leave_approvals_creates_rows_for_sequence_mode(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 3);
        $this->createConfig($ctx['company'], 'leave', 'sequence', [$approvers[0]->id, $approvers[1]->id, $approvers[2]->id]);

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(2)->toDateString(),
            'days' => 2,
            'status' => 'pending',
        ]);

        $result = $this->service->populateLeaveApprovals($leaveRequest);

        // Should return only level 1 for sequence mode
        $this->assertCount(1, $result);
        $this->assertSame($approvers[0]->id, $result->first()->id);

        // Verify DB rows
        $rows = LeaveApproval::query()->where('leave_request_id', $leaveRequest->id)->orderBy('level')->get();
        $this->assertCount(3, $rows);
        $this->assertSame($approvers[0]->id, $rows[0]->approver_id);
        $this->assertSame(1, $rows[0]->level);
        $this->assertSame($approvers[1]->id, $rows[1]->approver_id);
        $this->assertSame(2, $rows[1]->level);
        $this->assertSame($approvers[2]->id, $rows[2]->approver_id);
        $this->assertSame(3, $rows[2]->level);
        $this->assertSame('pending', $rows[0]->status);
    }

    public function test_populate_leave_approvals_returns_all_for_simultaneous_mode(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 2);
        $this->createConfig($ctx['company'], 'leave', 'simultaneous', [$approvers[0]->id, $approvers[1]->id]);

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);

        $result = $this->service->populateLeaveApprovals($leaveRequest);

        $this->assertCount(2, $result);
    }

    public function test_populate_leave_approvals_returns_empty_when_no_config(): void
    {
        $ctx = $this->createCompanyWithUser();
        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);

        $result = $this->service->populateLeaveApprovals($leaveRequest);
        $this->assertTrue($result->isEmpty());
    }

    public function test_populate_leave_approvals_clears_stale_rows(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 1);
        $this->createConfig($ctx['company'], 'leave', 'sequence', [$approvers[0]->id]);

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);

        // First population
        $this->service->populateLeaveApprovals($leaveRequest);
        $this->assertSame(1, LeaveApproval::query()->where('leave_request_id', $leaveRequest->id)->count());

        // Second population should clear + re-insert
        $this->service->populateLeaveApprovals($leaveRequest);
        $this->assertSame(1, LeaveApproval::query()->where('leave_request_id', $leaveRequest->id)->count());
    }

    // ========================
    // 1.3 — processApprovalDecision
    // ========================

    public function test_process_decision_approve_sequence_advances_chain(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 2);
        $this->createConfig($ctx['company'], 'leave', 'sequence', [$approvers[0]->id, $approvers[1]->id]);

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);
        $this->service->populateLeaveApprovals($leaveRequest);

        $result = $this->service->processApprovalDecision($leaveRequest, $approvers[0]->id, 'approved');

        $this->assertSame('pending', $result['status']);
        $this->assertCount(1, $result['next_approvers']);
        $this->assertSame($approvers[1]->id, $result['next_approvers']->first()->id);
    }

    public function test_process_decision_approve_last_sequence_level_fully_approved(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 1);
        $this->createConfig($ctx['company'], 'leave', 'sequence', [$approvers[0]->id]);

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);
        $this->service->populateLeaveApprovals($leaveRequest);

        $result = $this->service->processApprovalDecision($leaveRequest, $approvers[0]->id, 'approved');

        $this->assertSame('approved', $result['status']);
        $this->assertTrue($result['next_approvers']->isEmpty());
    }

    public function test_process_decision_approve_simultaneous_still_pending_waiting_others(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 2);
        $this->createConfig($ctx['company'], 'leave', 'simultaneous', [$approvers[0]->id, $approvers[1]->id]);

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);
        $this->service->populateLeaveApprovals($leaveRequest);

        $result = $this->service->processApprovalDecision($leaveRequest, $approvers[0]->id, 'approved');

        $this->assertSame('pending', $result['status']);
    }

    public function test_process_decision_approve_last_simultaneous_fully_approved(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 2);
        $this->createConfig($ctx['company'], 'leave', 'simultaneous', [$approvers[0]->id, $approvers[1]->id]);

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);
        $this->service->populateLeaveApprovals($leaveRequest);

        // First approver approves
        $this->service->processApprovalDecision($leaveRequest, $approvers[0]->id, 'approved');
        // Second approver approves — should trigger full approval
        $result = $this->service->processApprovalDecision($leaveRequest, $approvers[1]->id, 'approved');

        $this->assertSame('approved', $result['status']);
    }

    public function test_process_decision_reject_immediately_declined(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 2);
        $this->createConfig($ctx['company'], 'leave', 'sequence', [$approvers[0]->id, $approvers[1]->id]);

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);
        $this->service->populateLeaveApprovals($leaveRequest);

        $result = $this->service->processApprovalDecision($leaveRequest, $approvers[0]->id, 'declined');

        $this->assertSame('declined', $result['status']);
        $this->assertTrue($result['next_approvers']->isEmpty());
    }

    public function test_process_decision_no_config_fallback(): void
    {
        $ctx = $this->createCompanyWithUser();

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);

        $result = $this->service->processApprovalDecision($leaveRequest, $ctx['owner']->id, 'approved');
        $this->assertSame('approved', $result['status']);
    }

    public function test_process_decision_double_approve_is_idempotent(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 1);
        $this->createConfig($ctx['company'], 'leave', 'sequence', [$approvers[0]->id]);

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);
        $this->service->populateLeaveApprovals($leaveRequest);

        // First approve
        $this->service->processApprovalDecision($leaveRequest, $approvers[0]->id, 'approved');
        // Second approve same level — should not error
        $result = $this->service->processApprovalDecision($leaveRequest, $approvers[0]->id, 'approved');
        $this->assertSame('approved', $result['status']);
    }

    // ========================
    // 1.4 — resolveApproversToNotify
    // ========================

    public function test_resolve_approvers_sequence_returns_level_1_only(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 3);
        $this->createConfig($ctx['company'], 'overtime', 'sequence', [$approvers[0]->id, $approvers[1]->id, $approvers[2]->id]);

        $result = $this->service->resolveApproversToNotify((int) $ctx['company']->id, 'overtime');

        $this->assertCount(1, $result);
        $this->assertSame($approvers[0]->id, $result->first()->id);
    }

    public function test_resolve_approvers_simultaneous_returns_all(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 2);
        $this->createConfig($ctx['company'], 'overtime', 'simultaneous', [$approvers[0]->id, $approvers[1]->id]);

        $result = $this->service->resolveApproversToNotify((int) $ctx['company']->id, 'overtime');

        $this->assertCount(2, $result);
    }

    public function test_resolve_approvers_no_config_returns_empty(): void
    {
        $ctx = $this->createCompanyWithUser();
        $result = $this->service->resolveApproversToNotify((int) $ctx['company']->id, 'leave');
        $this->assertTrue($result->isEmpty());
    }

    public function test_resolve_approvers_empty_approvers_list_returns_empty(): void
    {
        $ctx = $this->createCompanyWithUser();
        HcmApprovalConfig::query()->create([
            'company_id' => $ctx['company']->id,
            'module' => 'leave',
            'approval_mode' => 'sequence',
            'is_active' => true,
        ]);

        $result = $this->service->resolveApproversToNotify((int) $ctx['company']->id, 'leave');
        $this->assertTrue($result->isEmpty());
    }

    // ========================
    // 1.5 — getEligibleApprovers
    // ========================

    public function test_eligible_approvers_returns_active_members(): void
    {
        $ctx = $this->createCompanyWithUser();
        $active = $this->createApproverUsers($ctx['company'], 2);

        $result = $this->service->getEligibleApprovers((int) $ctx['company']->id);

        $this->assertCount(3, $result); // owner + 2 approvers
    }

    public function test_eligible_approvers_excludes_inactive_members(): void
    {
        $ctx = $this->createCompanyWithUser();
        $active = $this->createApproverUsers($ctx['company'], 1);

        // Inactive user
        $inactive = User::query()->create([
            'name' => 'Inactive',
            'email' => 'inactive@test.com',
            'password' => bcrypt('password'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $inactive->id,
            'role' => 'admin',
            'status' => 'inactive',
        ]);

        $result = $this->service->getEligibleApprovers((int) $ctx['company']->id);

        $this->assertCount(2, $result); // owner + 1 active
    }

    public function test_eligible_approvers_search_by_name(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 3);

        $result = $this->service->getEligibleApprovers((int) $ctx['company']->id, 'Approver 1');

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Approver 1', $result->first()['name']);
    }

    public function test_eligible_approvers_search_by_email(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 2);

        $result = $this->service->getEligibleApprovers((int) $ctx['company']->id, 'approver1@');

        $this->assertCount(1, $result);
    }

    public function test_eligible_approvers_search_by_designation(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 1);

        EmployeeProfile::query()->create([
            'user_id' => $approvers[0]->id,
            'company_id' => $ctx['company']->id,
            'designation' => 'Manager HRD',
        ]);

        $result = $this->service->getEligibleApprovers((int) $ctx['company']->id, 'Manager');

        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    public function test_eligible_approvers_returns_max_20(): void
    {
        $ctx = $this->createCompanyWithUser();
        for ($i = 0; $i < 25; $i++) {
            $user = User::query()->create([
                'name' => "User {$i}",
                'email' => "user{$i}@test.com",
                'password' => bcrypt('password'),
            ]);
            CompanyUser::query()->create([
                'company_id' => $ctx['company']->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'status' => 'active',
            ]);
        }

        $result = $this->service->getEligibleApprovers((int) $ctx['company']->id);
        $this->assertLessThanOrEqual(20, $result->count());
    }

    public function test_eligible_approvers_scoped_by_company(): void
    {
        $ctx1 = $this->createCompanyWithUser();
        $ctx2 = $this->createCompanyWithUser();
        $this->createApproverUsers($ctx1['company'], 2);

        $result = $this->service->getEligibleApprovers((int) $ctx2['company']->id);
        $this->assertCount(1, $result); // only owner of company 2
    }

    // ========================
    // 1.6 — Query Count Regression
    // ========================

    public function test_get_config_query_count(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 1);
        $this->createConfig($ctx['company'], 'leave', 'sequence', [$approvers[0]->id]);

        $this->startQueryTracking();
        $this->service->getConfigForModule((int) $ctx['company']->id, 'leave');
        $this->assertQueryCountLessThan(3, 'getConfigForModule');
    }

    public function test_upsert_config_query_count(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 2);

        $this->startQueryTracking();
        $this->service->upsertConfig(
            (int) $ctx['company']->id,
            'leave',
            'sequence',
            [$approvers[0]->id, $approvers[1]->id]
        );
        $this->assertQueryCountLessThan(16, 'upsertConfig');
    }

    public function test_populate_leave_approvals_query_count(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 2);
        $this->createConfig($ctx['company'], 'leave', 'sequence', [$approvers[0]->id, $approvers[1]->id]);

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);

        $this->startQueryTracking();
        $this->service->populateLeaveApprovals($leaveRequest);
        $this->assertQueryCountLessThan(12, 'populateLeaveApprovals');
    }

    public function test_process_approval_decision_query_count(): void
    {
        $ctx = $this->createCompanyWithUser();
        $approvers = $this->createApproverUsers($ctx['company'], 1);
        $this->createConfig($ctx['company'], 'leave', 'sequence', [$approvers[0]->id]);

        $leaveRequest = LeaveRequest::query()->create([
            'company_id' => $ctx['company']->id,
            'user_id' => $ctx['owner']->id,
            'leave_type' => 'annual',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDay(1)->toDateString(),
            'days' => 1,
            'status' => 'pending',
        ]);
        $this->service->populateLeaveApprovals($leaveRequest);

        $this->startQueryTracking();
        $this->service->processApprovalDecision($leaveRequest, $approvers[0]->id, 'approved');
        $this->assertQueryCountLessThan(8, 'processApprovalDecision');
    }
}
