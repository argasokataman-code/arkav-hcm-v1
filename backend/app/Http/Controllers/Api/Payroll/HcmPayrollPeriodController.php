<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Support\PayrollDraftBuilder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HcmPayrollPeriodController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $query = HcmPayrollPeriod::query();
        $this->applyTenantScope($query, $companyId);

        $rows = $query
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(100)
            ->get()
            ->map(fn (HcmPayrollPeriod $p) => $this->serializePeriod($p));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $companyId = $this->activeCompanyId($request);
        $existsQuery = HcmPayrollPeriod::query()
            ->where('period_year', $validated['periodYear'])
            ->where('period_month', $validated['periodMonth']);
        $this->applyTenantScope($existsQuery, $companyId);
        if ($existsQuery->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_PERIOD_EXISTS',
                    'message' => 'Payroll period already exists.',
                ],
            ], 422);
        }

        try {
            $period = HcmPayrollPeriod::query()->create([
                'company_id' => $companyId,
                'period_year' => $validated['periodYear'],
                'period_month' => $validated['periodMonth'],
                'status' => HcmPayrollPeriod::STATUS_OPEN,
            ]);
        } catch (QueryException $e) {
            // Guard against race-condition inserts that hit unique(company_id, year, month).
            if ($this->isDuplicateKey($e)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PAYROLL_PERIOD_EXISTS',
                        'message' => 'Payroll period already exists.',
                    ],
                ], 422);
            }

            throw $e;
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializePeriod($period),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $periodQuery = HcmPayrollPeriod::query()->with('company');
        $this->applyTenantScope($periodQuery, $companyId);
        $period = $periodQuery->where('id', $id)->firstOrFail();

        $latestRunQuery = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $period->id)
            ->where('status', '!=', HcmPayrollRun::STATUS_VOID)
            ->orderByDesc('id');
        $this->applyTenantScope($latestRunQuery, $companyId);
        $latestRun = $latestRunQuery->first();

        return response()->json([
            'success' => true,
            'data' => array_merge($this->serializePeriod($period), [
                'latestRun' => $latestRun ? $this->serializeRunSummary($latestRun) : null,
            ]),
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $now = Carbon::now('Asia/Jakarta');
        $activeQuery = HcmPayrollPeriod::query()
            ->with('company')
            ->where('status', HcmPayrollPeriod::STATUS_OPEN)
            ->where(function ($q) use ($now): void {
                $q->where('period_year', '<', $now->year)
                    ->orWhere(function ($q2) use ($now): void {
                        $q2->where('period_year', $now->year)
                            ->where('period_month', '<=', $now->month);
                    });
            })
            ->orderByDesc('period_year')
            ->orderByDesc('period_month');
        $this->applyTenantScope($activeQuery, $companyId);
        $active = $activeQuery->first();

        if ($active === null) {
            $currentMonthQuery = HcmPayrollPeriod::query()
                ->where('period_year', (int) $now->year)
                ->where('period_month', (int) $now->month);
            $this->applyTenantScope($currentMonthQuery, $companyId);

            // If current month period already exists (even posted), reuse it instead of inserting duplicate.
            $active = $currentMonthQuery
                ->orderByRaw('company_id IS NULL')
                ->orderByDesc('id')
                ->first();

            if ($active === null) {
                try {
                    $active = HcmPayrollPeriod::query()->create([
                        'company_id' => $companyId,
                        'period_year' => (int) $now->year,
                        'period_month' => (int) $now->month,
                        'status' => HcmPayrollPeriod::STATUS_OPEN,
                    ]);
                } catch (QueryException $e) {
                    // Another request may create it first; load and continue.
                    if (! $this->isDuplicateKey($e)) {
                        throw $e;
                    }

                    $active = HcmPayrollPeriod::query()
                        ->where('period_year', (int) $now->year)
                        ->where('period_month', (int) $now->month)
                        ->where('company_id', $companyId)
                        ->firstOrFail();
                }
            }
        }

        $latestRunQuery = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $active->id)
            ->where('status', '!=', HcmPayrollRun::STATUS_VOID)
            ->orderByDesc('id');
        $this->applyTenantScope($latestRunQuery, $companyId);
        $latestRun = $latestRunQuery->first();

        return response()->json([
            'success' => true,
            'data' => array_merge($this->serializePeriod($active), [
                'latestRun' => $latestRun ? $this->serializeRunSummary($latestRun) : null,
            ]),
        ]);
    }

    public function calculateDraft(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.run');
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $periodQuery = HcmPayrollPeriod::query();
        $this->applyTenantScope($periodQuery, $companyId);
        $period = $periodQuery->where('id', $id)->firstOrFail();

        $finalizedExistsQuery = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $period->id)
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY);
        $this->applyTenantScope($finalizedExistsQuery, $companyId);
        $finalizedExists = $finalizedExistsQuery->exists();

        if ($finalizedExists) {
            if (! app()->environment(['local', 'development', 'testing'])) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PAYROLL_PERIOD_FINALIZED',
                        'message' => 'This period already has a finalized payroll run; void or use a new period before recalculating.',
                    ],
                ], 422);
            }

            // Dev helper: void finalized monthly runs and clear payment metadata so draft can be recalculated.
            DB::transaction(function () use ($period, $companyId): void {
                $finalizedRunsQuery = HcmPayrollRun::query()
                    ->where('hcm_payroll_period_id', $period->id)
                    ->where('status', HcmPayrollRun::STATUS_FINALIZED)
                    ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
                    ->lockForUpdate();
                $this->applyTenantScope($finalizedRunsQuery, $companyId);
                $finalizedRuns = $finalizedRunsQuery->get();

                foreach ($finalizedRuns as $finalizedRun) {
                    $lines = HcmPayrollLine::query()
                        ->where('hcm_payroll_run_id', $finalizedRun->id)
                        ->lockForUpdate()
                        ->get();

                    foreach ($lines as $line) {
                        $meta = is_array($line->meta)
                            ? $line->meta
                            : (json_decode((string) ($line->meta ?? '{}'), true) ?: []);
                        unset($meta['paymentStatus'], $meta['paidAt'], $meta['gatewayReference'], $meta['paymentChannel']);
                        $line->meta = $meta;
                        $line->save();
                    }

                    $finalizedRun->update([
                        'status' => HcmPayrollRun::STATUS_VOID,
                    ]);
                }

                $period->update([
                    'status' => HcmPayrollPeriod::STATUS_OPEN,
                ]);
            });
        }

        // Always rebuild draft from latest tenant-scoped employee source of truth.
        // This keeps "Calculate Draft" aligned with its refresh behavior in UI.
        $run = PayrollDraftBuilder::rebuildDraftRun($period, $companyId);
        $lines = HcmPayrollLine::query()
            ->where('hcm_payroll_run_id', $run->id)
            ->orderBy('user_id')
            ->orderBy('sort_order')
            ->get();
        $lineCount = $lines->count();
        $employeeCount = $lines->pluck('user_id')->filter()->unique()->count();
        $details = $lines->groupBy('user_id')->map(function ($items, $userId): array {
            $grossPay = round((float) $items->where('kind', 'addition')->sum('amount'), 2);
            $deductionsTotal = round((float) $items->where('kind', 'deduction')->sum('amount'), 2);

            return [
                'employee_id' => (int) $userId,
                'gross_pay' => $grossPay,
                'deductions_total' => $deductionsTotal,
                'net_pay' => round($grossPay - $deductionsTotal, 2),
                'line_count' => $items->count(),
            ];
        })->values()->all();

        $missingTaxProfileUserIds = $lines
            ->filter(function (HcmPayrollLine $line): bool {
                $meta = is_array($line->meta) ? $line->meta : [];

                return ($line->component_code === 'pph21_ter' || $line->category === 'pph21_ter')
                    && (($meta['missingTaxProfile'] ?? false) === true);
            })
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'run' => array_merge($this->serializeRunSummary($run), ['details' => $details]),
                'lineCount' => $lineCount,
                'employeeCount' => $employeeCount,
                'anomalies' => [
                    'missingTaxProfileUserCount' => count($missingTaxProfileUserIds),
                    'missingTaxProfileUserIds' => $missingTaxProfileUserIds,
                ],
                'reusedExistingDraft' => false,
            ],
        ]);
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000'
            || str_contains(strtolower($e->getMessage()), 'duplicate entry');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePeriod(HcmPayrollPeriod $p): array
    {
        $company = $p->relationLoaded('company') ? $p->company : null;

        return [
            'id' => $p->id,
            'periodYear' => $p->period_year,
            'periodMonth' => $p->period_month,
            'status' => $p->status,
            'companyId' => $p->company_id,
            'companyName' => $company?->name ?? null,
            'companyCode' => $company?->code ?? null,
            'createdAt' => $p->created_at?->toIso8601String(),
            'updatedAt' => $p->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRunSummary(HcmPayrollRun $r): array
    {
        $meta = is_array($r->meta) ? $r->meta : [];

        return [
            'id' => $r->id,
            'payrollPeriodId' => $r->hcm_payroll_period_id,
            'purpose' => $r->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => $r->status,
            'calculatedAt' => $r->calculated_at?->toIso8601String(),
            'finalizedAt' => $r->finalized_at?->toIso8601String(),
            'finalizedByUserId' => $r->finalized_by_user_id,
            'policySnapshot' => is_array($meta['policySnapshot'] ?? null) ? $meta['policySnapshot'] : null,
            'lateArrivalBuffer' => is_array($meta['lateArrivalBuffer'] ?? null) ? $meta['lateArrivalBuffer'] : null,
        ];
    }

    private function activeCompanyId(Request $request): ?int
    {
        return $request->attributes->get('activeCompanyId');
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        if ($companyId === null) {
            return $query;
        }

        return $query->where(function ($q) use ($companyId): void {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }
}
