<?php

namespace App\Console\Commands;

use App\Models\HcmLeaveCustomPolicy;
use App\Models\HcmLeaveTypeSetting;
use App\Models\LeavePolicy;
use App\Models\LeavePolicyAssignment;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HcmBackfillLeaveFoundationCommand extends Command
{
    protected $signature = 'hcm:leave-backfill-foundation
        {--company-id= : Optional company id for inserted foundation rows}
        {--assign-all-users : Assign base policy to all users if no custom assignment exists}';

    protected $description = 'Backfill leave foundation (types/policies/assignments) from legacy leave settings tables.';

    public function handle(): int
    {
        if (! $this->requiredTablesExist()) {
            $this->warn('Required tables are not available. Run migrations first.');

            return self::SUCCESS;
        }

        $companyId = $this->option('company-id');
        $companyId = ($companyId === null || $companyId === '') ? null : (int) $companyId;
        $assignAllUsers = (bool) $this->option('assign-all-users');

        $summary = [
            'types' => 0,
            'policies' => 0,
            'assignments' => 0,
            'customPolicies' => 0,
        ];

        DB::transaction(function () use ($companyId, $assignAllUsers, &$summary): void {
            $legacyTypes = HcmLeaveTypeSetting::query()->orderBy('sort_order')->orderBy('id')->get();
            $allUserIds = $assignAllUsers
                ? User::query()->pluck('id')->map(fn ($id) => (int) $id)->all()
                : [];

            foreach ($legacyTypes as $legacyType) {
                $foundationType = $this->upsertLeaveType($legacyType, $companyId);
                if (! $foundationType) {
                    continue;
                }
                if ((int) ($legacyType->leave_type_id ?? 0) !== (int) $foundationType->id) {
                    $legacyType->leave_type_id = $foundationType->id;
                    $legacyType->save();
                }
                $summary['types']++;

                $basePolicy = $this->upsertBasePolicy($legacyType, $foundationType->id, $companyId);
                $summary['policies']++;

                $customRows = HcmLeaveCustomPolicy::query()
                    ->where('leave_type_code', $legacyType->code)
                    ->orderBy('id')
                    ->get();

                $assignedUsersByCustom = [];
                foreach ($customRows as $custom) {
                    $customPolicy = LeavePolicy::query()->firstOrNew([
                        'leave_type_id' => $foundationType->id,
                        'name' => 'Legacy Custom: '.$custom->name,
                    ]);
                    $customPolicy->fill([
                        'company_id' => $companyId,
                        'days_per_year' => (float) $custom->days,
                        'min_service_months' => 0,
                        'is_prorated' => false,
                        'carry_forward' => false,
                        'max_carry_days' => null,
                        'expire_after_days' => null,
                        'is_earned_leave' => false,
                        'allow_negative_balance' => false,
                        'effective_from' => $customPolicy->effective_from ?: now()->startOfYear()->toDateString(),
                        'effective_to' => null,
                    ]);
                    $customPolicy->save();

                    if ((int) ($custom->leave_type_id ?? 0) !== (int) $foundationType->id || (int) ($custom->leave_policy_id ?? 0) !== (int) $customPolicy->id) {
                        $custom->leave_type_id = $foundationType->id;
                        $custom->leave_policy_id = $customPolicy->id;
                        $custom->save();
                    }
                    $summary['customPolicies']++;

                    $assignees = collect($custom->assignee_user_ids ?? [])
                        ->map(fn ($v) => (int) $v)
                        ->filter(fn ($v) => $v > 0)
                        ->unique()
                        ->values()
                        ->all();

                    foreach ($assignees as $userId) {
                        $assignment = LeavePolicyAssignment::query()->firstOrCreate([
                            'policy_id' => $customPolicy->id,
                            'employee_id' => $userId,
                        ], [
                            'company_id' => $companyId,
                            'effective_date' => now()->startOfYear()->toDateString(),
                            'end_date' => null,
                        ]);
                        if ($assignment->wasRecentlyCreated) {
                            $summary['assignments']++;
                        }
                        $assignedUsersByCustom[$userId] = true;
                    }
                }

                if ($assignAllUsers && $basePolicy) {
                    foreach ($allUserIds as $userId) {
                        if (isset($assignedUsersByCustom[$userId])) {
                            continue;
                        }
                        $assignment = LeavePolicyAssignment::query()->firstOrCreate([
                            'policy_id' => $basePolicy->id,
                            'employee_id' => $userId,
                        ], [
                            'company_id' => $companyId,
                            'effective_date' => now()->startOfYear()->toDateString(),
                            'end_date' => null,
                        ]);
                        if ($assignment->wasRecentlyCreated) {
                            $summary['assignments']++;
                        }
                    }
                }
            }
        });

        $this->info(sprintf(
            'Backfill selesai. types=%d policies=%d customPolicies=%d assignments=%d',
            $summary['types'],
            $summary['policies'],
            $summary['customPolicies'],
            $summary['assignments']
        ));

        return self::SUCCESS;
    }

    private function requiredTablesExist(): bool
    {
        $required = [
            'hcm_leave_type_settings',
            'hcm_leave_custom_policies',
            'leave_types',
            'leave_policies',
            'leave_policy_assignments',
            'users',
        ];
        foreach ($required as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function upsertLeaveType(HcmLeaveTypeSetting $legacyType, ?int $companyId): ?LeaveType
    {
        if (! $legacyType->is_enabled) {
            return null;
        }

        $code = $this->mapLegacyCode($legacyType->code, $legacyType->name);

        return LeaveType::query()->updateOrCreate(
            ['code' => $code],
            [
                'company_id' => $companyId,
                'name' => (string) $legacyType->name,
                'is_paid' => ! in_array($legacyType->code, ['lop', 'unpaid_leave'], true),
                'requires_approval' => true,
                'requires_attachment' => in_array($legacyType->code, ['sick_leave', 'hospitalisation'], true),
                'deduct_from_balance' => ! in_array($legacyType->code, ['sick_leave', 'hospitalisation', 'maternity', 'paternity'], true),
                'is_active' => true,
            ]
        );
    }

    private function upsertBasePolicy(HcmLeaveTypeSetting $legacyType, int $leaveTypeId, ?int $companyId): LeavePolicy
    {
        $name = 'Legacy Base: '.$legacyType->name;
        $daysPerYear = (float) ($legacyType->days ?? 0);

        $policy = LeavePolicy::query()->firstOrNew([
            'leave_type_id' => $leaveTypeId,
            'name' => $name,
        ]);
        $policy->fill([
            'company_id' => $companyId,
            'days_per_year' => $daysPerYear,
            'min_service_months' => $legacyType->code === 'annual_leave' ? 12 : 0,
            'is_prorated' => (bool) $legacyType->earned_leave,
            'carry_forward' => (bool) $legacyType->carry_forward,
            'max_carry_days' => $legacyType->max_carry_days,
            'expire_after_days' => null,
            'is_earned_leave' => (bool) $legacyType->earned_leave,
            'allow_negative_balance' => false,
            'effective_from' => $policy->effective_from ?: now()->startOfYear()->toDateString(),
            'effective_to' => null,
        ]);
        $policy->save();

        return $policy;
    }

    private function mapLegacyCode(string $code, string $name): string
    {
        $normalized = Str::of($code)->lower()->slug('_')->toString();
        $map = [
            'maternity' => 'maternity_leave',
            'paternity' => 'paternity_leave',
            'lop' => 'unpaid_leave',
        ];

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        if ($normalized !== '') {
            return $normalized;
        }

        return Str::of($name)->lower()->slug('_')->toString();
    }
}
