<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Support\PayrollDraftBuilder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HcmPayrollPeriodController extends Controller
{
    use EnsuresHcmAdmin;

    public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $rows = HcmPayrollPeriod::query()
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(100)
            ->get()
            ->map(fn (HcmPayrollPeriod $p) => $this->serializePeriod($p));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $exists = HcmPayrollPeriod::query()
            ->where('period_year', $validated['periodYear'])
            ->where('period_month', $validated['periodMonth'])
            ->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_PERIOD_EXISTS',
                    'message' => 'Payroll period already exists.',
                ],
            ], 422);
        }

        $period = HcmPayrollPeriod::query()->create([
            'period_year' => $validated['periodYear'],
            'period_month' => $validated['periodMonth'],
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->serializePeriod($period),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $period = HcmPayrollPeriod::query()->findOrFail($id);
        $latestRun = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $period->id)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => array_merge($this->serializePeriod($period), [
                'latestRun' => $latestRun ? $this->serializeRunSummary($latestRun) : null,
            ]),
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $now = Carbon::now('Asia/Jakarta');
        $active = HcmPayrollPeriod::query()
            ->where('status', HcmPayrollPeriod::STATUS_OPEN)
            ->where(function ($q) use ($now): void {
                $q->where('period_year', '<', $now->year)
                    ->orWhere(function ($q2) use ($now): void {
                        $q2->where('period_year', $now->year)
                            ->where('period_month', '<=', $now->month);
                    });
            })
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        if ($active === null) {
            $active = HcmPayrollPeriod::query()->firstOrCreate([
                'period_year' => (int) $now->year,
                'period_month' => (int) $now->month,
            ], [
                'status' => HcmPayrollPeriod::STATUS_OPEN,
            ]);
        }

        $latestRun = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $active->id)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => array_merge($this->serializePeriod($active), [
                'latestRun' => $latestRun ? $this->serializeRunSummary($latestRun) : null,
            ]),
        ]);
    }

    public function calculateDraft(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $period = HcmPayrollPeriod::query()->findOrFail($id);

        $finalizedExists = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $period->id)
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
            ->exists();

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
            DB::transaction(function () use ($period): void {
                $finalizedRuns = HcmPayrollRun::query()
                    ->where('hcm_payroll_period_id', $period->id)
                    ->where('status', HcmPayrollRun::STATUS_FINALIZED)
                    ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
                    ->lockForUpdate()
                    ->get();

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

        $run = PayrollDraftBuilder::rebuildDraftRun($period);
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
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePeriod(HcmPayrollPeriod $p): array
    {
        return [
            'id' => $p->id,
            'periodYear' => $p->period_year,
            'periodMonth' => $p->period_month,
            'status' => $p->status,
            'createdAt' => $p->created_at?->toIso8601String(),
            'updatedAt' => $p->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRunSummary(HcmPayrollRun $r): array
    {
        return [
            'id' => $r->id,
            'payrollPeriodId' => $r->hcm_payroll_period_id,
            'purpose' => $r->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => $r->status,
            'calculatedAt' => $r->calculated_at?->toIso8601String(),
            'finalizedAt' => $r->finalized_at?->toIso8601String(),
            'finalizedByUserId' => $r->finalized_by_user_id,
        ];
    }
}
