<?php

namespace App\Services\Reporting;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollRun;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\PurchaseTransaction;
use App\Models\ReportSnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportSnapshotService
{
    /**
     * Generate a snapshot for the specified report type and store data blocks.
     *
     * @param string $reportType (attendance|payroll|employee|leave|daily|finance)
     * @param Carbon $periodStart
     * @param Carbon $periodEnd
     * @param array $filters key-value pairs for filtering (e.g., department_id, user_id, status)
     * @param int $userId ID of user generating the snapshot
     * @param int $companyId Tenant company ID
     *
     * @return ReportSnapshot Snapshot model with status completed/failed
     */
    public function generateSnapshot(
        string $reportType,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $filters,
        int $userId,
        int $companyId
    ): ReportSnapshot {
        // Create snapshot record
        $snapshot = ReportSnapshot::create([
            'company_id' => $companyId,
            'report_type' => $reportType,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'generated_at' => now(),
            'generated_by_user_id' => $userId,
            'status' => 'processing',
            'meta' => [
                'filters' => $filters,
                'row_count' => 0,
            ],
        ]);

        try {
            // Dispatch to type-specific generator
            $method = "generate" . str_replace(' ', '', ucwords(str_replace('_', ' ', $reportType))) . "Snapshot";

            if (!method_exists($this, $method)) {
                throw new \InvalidArgumentException("Report type '{$reportType}' is not supported.");
            }

            $rowCount = $this->$method($snapshot, $periodStart, $periodEnd, $filters, $companyId);

            // Mark as completed
            $snapshot->update([
                'status' => 'completed',
                'meta' => array_merge($snapshot->meta ?? [], ['row_count' => $rowCount]),
            ]);
        } catch (\Exception $e) {
            // Mark as failed
            $snapshot->update([
                'status' => 'failed',
                'meta' => array_merge($snapshot->meta ?? [], [
                    'error' => $e->getMessage(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null,
                ]),
            ]);
        }

        return $snapshot->fresh();
    }

    /**
     * Generate attendance snapshot: daily records grouped by user, department, status.
     */
    private function generateAttendanceSnapshot(
        ReportSnapshot $snapshot,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $filters,
        int $companyId
    ): int {
        $query = AttendanceRecord::where('company_id', $companyId)
            ->whereBetween('work_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->with(['user']);

        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $records = $query->get();

        // Aggregate by date, user, and status
        $aggregated = [
            'summary' => [
                'total_records' => $records->count(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            'by_user' => [],
            'by_status' => [],
            'by_date' => [],
        ];

        foreach ($records as $record) {
            // By user
            $userId = $record->user_id;
            if (!isset($aggregated['by_user'][$userId])) {
                $aggregated['by_user'][$userId] = [
                    'user_id' => $userId,
                    'user_name' => $record->user->name ?? 'Unknown',
                    'present' => 0,
                    'absent' => 0,
                    'late' => 0,
                    'total_late_minutes' => 0,
                ];
            }

            if ($record->status === 'present') {
                $aggregated['by_user'][$userId]['present']++;
                $aggregated['by_user'][$userId]['total_late_minutes'] += ($record->late_minutes ?? 0);
                if (($record->late_minutes ?? 0) > 0) {
                    $aggregated['by_user'][$userId]['late']++;
                }
            } elseif ($record->status === 'absent') {
                $aggregated['by_user'][$userId]['absent']++;
            }

            // By status
            if (!isset($aggregated['by_status'][$record->status])) {
                $aggregated['by_status'][$record->status] = 0;
            }
            $aggregated['by_status'][$record->status]++;

            // By date
            $date = $record->work_date->toDateString();
            if (!isset($aggregated['by_date'][$date])) {
                $aggregated['by_date'][$date] = [
                    'date' => $date,
                    'present' => 0,
                    'absent' => 0,
                    'total_records' => 0,
                ];
            }
            $aggregated['by_date'][$date][$record->status] = ($aggregated['by_date'][$date][$record->status] ?? 0) + 1;
            $aggregated['by_date'][$date]['total_records']++;
        }

        // Store data blocks
        $rowCount = 0;

        // Summary block
        $snapshot->dataBlocks()->create([
            'module' => 'attendance',
            'data_key' => 'summary',
            'data_value' => $aggregated['summary'],
        ]);
        $rowCount++;

        // By user block
        foreach ($aggregated['by_user'] as $key => $data) {
            $snapshot->dataBlocks()->create([
                'module' => 'attendance',
                'data_key' => "user_{$key}",
                'data_value' => $data,
            ]);
            $rowCount++;
        }

        // By status block
        if (!empty($aggregated['by_status'])) {
            $snapshot->dataBlocks()->create([
                'module' => 'attendance',
                'data_key' => 'by_status',
                'data_value' => $aggregated['by_status'],
            ]);
            $rowCount++;
        }

        // By date block (sampled if too large)
        $dateSummary = collect($aggregated['by_date'])
            ->values()
            ->toArray();

        if (count($dateSummary) > 100) {
            // Sample every Nth date to avoid large blocks
            $dateSummary = array_filter($dateSummary, fn ($_, $i) => $i % ceil(count($dateSummary) / 100) === 0, ARRAY_FILTER_USE_BOTH);
        }

        $snapshot->dataBlocks()->create([
            'module' => 'attendance',
            'data_key' => 'by_date_sampled',
            'data_value' => $dateSummary,
        ]);
        $rowCount++;

        return $rowCount;
    }

    /**
     * Generate payroll snapshot: runs and line items aggregated by component and user.
     */
    private function generatePayrollSnapshot(
        ReportSnapshot $snapshot,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $filters,
        int $companyId
    ): int {
        $query = HcmPayrollRun::where('company_id', $companyId)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->with(['lines.user', 'lines.salaryComponent', 'period'])
            ->where('status', '!=', 'void');

        // Apply filters
        if (isset($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $runs = $query->get();
        $allLines = HcmPayrollLine::whereIn('hcm_payroll_run_id', $runs->pluck('id'))
            ->with(['user', 'salaryComponent'])
            ->get();

        $aggregated = [
            'summary' => [
                'total_runs' => $runs->count(),
                'total_lines' => $allLines->count(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            'by_component' => [],
            'by_user' => [],
            'by_run' => [],
        ];

        foreach ($allLines as $line) {
            // By component
            $componentCode = $line->component_code;
            if (!isset($aggregated['by_component'][$componentCode])) {
                $aggregated['by_component'][$componentCode] = [
                    'component_code' => $componentCode,
                    'component_name' => $line->component_name,
                    'kind' => $line->kind,
                    'category' => $line->category,
                    'total_amount' => 0,
                    'count' => 0,
                    'avg_amount' => 0,
                ];
            }
            $aggregated['by_component'][$componentCode]['total_amount'] += $line->amount;
            $aggregated['by_component'][$componentCode]['count']++;

            // By user
            $userId = $line->user_id;
            if (!isset($aggregated['by_user'][$userId])) {
                $aggregated['by_user'][$userId] = [
                    'user_id' => $userId,
                    'user_name' => $line->user->name ?? 'Unknown',
                    'total_gross' => 0,
                    'total_deductions' => 0,
                    'total_net' => 0,
                    'run_count' => 0,
                ];
            }

            if ($line->kind === 'addition') {
                $aggregated['by_user'][$userId]['total_gross'] += $line->amount;
            } elseif ($line->kind === 'deduction') {
                $aggregated['by_user'][$userId]['total_deductions'] += $line->amount;
            }
        }

        // Calculate net and averages
        foreach ($aggregated['by_component'] as &$comp) {
            $comp['avg_amount'] = $comp['count'] > 0 ? round($comp['total_amount'] / $comp['count'], 2) : 0;
        }

        foreach ($aggregated['by_user'] as &$user) {
            $user['total_net'] = $user['total_gross'] - $user['total_deductions'];
        }

        // By run (summary per payroll run)
        foreach ($runs as $run) {
            $runLines = $allLines->where('hcm_payroll_run_id', $run->id);
            $grossAmount = $runLines->where('kind', 'addition')->sum('amount');
            $deductionAmount = $runLines->where('kind', 'deduction')->sum('amount');

            $aggregated['by_run'][] = [
                'run_id' => $run->id,
                'purpose' => $run->purpose,
                'status' => $run->status,
                'period' => $run->period?->display_name ?? 'N/A',
                'gross_amount' => $grossAmount,
                'deduction_amount' => $deductionAmount,
                'net_amount' => $grossAmount - $deductionAmount,
                'line_count' => $runLines->count(),
            ];
        }

        $rowCount = 0;

        // Summary block
        $snapshot->dataBlocks()->create([
            'module' => 'payroll',
            'data_key' => 'summary',
            'data_value' => $aggregated['summary'],
        ]);
        $rowCount++;

        // By component block
        foreach ($aggregated['by_component'] as $key => $data) {
            $snapshot->dataBlocks()->create([
                'module' => 'payroll',
                'data_key' => "component_{$key}",
                'data_value' => $data,
            ]);
            $rowCount++;
        }

        // By user block
        foreach ($aggregated['by_user'] as $key => $data) {
            $snapshot->dataBlocks()->create([
                'module' => 'payroll',
                'data_key' => "user_{$key}",
                'data_value' => $data,
            ]);
            $rowCount++;
        }

        // By run block
        if (!empty($aggregated['by_run'])) {
            $snapshot->dataBlocks()->create([
                'module' => 'payroll',
                'data_key' => 'by_run',
                'data_value' => $aggregated['by_run'],
            ]);
            $rowCount++;
        }

        return $rowCount;
    }

    /**
     * Generate employee snapshot: employee count, department distribution, status breakdown.
     */
    private function generateEmployeeSnapshot(
        ReportSnapshot $snapshot,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $filters,
        int $companyId
    ): int {
        $query = \App\Models\CompanyUser::where('company_id', $companyId)
            ->with(['user'])
            ->where('status', '!=', 'archived');

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $employees = $query->get();

        $aggregated = [
            'summary' => [
                'total_active' => $employees->where('status', 'active')->count(),
                'total_inactive' => $employees->where('status', 'inactive')->count(),
                'total_pending' => $employees->where('status', 'pending')->count(),
                'total' => $employees->count(),
                'report_date' => now()->toDateString(),
            ],
            'by_status' => [],
        ];

        // By status
        foreach ($employees->groupBy('status') as $status => $group) {
            $aggregated['by_status'][$status] = [
                'status' => $status,
                'count' => $group->count(),
                'percentage' => round(($group->count() / $employees->count()) * 100, 2),
            ];
        }

        $rowCount = 0;

        // Summary block
        $snapshot->dataBlocks()->create([
            'module' => 'employee',
            'data_key' => 'summary',
            'data_value' => $aggregated['summary'],
        ]);
        $rowCount++;

        // By status block
        if (!empty($aggregated['by_status'])) {
            $snapshot->dataBlocks()->create([
                'module' => 'employee',
                'data_key' => 'by_status',
                'data_value' => $aggregated['by_status'],
            ]);
            $rowCount++;
        }

        return $rowCount;
    }

    /**
     * Generate leave snapshot: leave requests aggregated by type, user, and status.
     */
    private function generateLeaveSnapshot(
        ReportSnapshot $snapshot,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $filters,
        int $companyId
    ): int {
        $query = LeaveRequest::where('company_id', $companyId)
            ->whereBetween('date_from', [$periodStart, $periodEnd])
            ->with(['user']);

        // Apply filters
        if (isset($filters['leave_type'])) {
            $query->where('leave_type', $filters['leave_type']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $records = $query->get();

        $aggregated = [
            'summary' => [
                'total_requests' => $records->count(),
                'total_days' => $records->sum('days'),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            'by_leave_type' => [],
            'by_status' => [],
            'by_user' => [],
        ];

        foreach ($records as $record) {
            // By leave type
            if (!isset($aggregated['by_leave_type'][$record->leave_type])) {
                $aggregated['by_leave_type'][$record->leave_type] = [
                    'leave_type' => $record->leave_type,
                    'count' => 0,
                    'total_days' => 0,
                ];
            }
            $aggregated['by_leave_type'][$record->leave_type]['count']++;
            $aggregated['by_leave_type'][$record->leave_type]['total_days'] += $record->days;

            // By status
            if (!isset($aggregated['by_status'][$record->status])) {
                $aggregated['by_status'][$record->status] = [
                    'status' => $record->status,
                    'count' => 0,
                ];
            }
            $aggregated['by_status'][$record->status]['count']++;

            // By user
            $userId = $record->user_id;
            if (!isset($aggregated['by_user'][$userId])) {
                $aggregated['by_user'][$userId] = [
                    'user_id' => $userId,
                    'user_name' => $record->user->name ?? 'Unknown',
                    'total_days' => 0,
                    'request_count' => 0,
                ];
            }
            $aggregated['by_user'][$userId]['total_days'] += $record->days;
            $aggregated['by_user'][$userId]['request_count']++;
        }

        $rowCount = 0;

        // Summary block
        $snapshot->dataBlocks()->create([
            'module' => 'leave',
            'data_key' => 'summary',
            'data_value' => $aggregated['summary'],
        ]);
        $rowCount++;

        // By leave type block
        foreach ($aggregated['by_leave_type'] as $key => $data) {
            $snapshot->dataBlocks()->create([
                'module' => 'leave',
                'data_key' => "leave_type_{$key}",
                'data_value' => $data,
            ]);
            $rowCount++;
        }

        // By status block
        if (!empty($aggregated['by_status'])) {
            $snapshot->dataBlocks()->create([
                'module' => 'leave',
                'data_key' => 'by_status',
                'data_value' => $aggregated['by_status'],
            ]);
            $rowCount++;
        }

        // By user block
        foreach ($aggregated['by_user'] as $key => $data) {
            $snapshot->dataBlocks()->create([
                'module' => 'leave',
                'data_key' => "user_{$key}",
                'data_value' => $data,
            ]);
            $rowCount++;
        }

        return $rowCount;
    }

    /**
     * Generate finance snapshot: invoices, payments, transactions aggregated by status.
     */
    private function generateFinanceSnapshot(
        ReportSnapshot $snapshot,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $filters,
        int $companyId
    ): int {
        // Query invoices
        $invoiceQuery = Invoice::where('company_id', $companyId)
            ->whereBetween('issue_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);

        if (isset($filters['status'])) {
            $invoiceQuery->where('status', $filters['status']);
        }

        $invoices = $invoiceQuery->get();

        // Query payments
        $paymentQuery = Payment::where('company_id', $companyId)
            ->whereBetween('paid_at', [$periodStart, $periodEnd]);

        if (isset($filters['payment_status'])) {
            $paymentQuery->where('status', $filters['payment_status']);
        }

        $payments = $paymentQuery->get();

        // Query transactions
        $transactionQuery = PurchaseTransaction::where('company_id', $companyId)
            ->whereBetween('created_at', [$periodStart, $periodEnd]);

        if (isset($filters['transaction_status'])) {
            $transactionQuery->where('status', $filters['transaction_status']);
        }

        $transactions = $transactionQuery->get();

        $aggregated = [
            'summary' => [
                'total_invoices' => $invoices->count(),
                'total_payments' => $payments->count(),
                'total_transactions' => $transactions->count(),
                'invoiced_amount' => $invoices->sum('amount_due'),
                'paid_amount' => $payments->sum('amount'),
                'transaction_amount' => $transactions->sum('total_amount'),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            'invoice_by_status' => [],
            'payment_by_status' => [],
            'transaction_by_type' => [],
        ];

        // Invoice by status
        foreach ($invoices->groupBy('status') as $status => $group) {
            $aggregated['invoice_by_status'][$status] = [
                'status' => $status,
                'count' => $group->count(),
                'total_amount' => $group->sum('amount_due'),
            ];
        }

        // Payment by status
        foreach ($payments->groupBy('status') as $status => $group) {
            $aggregated['payment_by_status'][$status] = [
                'status' => $status,
                'count' => $group->count(),
                'total_amount' => $group->sum('amount'),
            ];
        }

        // Transaction by type
        foreach ($transactions->groupBy('transaction_type') as $type => $group) {
            $aggregated['transaction_by_type'][$type] = [
                'transaction_type' => $type,
                'count' => $group->count(),
                'total_amount' => $group->sum('total_amount'),
            ];
        }

        $rowCount = 0;

        // Summary block
        $snapshot->dataBlocks()->create([
            'module' => 'finance',
            'data_key' => 'summary',
            'data_value' => $aggregated['summary'],
        ]);
        $rowCount++;

        // Invoice by status
        foreach ($aggregated['invoice_by_status'] as $key => $data) {
            $snapshot->dataBlocks()->create([
                'module' => 'finance',
                'data_key' => "invoice_status_{$key}",
                'data_value' => $data,
            ]);
            $rowCount++;
        }

        // Payment by status
        foreach ($aggregated['payment_by_status'] as $key => $data) {
            $snapshot->dataBlocks()->create([
                'module' => 'finance',
                'data_key' => "payment_status_{$key}",
                'data_value' => $data,
            ]);
            $rowCount++;
        }

        // Transaction by type
        foreach ($aggregated['transaction_by_type'] as $key => $data) {
            $snapshot->dataBlocks()->create([
                'module' => 'finance',
                'data_key' => "transaction_type_{$key}",
                'data_value' => $data,
            ]);
            $rowCount++;
        }

        return $rowCount;
    }
}
