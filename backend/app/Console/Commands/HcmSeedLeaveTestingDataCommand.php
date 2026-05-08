<?php

namespace App\Console\Commands;

use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeProfile;
use App\Models\HcmLeaveCustomPolicy;
use App\Models\HcmLeaveTypeSetting;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeaveApproval;
use App\Models\LeaveLedger;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyAssignment;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class HcmSeedLeaveTestingDataCommand extends Command
{
    protected $signature = 'hcm:leave-seed-testing-data
        {--fresh : Delete existing leave-related data before seeding}
        {--password=StrongPass1 : Password for seeded users}
        {--company-id= : Optional company id for foundation rows}';

    protected $description = 'Seed clean and detailed leave testing data across legacy and foundation schemas.';

    public function handle(): int
    {
        if (! $this->requiredTablesExist()) {
            $this->warn('Required leave tables are not available. Run migrations first.');

            return self::SUCCESS;
        }

        $companyIdOption = $this->option('company-id');
        $companyId = ($companyIdOption === null || $companyIdOption === '') ? null : (int) $companyIdOption;

        // leave_requests.company_id is NOT NULL — auto-provision a seed company when none is provided.
        if ($companyId === null) {
            $company = \App\Models\Company::query()->firstOrCreate(
                ['code' => 'LEAVE_SEED_COMPANY'],
                ['name' => 'Leave Seed Company', 'domain' => 'leave-seed.local']
            );
            $companyId = $company->id;
        }

        $password = (string) $this->option('password');
        $fresh = (bool) $this->option('fresh');

        DB::transaction(function () use ($fresh, $password, $companyId): void {
            if ($fresh) {
                $this->wipeLeaveData();
            }

            $users = $this->seedUsers($password);
            [$leaveTypes, $legacyCodes] = $this->seedLeaveTypesAndLegacySettings($companyId);
            $policies = $this->seedPolicies($leaveTypes, $companyId);
            $this->seedCustomPolicies($users, $leaveTypes, $policies);
            $this->seedPolicyAssignments($users, $policies, $companyId);
            $this->seedBalances($users, $leaveTypes, $companyId);
            $this->seedRequestsApprovalsAndLedger($users, $leaveTypes, $policies, $companyId);
            $this->seedHolidays($companyId);

            // Keep legacy code map alive for consistent custom policy visibility in old settings menu.
            foreach ($legacyCodes as $legacyCode => $foundationCode) {
                HcmLeaveTypeSetting::query()
                    ->where('code', $legacyCode)
                    ->update(['leave_type_id' => $leaveTypes[$foundationCode]->id]);
            }
        });

        $this->info('Seed data testing cuti selesai.');
        $this->line('Users: admin.hcm@example.com, employee1@example.com, employee2@example.com, manager.ops@example.com');
        $this->line('Password: '.$password);
        $this->line('Data seeded: leave_types, leave_policies, leave_policy_assignments, employee_leave_balances, leave_requests, leave_approvals, leave_ledger, hcm_leave_type_settings, hcm_leave_custom_policies, holidays, holiday_calendars.');

        return self::SUCCESS;
    }

    private function requiredTablesExist(): bool
    {
        $required = [
            'users',
            'employee_profiles',
            'leave_requests',
            'leave_types',
            'leave_policies',
            'leave_policy_assignments',
            'employee_leave_balances',
            'leave_ledger',
            'hcm_leave_type_settings',
            'hcm_leave_custom_policies',
            'leave_approvals',
            'holidays',
            'holiday_calendars',
        ];

        foreach ($required as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function wipeLeaveData(): void
    {
        LeaveApproval::query()->delete();
        LeaveLedger::query()->delete();
        EmployeeLeaveBalance::query()->delete();
        LeavePolicyAssignment::query()->delete();
        HcmLeaveCustomPolicy::query()->delete();
        LeavePolicy::query()->delete();
        LeaveRequest::query()->delete();
        HolidayCalendar::query()->delete();
        Holiday::query()->delete();
        HcmLeaveTypeSetting::query()->delete();
        LeaveType::query()->delete();
    }

    /**
     * @return array{admin: User, employee1: User, employee2: User, manager: User}
     */
    private function seedUsers(string $password): array
    {
        $users = [
            'admin' => ['name' => 'HCM Admin', 'email' => 'admin.hcm@example.com', 'designation' => 'HR Admin'],
            'employee1' => ['name' => 'Andi Pratama', 'email' => 'employee1@example.com', 'designation' => 'Staff'],
            'employee2' => ['name' => 'Budi Santoso', 'email' => 'employee2@example.com', 'designation' => 'Staff'],
            'manager' => ['name' => 'Maya Ops', 'email' => 'manager.ops@example.com', 'designation' => 'Supervisor'],
        ];

        $result = [];
        foreach ($users as $key => $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                ['name' => $row['name'], 'password' => Hash::make($password)]
            );

            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'designation' => $row['designation'],
                    'employment_status' => 'active',
                    'team' => 'Operations',
                    'hire_date' => now()->subMonths(16)->toDateString(),
                    'phone' => '081200000'.(string) $user->id,
                ]
            );

            $result[$key] = $user;
        }

        /** @var array{admin: User, employee1: User, employee2: User, manager: User} $result */
        return $result;
    }

    /**
     * @return array{0: array<string, LeaveType>, 1: array<string, string>}
     */
    private function seedLeaveTypesAndLegacySettings(?int $companyId): array
    {
        $catalog = [
            ['annual_leave', 'Cuti Tahunan', true, true, 12, true, 6, true, 1],
            ['joint_leave', 'Cuti Bersama', true, true, 0, false, null, false, 2],
            ['sick_leave', 'Cuti Sakit', true, false, 0, false, null, false, 3],
            ['maternity_leave', 'Cuti Melahirkan', true, false, 90, false, null, false, 4],
            ['paternity_leave', 'Cuti Ayah', true, false, 2, false, null, false, 5],
            ['menstrual_leave', 'Cuti Haid', true, false, 24, false, null, false, 6],
            ['marriage_leave', 'Cuti Menikah', true, false, 3, false, null, false, 7],
            ['bereavement_leave', 'Cuti Duka', true, false, 2, false, null, false, 8],
            ['unpaid_leave', 'Cuti Tidak Dibayar (LOP)', false, false, 0, false, null, false, 9],
        ];

        $legacyCodeMap = [
            'annual_leave' => 'annual_leave',
            'joint_leave' => 'joint_leave',
            'sick_leave' => 'sick_leave',
            'maternity' => 'maternity_leave',
            'paternity' => 'paternity_leave',
            'menstrual_leave' => 'menstrual_leave',
            'marriage_leave' => 'marriage_leave',
            'bereavement_leave' => 'bereavement_leave',
            'lop' => 'unpaid_leave',
        ];

        $leaveTypes = [];
        foreach ($catalog as $row) {
            [$code, $name, $isPaid, $deduct, $days, $carryForward, $maxCarryDays, $earnedLeave] = $row;
            $type = LeaveType::query()->updateOrCreate(
                ['code' => $code],
                [
                    'company_id' => $companyId,
                    'name' => $name,
                    'is_paid' => $isPaid,
                    'requires_approval' => true,
                    'requires_attachment' => in_array($code, ['sick_leave', 'maternity_leave', 'marriage_leave', 'bereavement_leave'], true),
                    'deduct_from_balance' => $deduct,
                    'is_active' => true,
                ]
            );

            $legacyCode = array_search($code, $legacyCodeMap, true);
            if ($legacyCode !== false) {
                HcmLeaveTypeSetting::query()->updateOrCreate(
                    ['code' => $legacyCode],
                    [
                        'leave_type_id' => $type->id,
                        'name' => $name,
                        'is_enabled' => true,
                        'days' => $days,
                        'carry_forward' => $carryForward,
                        'max_carry_days' => $maxCarryDays,
                        'earned_leave' => $earnedLeave,
                        'sort_order' => (int) $row[8],
                    ]
                );
            }

            $leaveTypes[$code] = $type;
        }

        return [$leaveTypes, $legacyCodeMap];
    }

    /**
     * @param  array<string, LeaveType>  $leaveTypes
     * @return array<string, LeavePolicy>
     */
    private function seedPolicies(array $leaveTypes, ?int $companyId): array
    {
        $currentYearStart = now()->startOfYear()->toDateString();

        $policyConfig = [
            'annual_leave' => [12, 12, true, true, 6, true],
            'joint_leave' => [0, 0, false, false, null, false],
            'sick_leave' => [0, 0, false, false, null, false],
            'maternity_leave' => [90, 0, false, false, null, false],
            'paternity_leave' => [2, 0, false, false, null, false],
            'menstrual_leave' => [24, 0, false, false, null, false],
            'marriage_leave' => [3, 0, false, false, null, false],
            'bereavement_leave' => [2, 0, false, false, null, false],
            'unpaid_leave' => [0, 0, false, false, null, false],
        ];

        $policies = [];
        foreach ($policyConfig as $code => [$days, $minMonths, $prorated, $carry, $maxCarry, $earned]) {
            $type = $leaveTypes[$code];
            $policy = LeavePolicy::query()->updateOrCreate(
                ['leave_type_id' => $type->id, 'name' => 'Indonesia Default: '.$type->name],
                [
                    'company_id' => $companyId,
                    'days_per_year' => $days,
                    'min_service_months' => $minMonths,
                    'is_prorated' => $prorated,
                    'carry_forward' => $carry,
                    'max_carry_days' => $maxCarry,
                    'expire_after_days' => null,
                    'is_earned_leave' => $earned,
                    'allow_negative_balance' => false,
                    'effective_from' => $currentYearStart,
                    'effective_to' => null,
                ]
            );

            $policies[$code] = $policy;
        }

        return $policies;
    }

    /**
     * @param  array{admin: User, employee1: User, employee2: User, manager: User}  $users
     * @param  array<string, LeaveType>  $leaveTypes
     * @param  array<string, LeavePolicy>  $policies
     */
    private function seedCustomPolicies(array $users, array $leaveTypes, array $policies): void
    {
        $customA = LeavePolicy::query()->updateOrCreate(
            ['leave_type_id' => $leaveTypes['annual_leave']->id, 'name' => 'Legacy Custom: Engineering Annual 15'],
            [
                'company_id' => null,
                'days_per_year' => 15,
                'min_service_months' => 6,
                'is_prorated' => true,
                'carry_forward' => true,
                'max_carry_days' => 7,
                'expire_after_days' => null,
                'is_earned_leave' => true,
                'allow_negative_balance' => false,
                'effective_from' => now()->startOfYear()->toDateString(),
                'effective_to' => null,
            ]
        );

        $customB = LeavePolicy::query()->updateOrCreate(
            ['leave_type_id' => $leaveTypes['annual_leave']->id, 'name' => 'Legacy Custom: Probation Annual 6'],
            [
                'company_id' => null,
                'days_per_year' => 6,
                'min_service_months' => 0,
                'is_prorated' => true,
                'carry_forward' => false,
                'max_carry_days' => null,
                'expire_after_days' => null,
                'is_earned_leave' => true,
                'allow_negative_balance' => false,
                'effective_from' => now()->startOfYear()->toDateString(),
                'effective_to' => null,
            ]
        );

        HcmLeaveCustomPolicy::query()->updateOrCreate(
            ['name' => 'Engineering Annual 15'],
            [
                'leave_type_code' => 'annual_leave',
                'leave_type_id' => $leaveTypes['annual_leave']->id,
                'leave_policy_id' => $customA->id,
                'days' => 15,
                'assignee_user_ids' => [$users['employee1']->id, $users['manager']->id],
            ]
        );

        HcmLeaveCustomPolicy::query()->updateOrCreate(
            ['name' => 'Probation Annual 6'],
            [
                'leave_type_code' => 'annual_leave',
                'leave_type_id' => $leaveTypes['annual_leave']->id,
                'leave_policy_id' => $customB->id,
                'days' => 6,
                'assignee_user_ids' => [$users['employee2']->id],
            ]
        );

        // Ensure default annual policy still exists for users not in custom policy.
        LeavePolicy::query()->findOrFail($policies['annual_leave']->id);
    }

    /**
     * @param  array{admin: User, employee1: User, employee2: User, manager: User}  $users
     * @param  array<string, LeavePolicy>  $policies
     */
    private function seedPolicyAssignments(array $users, array $policies, ?int $companyId): void
    {
        $assignmentDate = now()->startOfYear()->toDateString();

        $byName = LeavePolicy::query()
            ->whereIn('name', ['Legacy Custom: Engineering Annual 15', 'Legacy Custom: Probation Annual 6'])
            ->get()
            ->keyBy('name');

        LeavePolicyAssignment::query()->updateOrCreate(
            ['policy_id' => $byName['Legacy Custom: Engineering Annual 15']->id, 'employee_id' => $users['employee1']->id],
            ['company_id' => $companyId, 'effective_date' => $assignmentDate, 'end_date' => null]
        );

        LeavePolicyAssignment::query()->updateOrCreate(
            ['policy_id' => $byName['Legacy Custom: Probation Annual 6']->id, 'employee_id' => $users['employee2']->id],
            ['company_id' => $companyId, 'effective_date' => $assignmentDate, 'end_date' => null]
        );

        LeavePolicyAssignment::query()->updateOrCreate(
            ['policy_id' => $byName['Legacy Custom: Engineering Annual 15']->id, 'employee_id' => $users['manager']->id],
            ['company_id' => $companyId, 'effective_date' => $assignmentDate, 'end_date' => null]
        );

        LeavePolicyAssignment::query()->updateOrCreate(
            ['policy_id' => $policies['annual_leave']->id, 'employee_id' => $users['admin']->id],
            ['company_id' => $companyId, 'effective_date' => $assignmentDate, 'end_date' => null]
        );
    }

    /**
     * @param  array{admin: User, employee1: User, employee2: User, manager: User}  $users
     * @param  array<string, LeaveType>  $leaveTypes
     */
    private function seedBalances(array $users, array $leaveTypes, ?int $companyId): void
    {
        $year = (int) now()->year;

        $this->upsertBalance($users['employee1']->id, $leaveTypes['annual_leave']->id, $year, 10, 2, 1, 0, $companyId);
        $this->upsertBalance($users['employee2']->id, $leaveTypes['annual_leave']->id, $year, 4, 1, 0, 0, $companyId);
        $this->upsertBalance($users['manager']->id, $leaveTypes['annual_leave']->id, $year, 12, 3, 2, 0, $companyId);
        $this->upsertBalance($users['admin']->id, $leaveTypes['annual_leave']->id, $year, 12, 0, 0, 0, $companyId);

        $this->upsertBalance($users['employee1']->id, $leaveTypes['sick_leave']->id, $year, 0, 1, 0, 0, $companyId);
        $this->upsertBalance($users['employee2']->id, $leaveTypes['sick_leave']->id, $year, 0, 0, 0, 0, $companyId);
    }

    private function upsertBalance(
        int $employeeId,
        int $leaveTypeId,
        int $year,
        float $balance,
        float $used,
        float $carriedForward,
        float $expired,
        ?int $companyId
    ): void {
        EmployeeLeaveBalance::query()->updateOrCreate(
            ['employee_id' => $employeeId, 'leave_type_id' => $leaveTypeId, 'year' => $year],
            [
                'company_id' => $companyId,
                'balance' => $balance,
                'used' => $used,
                'carried_forward' => $carriedForward,
                'expired' => $expired,
            ]
        );
    }

    /**
     * @param  array{admin: User, employee1: User, employee2: User, manager: User}  $users
     * @param  array<string, LeaveType>  $leaveTypes
     * @param  array<string, LeavePolicy>  $policies
     */
    private function seedRequestsApprovalsAndLedger(array $users, array $leaveTypes, array $policies, ?int $companyId): void
    {
        $today = Carbon::today();

        $requests = [
            ['user' => $users['employee1'], 'type' => 'annual_leave', 'from' => $today->copy()->addDays(3), 'to' => $today->copy()->addDays(4), 'days' => 2, 'status' => 'pending', 'notes' => 'Family event'],
            ['user' => $users['employee1'], 'type' => 'sick_leave', 'from' => $today->copy()->subDays(6), 'to' => $today->copy()->subDays(6), 'days' => 1, 'status' => 'approved', 'notes' => 'Demam + surat dokter'],
            ['user' => $users['employee2'], 'type' => 'annual_leave', 'from' => $today->copy()->subDays(15), 'to' => $today->copy()->subDays(14), 'days' => 2, 'status' => 'approved', 'notes' => 'Pulang kampung'],
            ['user' => $users['employee2'], 'type' => 'unpaid_leave', 'from' => $today->copy()->addDays(8), 'to' => $today->copy()->addDays(8), 'days' => 1, 'status' => 'declined', 'notes' => 'Kebutuhan pribadi'],
            ['user' => $users['manager'], 'type' => 'annual_leave', 'from' => $today->copy()->subDays(21), 'to' => $today->copy()->subDays(20), 'days' => 2, 'status' => 'approved', 'notes' => 'Liburan singkat'],
            ['user' => $users['manager'], 'type' => 'marriage_leave', 'from' => $today->copy()->addDays(20), 'to' => $today->copy()->addDays(22), 'days' => 3, 'status' => 'pending', 'notes' => 'Acara keluarga'],
            ['user' => $users['admin'], 'type' => 'joint_leave', 'from' => $today->copy()->addDays(1), 'to' => $today->copy()->addDays(1), 'days' => 1, 'status' => 'approved', 'notes' => 'Cuti bersama nasional'],
        ];

        foreach ($requests as $index => $row) {
            $request = LeaveRequest::query()->updateOrCreate(
                [
                    'user_id' => $row['user']->id,
                    'leave_type' => $row['type'],
                    'date_from' => $row['from']->toDateString(),
                    'date_to' => $row['to']->toDateString(),
                ],
                [
                    'company_id' => $companyId,
                    'days' => $row['days'],
                    'status' => $row['status'],
                    'notes' => $row['notes'],
                ]
            );

            LeaveApproval::query()->updateOrCreate(
                ['leave_request_id' => $request->id, 'level' => 1],
                [
                    'company_id' => $companyId,
                    'approver_id' => $users['admin']->id,
                    'status' => $row['status'] === 'pending' ? 'pending' : $row['status'],
                    'acted_at' => $row['status'] === 'pending' ? null : now()->subDays(max(1, $index + 1)),
                    'notes' => 'Seeded approval for testing',
                ]
            );

            if ($row['status'] === 'approved' && $leaveTypes[$row['type']]->deduct_from_balance) {
                LeaveLedger::query()->updateOrCreate(
                    [
                        'employee_id' => $row['user']->id,
                        'leave_type_id' => $leaveTypes[$row['type']]->id,
                        'reference_type' => 'leave_request_approval',
                        'reference_id' => 'seed:leave_request:'.$request->id.':usage',
                    ],
                    [
                        'company_id' => $companyId,
                        'policy_id' => $policies[$row['type']]->id,
                        'transaction_type' => 'usage',
                        'amount' => -1 * abs((float) $row['days']),
                        'balance_after' => null,
                        'occurred_on' => $row['from']->toDateString(),
                        'notes' => 'Seeded approved leave usage',
                        'created_by' => $users['admin']->id,
                    ]
                );
            }
        }

        // Add one accrual and one carry-forward record for richer ledger list testing.
        LeaveLedger::query()->updateOrCreate(
            [
                'employee_id' => $users['employee1']->id,
                'leave_type_id' => $leaveTypes['annual_leave']->id,
                'reference_type' => 'system_monthly_accrual',
                'reference_id' => 'seed:monthly:'.now()->format('Y-m'),
            ],
            [
                'company_id' => $companyId,
                'policy_id' => $policies['annual_leave']->id,
                'transaction_type' => 'accrual',
                'amount' => 1.0,
                'balance_after' => null,
                'occurred_on' => now()->startOfMonth()->toDateString(),
                'notes' => 'Seeded monthly accrual',
                'created_by' => $users['admin']->id,
            ]
        );

        LeaveLedger::query()->updateOrCreate(
            [
                'employee_id' => $users['employee1']->id,
                'leave_type_id' => $leaveTypes['annual_leave']->id,
                'reference_type' => 'system_yearly_carry',
                'reference_id' => 'seed:carry:'.now()->year,
            ],
            [
                'company_id' => $companyId,
                'policy_id' => $policies['annual_leave']->id,
                'transaction_type' => 'carry_forward',
                'amount' => 2.0,
                'balance_after' => null,
                'occurred_on' => now()->startOfYear()->toDateString(),
                'notes' => 'Seeded yearly carry-forward',
                'created_by' => $users['admin']->id,
            ]
        );
    }

    private function seedHolidays(?int $companyId): void
    {
        $rows = [
            ['Hari Buruh', now()->addDays(18)->toDateString(), true, false, false],
            ['Cuti Bersama Nasional', now()->addDays(30)->toDateString(), true, true, true],
            ['Hari Kemerdekaan', now()->addMonths(4)->day(17)->toDateString(), true, false, false],
        ];

        foreach ($rows as [$title, $date, $isNational, $isJointLeave, $deduct]) {
            $holiday = Holiday::query()->updateOrCreate(
                ['title' => $title, 'holiday_date' => $date],
                ['description' => 'Seeded for leave testing', 'is_active' => true]
            );

            HolidayCalendar::query()->updateOrCreate(
                ['company_id' => $companyId, 'date' => $date, 'name' => $title],
                [
                    'is_national' => $isNational,
                    'is_joint_leave' => $isJointLeave,
                    'deduct_from_leave' => $deduct,
                    'source' => 'manual',
                    'last_synced_at' => now(),
                ]
            );

            $holiday->touch();
        }
    }
}
