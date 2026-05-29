<?php

namespace App\Services;

use App\Models\HcmApprovalConfig;
use App\Models\HcmApprovalConfigApprover;
use App\Models\LeaveApproval;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveApprovalRequestedNotification;
use App\Notifications\LeaveNextApproverNotification;
use App\Notifications\LeaveApprovedNotification;
use App\Notifications\LeaveRejectedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApprovalConfigService
{
    /**
     * Retrieve approval config for a given company+module.
     * Returns null if no config is set up.
     */
    public function getConfigForModule(int $companyId, string $module): ?HcmApprovalConfig
    {
        return HcmApprovalConfig::query()
            ->with('approvers.approverUser:id,uuid,name,email')
            ->where('company_id', $companyId)
            ->where('module', $module)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Upsert approval config for a module.
     * $approverUserIds: ordered array of user IDs. Order = sequence_order.
     */
    public function upsertConfig(int $companyId, string $module, string $approvalMode, array $approverUserIds): HcmApprovalConfig
    {
        return DB::transaction(function () use ($companyId, $module, $approvalMode, $approverUserIds): HcmApprovalConfig {
            /** @var HcmApprovalConfig $config */
            $config = HcmApprovalConfig::query()->firstOrCreate(
                ['company_id' => $companyId, 'module' => $module],
                ['approval_mode' => $approvalMode, 'is_active' => true]
            );

            $config->update(['approval_mode' => $approvalMode, 'is_active' => true]);

            // Remove old approvers and re-insert (simple replace)
            $config->approvers()->delete();

            foreach ($approverUserIds as $order => $userId) {
                $user = User::query()->find((int) $userId);
                if (! $user) {
                    continue;
                }

                HcmApprovalConfigApprover::query()->create([
                    'hcm_approval_config_id' => $config->id,
                    'company_id' => $companyId,
                    'approver_user_id' => $user->id,
                    'approver_user_uuid' => (string) ($user->uuid ?? ''),
                    'sequence_order' => $order + 1,
                ]);
            }

            return $config->fresh(['approvers']);
        });
    }

    /**
     * After a LeaveRequest is created, populate leave_approvals rows.
     * If no config: does nothing (existing notification fallback handles it).
     * Returns the list of first-level approvers to notify.
     *
     * @return Collection<int, User>
     */
    public function populateLeaveApprovals(LeaveRequest $leaveRequest): Collection
    {
        $config = $this->getConfigForModule((int) $leaveRequest->company_id, 'leave');

        if (! $config || $config->approvers->isEmpty()) {
            return collect();
        }

        // Remove any stale leave_approvals for this request
        LeaveApproval::query()->where('leave_request_id', $leaveRequest->id)->delete();

        foreach ($config->approvers as $approver) {
            LeaveApproval::query()->create([
                'company_id' => $leaveRequest->company_id,
                'leave_request_id' => $leaveRequest->id,
                'approver_id' => $approver->approver_user_id,
                'level' => $approver->sequence_order,
                'status' => 'pending',
            ]);
        }

        // Return users to notify at level 1 (or all, for simultaneous)
        $firstLevel = $config->approval_mode === 'sequence' ? 1 : null;

        return $config->approvers
            ->when($firstLevel !== null, fn ($c) => $c->where('sequence_order', $firstLevel))
            ->map(fn ($a) => $a->approverUser)
            ->filter()
            ->values();
    }

    /**
     * Process an approver's decision (approve or reject) on a LeaveRequest.
     * Handles:
     *   - sequence mode: advance chain on approve, reject immediately on decline
     *   - simultaneous mode: approve when all approvers have approved, reject on any decline
     *
     * @param  string  $decision  'approved' | 'declined'
     * @return array{status: string, next_approvers: Collection<int, User>}
     */
    public function processApprovalDecision(
        LeaveRequest $leaveRequest,
        int $actorUserId,
        string $decision,
        ?string $notes = null
    ): array {
        $config = $this->getConfigForModule((int) $leaveRequest->company_id, 'leave');

        if (! $config) {
            // No config, nothing to process here
            return ['status' => $decision === 'approved' ? 'approved' : 'declined', 'next_approvers' => collect()];
        }

        return DB::transaction(function () use ($leaveRequest, $actorUserId, $decision, $notes, $config): array {
            // Find this actor's pending leave_approval row
            $leaveApprovalRow = LeaveApproval::query()
                ->where('leave_request_id', $leaveRequest->id)
                ->where('approver_id', $actorUserId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($leaveApprovalRow) {
                $leaveApprovalRow->update([
                    'status' => $decision,
                    'acted_at' => now(),
                    'notes' => $notes,
                ]);
            }

            if ($decision === 'declined') {
                return ['status' => 'declined', 'next_approvers' => collect()];
            }

            // decision = approved
            if ($config->approval_mode === 'simultaneous') {
                // Check if all approvers have approved
                $pendingCount = LeaveApproval::query()
                    ->where('leave_request_id', $leaveRequest->id)
                    ->where('status', 'pending')
                    ->count();

                if ($pendingCount === 0) {
                    return ['status' => 'approved', 'next_approvers' => collect()];
                }

                // Some still pending — no status change yet
                return ['status' => 'pending', 'next_approvers' => collect()];
            }

            // sequence mode: advance to next level
            if ($leaveApprovalRow) {
                $currentLevel = (int) $leaveApprovalRow->level;
                $nextLevelRow = LeaveApproval::query()
                    ->where('leave_request_id', $leaveRequest->id)
                    ->where('level', $currentLevel + 1)
                    ->where('status', 'pending')
                    ->first();

                if ($nextLevelRow) {
                    $nextUser = User::query()->find($nextLevelRow->approver_id);
                    return [
                        'status' => 'pending',
                        'next_approvers' => $nextUser ? collect([$nextUser]) : collect(),
                    ];
                }
            }

            // No more pending levels — fully approved
            return ['status' => 'approved', 'next_approvers' => collect()];
        });
    }

    /**
     * Returns the list of User objects to immediately notify for a new approval request.
     * - simultaneous mode: all configured approvers
     * - sequence mode: only level 1 approvers
     *
     * Used by modules that do NOT have a dedicated _approvals tracking table
     * (e.g. overtime, resignation, termination).
     *
     * @return Collection<int, User>
     */
    public function resolveApproversToNotify(int $companyId, string $module): Collection
    {
        $config = $this->getConfigForModule($companyId, $module);

        if (! $config || $config->approvers->isEmpty()) {
            return collect();
        }

        $firstLevel = $config->approval_mode === 'sequence' ? 1 : null;

        return $config->approvers
            ->when($firstLevel !== null, fn ($c) => $c->where('sequence_order', $firstLevel))
            ->map(fn ($a) => $a->approverUser)
            ->filter()
            ->values();
    }

    /**
     * Get active members of a company who are eligible to be approvers.
     * Returns users with role owner or admin, plus any existing configured approvers.
     *
     * @return Collection<int, array{id: int, uuid: string, name: string, email: string}>
     */
    public function getEligibleApprovers(int $companyId, string $search = ''): Collection
    {
        $query = User::query()
            ->select('users.id', 'users.uuid', 'users.name', 'users.email', 'ep.designation')
            ->join('company_users', 'company_users.user_id', '=', 'users.id')
            ->leftJoin('employee_profiles as ep', function ($join) use ($companyId) {
                $join->on('ep.user_id', '=', 'users.id')
                     ->where('ep.company_id', '=', $companyId);
            })
            ->where('company_users.company_id', $companyId)
            ->where('company_users.status', 'active')
            ->orderBy('users.name')
            ->limit(20);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like): void {
                $q->where('users.name', 'LIKE', $like)
                  ->orWhere('users.email', 'LIKE', $like)
                  ->orWhere('ep.designation', 'LIKE', $like);
            });
        }

        return $query->get()->map(fn (User $u) => [
            'id'          => $u->id,
            'uuid'        => (string) ($u->uuid ?? ''),
            'name'        => (string) $u->name,
            'email'       => (string) $u->email,
            'designation' => (string) ($u->designation ?? ''),
        ]);
    }
}
