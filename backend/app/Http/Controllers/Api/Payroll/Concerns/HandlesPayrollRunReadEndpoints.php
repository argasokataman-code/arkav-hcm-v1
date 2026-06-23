<?php

namespace App\Http\Controllers\Api\Payroll\Concerns;

use App\Mail\MonthlyPayslipMail;
use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\User;
use App\Services\PayslipEncryptionService;
use App\Support\Exports\TabularExportResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

trait HandlesPayrollRunReadEndpoints
{
    public function mySlip(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'Unauthorized.'],
            ], 401);
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->monthlyPayslipService->buildForUser(
                $user,
                (int) $validated['periodYear'],
                (int) $validated['periodMonth'],
                $this->activeCompanyId($request),
            ),
        ]);
    }

    public function mySlipLatestPeriod(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'Unauthorized.'],
            ], 401);
        }

        $companyId = $this->activeCompanyId($request);
        $latestRunQuery = HcmPayrollRun::query()
            ->with('period:id,period_year,period_month,status')
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->whereIn('purpose', [
                HcmPayrollRun::PURPOSE_MONTHLY,
                HcmPayrollRun::PURPOSE_THR,
                HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
            ])
            ->whereHas('period')
            ->whereHas('lines', fn ($query) => $query->where('user_id', $user->id))
            ->orderByDesc('hcm_payroll_period_id')
            ->orderByDesc('id');
        $this->applyTenantScope($latestRunQuery, $companyId);
        $latestRun = $latestRunQuery->first();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $latestRun?->period ? $this->serializePeriodBrief($latestRun->period) : null,
                'run' => $latestRun ? $this->serializeRunBrief($latestRun) : null,
            ],
        ]);
    }

    public function mySlipPdf(Request $request)
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'Unauthorized.'],
            ], 401);
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $slip = $this->monthlyPayslipService->buildForUser(
            $user,
            (int) $validated['periodYear'],
            (int) $validated['periodMonth'],
            $this->activeCompanyId($request),
        );

        if (($slip['run'] ?? null) === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_SLIP_NOT_FOUND',
                    'message' => 'No finalized payroll slip is available for the requested period.',
                ],
            ], 404);
        }

        $pdf = $this->monthlyPayslipService->renderPdf(
            $user,
            (int) $validated['periodYear'],
            (int) $validated['periodMonth'],
            $this->activeCompanyId($request),
        );
        $filename = strtolower((string) ($slip['slipNumber'] ?? 'payslip')).'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function sendMonthlySlips(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.disburse')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
            'userIds' => ['required', 'array', 'min:1'],
            'userIds.*' => [function (string $attribute, mixed $value, Closure $fail): void {
                $companyId = $this->activeCompanyId(request());
                if (! $this->userIdentifierExists($value, $companyId)) {
                    $fail("The selected {$attribute} is invalid.");
                }
            }],
        ]);

        $companyId = $this->activeCompanyId($request);
        $resolvedUserIds = $this->resolveUserIdsFromIdentifiers($validated['userIds'], $companyId);
        $periodQuery = HcmPayrollPeriod::query()
            ->where('period_year', $validated['periodYear'])
            ->where('period_month', $validated['periodMonth']);
        $this->applyTenantScope($periodQuery, $companyId);
        $period = $periodQuery->first();

        if ($period === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_PERIOD_NOT_FOUND',
                    'message' => 'Periode payroll tidak ditemukan.',
                ],
            ], 404);
        }

        $runQuery = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $period->id)
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
            ->orderByDesc('id');
        $this->applyTenantScope($runQuery, $companyId);
        $run = $runQuery->first();

        if ($run === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_SLIP_NOT_READY',
                    'message' => 'Slip gaji hanya bisa dikirim setelah payroll bulanan periode ini finalized.',
                ],
            ], 422);
        }

        $users = User::query()
            ->with(['employeeProfile.department', 'employeeProfile.designationRef'])
            ->whereIn('id', $resolvedUserIds->all())
            ->get()
            ->keyBy('id');

        $sentUserIds = [];
        $skipped = [];

        foreach ($resolvedUserIds as $userId) {
            $user = $users->get((int) $userId);
            if (! $user) {
                $skipped[] = ['userId' => (int) $userId, 'reason' => 'USER_NOT_FOUND'];

                continue;
            }

            $email = trim((string) ($user->email ?? ''));
            if ($email === '') {
                $skipped[] = ['userId' => (int) $userId, 'reason' => 'EMAIL_EMPTY'];

                continue;
            }

            $slip = $this->monthlyPayslipService->buildForUser(
                $user,
                (int) $validated['periodYear'],
                (int) $validated['periodMonth'],
                $companyId,
            );

            if (($slip['run'] ?? null) === null) {
                $skipped[] = ['userId' => (int) $userId, 'reason' => 'SLIP_NOT_FOUND'];

                continue;
            }

            $pdf = $this->monthlyPayslipService->renderPdf(
                $user,
                (int) $validated['periodYear'],
                (int) $validated['periodMonth'],
                $companyId,
            );

            $pdfContent = $pdf;
            $isEncrypted = false;
            $decryptionPassword = '';
            $companyName = Company::query()->where('id', $companyId)->value('name') ?? '';

            if (config('pdp.payslip_encryption_enabled', false)) {
                try {
                    $profile = $user->employeeProfile;
                    $nik = $profile?->nik ?? '';
                    if ($nik) {
                        $service = new PayslipEncryptionService;
                        $decryptionPassword = $service->deriveDefaultPassword($nik);
                        $pdfContent = $service->encrypt($pdf, $decryptionPassword);
                        $isEncrypted = true;
                    }
                } catch (\Throwable $e) {
                    // Encryption failure is non-fatal — send as plain PDF
                    \Illuminate\Support\Facades\Log::warning('Payslip encryption skipped', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                Mail::to($email)->send(new MonthlyPayslipMail(
                    $user,
                    $slip,
                    $pdfContent,
                    $companyName,
                    $isEncrypted,
                    $decryptionPassword,
                ));
                $sentUserIds[] = (int) $userId;
            } catch (\Throwable $exception) {
                $skipped[] = [
                    'userId' => (int) $userId,
                    'reason' => 'MAIL_SEND_FAILED',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sentUserIds' => $sentUserIds,
                'skipped' => $skipped,
            ],
        ]);
    }

    public function mySlipLines(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AUTH_REQUIRED', 'message' => 'Unauthorized.'],
            ], 401);
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $companyId = $this->activeCompanyId($request);
        $periodQuery = HcmPayrollPeriod::query()
            ->where('period_year', $validated['periodYear'])
            ->where('period_month', $validated['periodMonth']);
        $this->applyTenantScope($periodQuery, $companyId);
        $period = $periodQuery->first();

        if ($period === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'period' => null,
                    'run' => null,
                    'lines' => [],
                ],
            ]);
        }

        $monthlyRun = $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_MONTHLY, $companyId);
        $thrRun = $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_THR, $companyId);
        $pkwtRun = $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_PKWT_COMPENSATION, $companyId);

        $runs = collect([$monthlyRun, $thrRun, $pkwtRun])->filter();

        if ($runs->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $this->serializePeriodBrief($period),
                    'run' => null,
                    'runs' => [],
                    'lines' => [],
                ],
            ]);
        }

        $lines = collect();
        $runBriefs = [];
        foreach ($runs as $run) {
            $runBriefs[] = $this->serializeRunBrief($run);
            $chunk = HcmPayrollLine::query()
                ->where('hcm_payroll_run_id', $run->id)
                ->where('user_id', $user->id)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (HcmPayrollLine $line) => $this->serializeLine($line));
            $lines = $lines->concat($chunk);
        }

        $primaryRun = $monthlyRun ?? $thrRun ?? $pkwtRun;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $this->serializePeriodBrief($period),
                'run' => $primaryRun ? $this->serializeRunBrief($primaryRun) : null,
                'runs' => $runBriefs,
                'lines' => $lines->values()->all(),
            ],
        ]);
    }

    /**
     * Admin endpoint: list all employee payslip summaries for a period's finalized run.
     */
    public function adminRunSlips(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $companyId = $this->activeCompanyId($request);
        $periodQuery = HcmPayrollPeriod::query()
            ->where('period_year', $validated['periodYear'])
            ->where('period_month', $validated['periodMonth']);
        $this->applyTenantScope($periodQuery, $companyId);
        $period = $periodQuery->first();

        if ($period === null) {
            return response()->json([
                'success' => true,
                'data' => ['period' => null, 'run' => null, 'slips' => []],
            ]);
        }

        $runs = collect([
            $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_MONTHLY, $companyId),
            $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_THR, $companyId),
            $this->latestFinalizedRunForPurpose($period->id, HcmPayrollRun::PURPOSE_PKWT_COMPENSATION, $companyId),
        ])->filter();

        if ($runs->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => ['period' => $this->serializePeriodBrief($period), 'run' => null, 'slips' => []],
            ]);
        }

        $run = $runs->firstWhere('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
            ?? $runs->firstWhere('purpose', HcmPayrollRun::PURPOSE_THR)
            ?? $runs->firstWhere('purpose', HcmPayrollRun::PURPOSE_PKWT_COMPENSATION);

        $lines = HcmPayrollLine::query()
            ->whereIn('hcm_payroll_run_id', $runs->pluck('id')->all())
            ->with(['user:id,name,email', 'user.employeeProfile:user_id,designation,team'])
            ->orderBy('sort_order')
            ->get();

        $byUser = $lines->groupBy('user_id');

        $slips = $byUser->map(function ($userLines, $userId) use ($validated) {
            $first = $userLines->first();
            $user = $first->user;
            $earnings = $userLines->where('kind', 'addition')->values();
            $deductions = $userLines->where('kind', 'deduction')->values();

            $earningsTotal = round((float) $earnings->sum('amount'), 2);
            $deductionsTotal = round((float) $deductions->sum('amount'), 2);
            $netPay = round($earningsTotal - $deductionsTotal, 2);

            $meta = json_decode((string) ($first->meta ?? '{}'), true);
            $userName = $user?->name ?? ($meta['userName'] ?? "User {$userId}");
            $email = $user?->email ?? null;

            $profile = $user?->employeeProfile;

            return [
                'userId' => (int) $userId,
                'employeeName' => $userName,
                'email' => $email,
                'designation' => $profile?->designation ?? '—',
                'team' => $profile?->team ?? '—',
                'slipNumber' => 'SLP-'.$validated['periodYear'].sprintf('%02d', $validated['periodMonth']).'-'.sprintf('%04d', $userId),
                'earnings' => $serializedEarnings = $earnings->map(fn ($line) => $this->serializeLine($line))->values()->all(),
                'deductions' => $deductions->map(fn ($line) => $this->serializeLine($line))->values()->all(),
                'overtime' => $overtime = $this->summarizeOvertimeFromSerializedLines(collect($serializedEarnings)),
                'totals' => [
                    'earningsTotal' => $earningsTotal,
                    'deductionsTotal' => $deductionsTotal,
                    'overtimeTotal' => $overtime['amountTotal'],
                    'netPay' => $netPay,
                ],
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $this->serializePeriodBrief($period),
                'run' => $this->serializeRunBrief($run),
                'runs' => $runs->map(fn (HcmPayrollRun $item) => $this->serializeRunBrief($item))->values()->all(),
                'slips' => $slips,
            ],
        ]);
    }

    /**
     * Admin endpoint: list employee payslips across finalized monthly runs (all periods by default).
     */
    public function adminSlips(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'periodYear' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $companyId = $this->activeCompanyId($request);
        $runsQuery = HcmPayrollRun::query()
            ->with([
                'period',
                'lines.user:id,name,email',
                'lines.user.employeeProfile:user_id,designation,team',
            ])
            ->whereIn('purpose', [
                HcmPayrollRun::PURPOSE_MONTHLY,
                HcmPayrollRun::PURPOSE_THR,
                HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
            ])
            ->when(isset($validated['periodYear']), function ($query) use ($validated): void {
                $query->whereHas('period', fn ($periodQuery) => $periodQuery->where('period_year', (int) $validated['periodYear']));
            })
            ->when(isset($validated['periodMonth']), function ($query) use ($validated): void {
                $query->whereHas('period', fn ($periodQuery) => $periodQuery->where('period_month', (int) $validated['periodMonth']));
            })
            ->orderByDesc('hcm_payroll_period_id')
            ->orderByDesc('id');
        $this->applyTenantScope($runsQuery, $companyId);
        $runs = $runsQuery->get();

        $rows = collect();
        foreach ($runs as $run) {
            $byUser = $run->lines->groupBy('user_id');

            foreach ($byUser as $userId => $userLines) {
                $first = $userLines->first();
                $user = $first?->user;
                $profile = $user?->employeeProfile;
                $earnings = $userLines->where('kind', 'addition')->values();
                $deductions = $userLines->where('kind', 'deduction')->values();

                $earningsTotal = round((float) $earnings->sum('amount'), 2);
                $deductionsTotal = round((float) $deductions->sum('amount'), 2);
                $netPay = round($earningsTotal - $deductionsTotal, 2);

                $linePaymentStates = $userLines->map(function ($line): string {
                    $lineMeta = is_array($line->meta)
                        ? $line->meta
                        : (json_decode((string) ($line->meta ?? '{}'), true) ?: []);

                    return strtolower((string) ($lineMeta['paymentStatus'] ?? ''));
                })->filter(fn ($state) => $state !== '')->values();
                $paidCount = $linePaymentStates->filter(fn ($state) => $state === 'paid')->count();
                $paymentStatus = $run->status === HcmPayrollRun::STATUS_FINALIZED ? 'paid' : 'unpaid';
                if ($paidCount > 0 && $paidCount < $linePaymentStates->count()) {
                    $paymentStatus = 'partial';
                } elseif ($linePaymentStates->count() > 0 && $paidCount === 0) {
                    $paymentStatus = 'unpaid';
                } elseif ($linePaymentStates->count() > 0 && $paidCount === $linePaymentStates->count()) {
                    $paymentStatus = 'paid';
                }

                $metaRaw = $first?->meta;
                $meta = is_array($metaRaw)
                    ? $metaRaw
                    : (json_decode((string) ($metaRaw ?? '{}'), true) ?: []);
                $employeeName = $user?->name ?? ($meta['userName'] ?? ('User '.$userId));

                $rows->push([
                    'rowKey' => $run->id.'-'.$userId,
                    'runId' => (int) $run->id,
                    'periodYear' => (int) ($run->period?->period_year ?? 0),
                    'periodMonth' => (int) ($run->period?->period_month ?? 0),
                    'runStatus' => (string) $run->status,
                    'paymentStatus' => $paymentStatus,
                    'userId' => (int) $userId,
                    'employeeName' => $employeeName,
                    'email' => $user?->email,
                    'designation' => $profile?->designation ?? '—',
                    'team' => $profile?->team ?? '—',
                    'slipNumber' => 'SLP-'.(int) ($run->period?->period_year ?? 0).sprintf('%02d', (int) ($run->period?->period_month ?? 0)).'-'.sprintf('%04d', (int) $userId),
                    'earnings' => $serializedEarnings = $earnings->map(fn ($line) => $this->serializeLine($line))->values()->all(),
                    'deductions' => $deductions->map(fn ($line) => $this->serializeLine($line))->values()->all(),
                    'overtime' => $overtime = $this->summarizeOvertimeFromSerializedLines(collect($serializedEarnings)),
                    'totals' => [
                        'earningsTotal' => $earningsTotal,
                        'deductionsTotal' => $deductionsTotal,
                        'overtimeTotal' => $overtime['amountTotal'],
                        'netPay' => $netPay,
                    ],
                ]);
            }
        }

        $rows = $rows
            ->groupBy(fn (array $row) => ($row['periodYear'] ?? 0).'-'.($row['periodMonth'] ?? 0).'-'.($row['userId'] ?? 0))
            ->map(function ($items): array {
                $first = $items->first();
                $earnings = collect();
                $deductions = collect();
                $paidStates = collect();
                $runIds = [];

                foreach ($items as $item) {
                    $earnings = $earnings->concat($item['earnings'] ?? []);
                    $deductions = $deductions->concat($item['deductions'] ?? []);
                    $paidStates->push($item['paymentStatus'] ?? 'unpaid');
                    $runIds[] = (int) ($item['runId'] ?? 0);
                }

                $earningsTotal = round((float) $earnings->sum('amount'), 2);
                $deductionsTotal = round((float) $deductions->sum('amount'), 2);
                $overtime = $this->summarizeOvertimeFromSerializedLines($earnings);
                $paymentStatus = $paidStates->contains('partial')
                    ? 'partial'
                    : ($paidStates->contains('unpaid') && $paidStates->contains('paid') ? 'partial' : ($paidStates->contains('paid') ? 'paid' : 'unpaid'));

                return [
                    'rowKey' => ($first['periodYear'] ?? 0).'-'.($first['periodMonth'] ?? 0).'-'.($first['userId'] ?? 0),
                    'runId' => max($runIds),
                    'runIds' => array_values(array_unique($runIds)),
                    'periodYear' => $first['periodYear'] ?? 0,
                    'periodMonth' => $first['periodMonth'] ?? 0,
                    'runStatus' => 'finalized',
                    'paymentStatus' => $paymentStatus,
                    'userId' => $first['userId'] ?? 0,
                    'employeeName' => $first['employeeName'] ?? 'User',
                    'email' => $first['email'] ?? null,
                    'designation' => $first['designation'] ?? '—',
                    'team' => $first['team'] ?? '—',
                    'slipNumber' => $first['slipNumber'] ?? null,
                    'earnings' => $earnings->values()->all(),
                    'deductions' => $deductions->values()->all(),
                    'overtime' => $overtime,
                    'totals' => [
                        'earningsTotal' => $earningsTotal,
                        'deductionsTotal' => $deductionsTotal,
                        'overtimeTotal' => $overtime['amountTotal'],
                        'netPay' => round($earningsTotal - $deductionsTotal, 2),
                    ],
                ];
            })
            ->sortByDesc(fn (array $row) => sprintf('%04d%02d%08d', (int) ($row['periodYear'] ?? 0), (int) ($row['periodMonth'] ?? 0), (int) ($row['userId'] ?? 0)))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'rows' => $rows->values()->all(),
                'summary' => [
                    'totalRows' => $rows->count(),
                    'totalEmployees' => $rows->pluck('userId')->unique()->count(),
                    'totalPeriods' => $rows->map(fn ($row) => ($row['periodYear'] ?? 0).'-'.($row['periodMonth'] ?? 0))->unique()->count(),
                ],
            ],
        ]);
    }

    public function monthlyReport(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildMonthlyReportPayload($request),
        ]);
    }

    public function exportMonthlyReport(Request $request): Response
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $this->validateMonthlyReportFilters($request);
        $payload = $this->buildMonthlyReportPayload($request, $validated);
        $rows = collect($payload['rows'] ?? []);
        $format = strtolower((string) ($request->query('format', 'xlsx')));
        if (! in_array($format, ['xlsx', 'csv'], true)) {
            $format = 'xlsx';
        }

        $headers = [
            'period',
            'employee_id',
            'employee_name',
            'email',
            'designation',
            'team',
            'bank_name',
            'account_number',
            'bank_branch',
            'payment_status',
            'monthly_run_id',
            'monthly_gross',
            'monthly_overtime',
            'monthly_deductions',
            'monthly_net',
            'thr_run_id',
            'thr_gross',
            'thr_overtime',
            'thr_deductions',
            'thr_net',
            'pkwt_run_id',
            'pkwt_gross',
            'pkwt_overtime',
            'pkwt_deductions',
            'pkwt_net',
            'total_gross',
            'total_overtime',
            'total_deductions',
            'total_net',
        ];

        $exportRows = $rows->map(function (array $row): array {
            $breakdown = $row['breakdown'] ?? [];
            $monthly = $breakdown[HcmPayrollRun::PURPOSE_MONTHLY] ?? [];
            $thr = $breakdown[HcmPayrollRun::PURPOSE_THR] ?? [];
            $pkwt = $breakdown[HcmPayrollRun::PURPOSE_PKWT_COMPENSATION] ?? [];

            return [
                sprintf('%04d-%02d', (int) ($row['periodYear'] ?? 0), (int) ($row['periodMonth'] ?? 0)),
                (string) ($row['userId'] ?? ''),
                (string) ($row['employeeName'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['designation'] ?? ''),
                (string) ($row['team'] ?? ''),
                (string) ($row['bankName'] ?? ''),
                (string) ($row['accountNumber'] ?? ''),
                (string) ($row['bankBranch'] ?? ''),
                (string) ($row['paymentStatus'] ?? 'unpaid'),
                (string) ($monthly['runId'] ?? ''),
                (string) round((float) ($monthly['earningsTotal'] ?? 0), 2),
                (string) round((float) (($monthly['overtime']['amountTotal'] ?? 0)), 2),
                (string) round((float) ($monthly['deductionsTotal'] ?? 0), 2),
                (string) round((float) ($monthly['netPay'] ?? 0), 2),
                (string) ($thr['runId'] ?? ''),
                (string) round((float) ($thr['earningsTotal'] ?? 0), 2),
                (string) round((float) (($thr['overtime']['amountTotal'] ?? 0)), 2),
                (string) round((float) ($thr['deductionsTotal'] ?? 0), 2),
                (string) round((float) ($thr['netPay'] ?? 0), 2),
                (string) ($pkwt['runId'] ?? ''),
                (string) round((float) ($pkwt['earningsTotal'] ?? 0), 2),
                (string) round((float) (($pkwt['overtime']['amountTotal'] ?? 0)), 2),
                (string) round((float) ($pkwt['deductionsTotal'] ?? 0), 2),
                (string) round((float) ($pkwt['netPay'] ?? 0), 2),
                (string) round((float) ($row['totals']['earningsTotal'] ?? 0), 2),
                (string) round((float) ($row['totals']['overtimeTotal'] ?? 0), 2),
                (string) round((float) ($row['totals']['deductionsTotal'] ?? 0), 2),
                (string) round((float) ($row['totals']['netPay'] ?? 0), 2),
            ];
        })->values()->all();

        $fileBase = 'monthly-report-'.now()->format('YmdHis');

        return TabularExportResponse::download($headers, $exportRows, $fileBase, $format, 'Monthly Report');
    }
}
