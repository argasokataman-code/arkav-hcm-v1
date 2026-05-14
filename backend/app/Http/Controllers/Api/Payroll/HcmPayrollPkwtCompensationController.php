<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollRun;
use App\Services\Hcm\PkwtCompensationService;
use App\Services\Reconciliation\Exceptions\ExportReconciliationException;
use App\Services\Reconciliation\ReconciliationGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmPayrollPkwtCompensationController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        private readonly PkwtCompensationService $pkwtCompensationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $companyId = $this->activeCompanyId($request);

        return response()->json([
            'success' => true,
            'data' => [
                'preview' => $this->pkwtCompensationService->previewForMonth(
                    (int) $validated['periodYear'],
                    (int) $validated['periodMonth'],
                    $companyId,
                ),
                'run' => $this->serializeRun(
                    $this->pkwtCompensationService->currentRunForMonth(
                        (int) $validated['periodYear'],
                        (int) $validated['periodMonth'],
                        $companyId,
                    )
                ),
            ],
        ]);
    }

    public function postPayroll(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'periodYear' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodMonth' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        if ($error = $this->guardPkwtReconciliation($request, (int) $validated['periodYear'], (int) $validated['periodMonth'])) {
            return $error;
        }

        try {
            $result = $this->pkwtCompensationService->createOrReplaceDraftRun(
                (int) $validated['periodYear'],
                (int) $validated['periodMonth'],
                $request->user()?->id,
                $this->activeCompanyId($request),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $e->getMessage(),
                    'message' => match ($e->getMessage()) {
                        'PKWT_COMPENSATION_EMPTY' => 'Tidak ada kompensasi PKWT eligible untuk periode ini.',
                        'PKWT_COMPENSATION_COMPONENT_MISSING' => 'Komponen gaji kompensasi PKWT belum tersedia di master.',
                        'PKWT_COMPENSATION_FINALIZED_EXISTS' => 'Periode ini sudah memiliki payroll kompensasi PKWT finalized.',
                        default => 'Gagal membuat payroll kompensasi PKWT.',
                    },
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'id' => $result['period']->id,
                    'periodYear' => $result['period']->period_year,
                    'periodMonth' => $result['period']->period_month,
                    'status' => $result['period']->status,
                ],
                'run' => $this->serializeRun($result['run']),
                'preview' => $result['preview'],
            ],
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'contractStartDate' => ['required', 'date'],
            'contractEndDate' => ['required', 'date'],
            'baseMonthlySalary' => ['required', 'numeric', 'min:0'],
            'fixedMonthlyAllowance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $this->pkwtCompensationService->calculate(
            $validated['contractStartDate'],
            $validated['contractEndDate'],
            (float) $validated['baseMonthlySalary'],
            (float) ($validated['fixedMonthlyAllowance'] ?? 0),
        );

        $result['regulationReference'] = PkwtCompensationService::REGULATION_LABEL;

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeRun(?HcmPayrollRun $run): ?array
    {
        if ($run === null) {
            return null;
        }

        $payment = $this->paymentSummary($run);

        return [
            'id' => $run->id,
            'purpose' => $run->purpose ?? HcmPayrollRun::PURPOSE_PKWT_COMPENSATION,
            'status' => $run->status,
            'finalizedAt' => $run->finalized_at?->toIso8601String(),
            'period' => $run->period ? [
                'id' => $run->period->id,
                'periodYear' => $run->period->period_year,
                'periodMonth' => $run->period->period_month,
                'status' => $run->period->status,
            ] : null,
            'payment' => $payment,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSummary(HcmPayrollRun $run): array
    {
        $lines = $run->relationLoaded('lines') ? $run->lines : $run->lines()->get(['user_id', 'meta']);

        $perUser = $lines->groupBy('user_id');
        $paidUserIds = [];
        $latestPaidAt = null;
        $latestGatewayReference = null;

        foreach ($perUser as $userId => $items) {
            $paidMeta = collect($items)
                ->map(fn (HcmPayrollLine $line) => is_array($line->meta) ? $line->meta : [])
                ->first(fn (array $meta) => strtolower((string) ($meta['paymentStatus'] ?? 'unpaid')) === 'paid');

            if ($paidMeta === null) {
                continue;
            }

            $paidUserIds[] = (int) $userId;
            $paidAt = isset($paidMeta['paidAt']) ? (string) $paidMeta['paidAt'] : null;
            if ($paidAt !== null && ($latestPaidAt === null || strtotime($paidAt) >= strtotime((string) $latestPaidAt))) {
                $latestPaidAt = $paidAt;
                $latestGatewayReference = isset($paidMeta['gatewayReference']) ? (string) $paidMeta['gatewayReference'] : $latestGatewayReference;
            }
        }

        $employeeCount = $perUser->count();
        $paidCount = count($paidUserIds);

        return [
            'status' => $paidCount === 0 ? 'unpaid' : ($paidCount < $employeeCount ? 'partial' : 'paid'),
            'employeeCount' => $employeeCount,
            'paidEmployeeCount' => $paidCount,
            'paidUserIds' => array_values(array_unique($paidUserIds)),
            'paidAt' => $latestPaidAt,
            'gatewayReference' => $latestGatewayReference,
        ];
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function guardPkwtReconciliation(Request $request, int $periodYear, int $periodMonth): ?JsonResponse
    {
        if (! (bool) config('hcm.export_reconciliation.enabled', true)) {
            return null;
        }

        if (! (bool) config('hcm.export_reconciliation.enforce.pkwt_compensation.post_payroll', false)) {
            return null;
        }

        $reconciliation = $request->input('reconciliation', []);
        $filterPayload = is_array($reconciliation['filterPayload'] ?? null) ? $reconciliation['filterPayload'] : [];
        $datasetChecksum = isset($reconciliation['datasetChecksum']) ? (string) $reconciliation['datasetChecksum'] : null;
        $strictChecksum = (bool) ($reconciliation['strictChecksum'] ?? config('hcm.export_reconciliation.strict_checksum', false));

        try {
            app(ReconciliationGateService::class)->assertCanProceed(
                $this->activeCompanyId($request),
                'pkwt_compensation',
                'post_payroll',
                sprintf('%d-%02d', $periodYear, $periodMonth),
                $filterPayload,
                $datasetChecksum,
                $strictChecksum,
            );
        } catch (ExportReconciliationException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                ],
            ], $exception->status());
        }

        return null;
    }
}
