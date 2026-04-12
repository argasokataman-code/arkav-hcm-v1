<?php

namespace App\Services\Hcm;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveLedgerService
{
    /**
     * @param array{
     *  companyId?: int|null,
     *  employeeId: int,
     *  leaveTypeId: int,
     *  policyId?: int|null,
     *  transactionType: string,
     *  amount: float|int|string,
     *  occurredOn?: string|Carbon,
     *  referenceType?: string|null,
     *  referenceId?: string|null,
     *  notes?: string|null,
     *  createdBy?: int|null,
     *  enforceNoNegative?: bool
     * } $payload
     */
    public function post(array $payload): LeaveLedger
    {
        return DB::transaction(function () use ($payload): LeaveLedger {
            $companyId = $payload['companyId'] ?? null;
            $employeeId = (int) $payload['employeeId'];
            $leaveTypeId = (int) $payload['leaveTypeId'];
            $policyId = isset($payload['policyId']) ? (int) $payload['policyId'] : null;
            $transactionType = strtolower(trim((string) $payload['transactionType']));
            $amount = (float) $payload['amount'];
            $occurredOn = $payload['occurredOn'] ?? now()->toDateString();
            $occurredDate = $occurredOn instanceof Carbon ? $occurredOn->copy()->startOfDay() : Carbon::parse((string) $occurredOn)->startOfDay();
            $year = (int) $occurredDate->year;

            $balanceRow = EmployeeLeaveBalance::query()
                ->where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveTypeId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $balanceRow) {
                $balanceRow = EmployeeLeaveBalance::query()->create([
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveTypeId,
                    'year' => $year,
                    'balance' => 0,
                    'used' => 0,
                    'expired' => 0,
                    'carried_forward' => 0,
                ]);

                $balanceRow = EmployeeLeaveBalance::query()
                    ->whereKey($balanceRow->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $balanceBefore = (float) $balanceRow->balance;
            $balanceAfter = $balanceBefore + $amount;
            $enforceNoNegative = (bool) ($payload['enforceNoNegative'] ?? false);
            if ($enforceNoNegative && $balanceAfter < 0) {
                throw new \RuntimeException('LEAVE_NEGATIVE_BALANCE_NOT_ALLOWED');
            }

            $usedDelta = 0.0;
            $expiredDelta = 0.0;
            $carryDelta = 0.0;

            if (in_array($transactionType, ['usage', 'joint_leave'], true) && $amount < 0) {
                $usedDelta = abs($amount);
            }
            if ($transactionType === 'reversal' && $amount > 0) {
                $usedDelta = -1 * $amount;
            }
            if ($transactionType === 'expire' && $amount < 0) {
                $expiredDelta = abs($amount);
            }
            if ($transactionType === 'carry_forward' && $amount > 0) {
                $carryDelta = $amount;
            }

            $nextUsed = max(0, (float) $balanceRow->used + $usedDelta);
            $nextExpired = max(0, (float) $balanceRow->expired + $expiredDelta);
            $nextCarried = max(0, (float) $balanceRow->carried_forward + $carryDelta);

            $balanceRow->update([
                'balance' => $balanceAfter,
                'used' => $nextUsed,
                'expired' => $nextExpired,
                'carried_forward' => $nextCarried,
                'company_id' => $companyId,
            ]);

            return LeaveLedger::query()->create([
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'policy_id' => $policyId,
                'transaction_type' => $transactionType,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'reference_type' => $payload['referenceType'] ?? null,
                'reference_id' => $payload['referenceId'] ?? null,
                'occurred_on' => $occurredDate->toDateString(),
                'notes' => $payload['notes'] ?? null,
                'created_by' => $payload['createdBy'] ?? null,
            ]);
        });
    }

    public function rebuildYearlyBalance(int $employeeId, int $leaveTypeId, int $year): EmployeeLeaveBalance
    {
        return DB::transaction(function () use ($employeeId, $leaveTypeId, $year): EmployeeLeaveBalance {
            $rows = LeaveLedger::query()
                ->where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveTypeId)
                ->whereYear('occurred_on', $year)
                ->orderBy('occurred_on')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['transaction_type', 'amount']);

            $balance = 0.0;
            $used = 0.0;
            $expired = 0.0;
            $carried = 0.0;

            foreach ($rows as $row) {
                $amount = (float) $row->amount;
                $type = strtolower((string) $row->transaction_type);

                $balance += $amount;
                if (in_array($type, ['usage', 'joint_leave'], true) && $amount < 0) {
                    $used += abs($amount);
                }
                if ($type === 'expire' && $amount < 0) {
                    $expired += abs($amount);
                }
                if ($type === 'carry_forward' && $amount > 0) {
                    $carried += $amount;
                }
            }

            $row = EmployeeLeaveBalance::query()
                ->where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveTypeId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                return EmployeeLeaveBalance::query()->create([
                    'company_id' => null,
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveTypeId,
                    'year' => $year,
                    'balance' => $balance,
                    'used' => $used,
                    'expired' => $expired,
                    'carried_forward' => $carried,
                ]);
            }

            $row->update([
                'balance' => $balance,
                'used' => $used,
                'expired' => $expired,
                'carried_forward' => $carried,
            ]);

            return $row->fresh();
        });
    }
}
