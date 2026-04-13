<?php

namespace App\Services\Hcm;

use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

final class MonthlyPayslipService
{
    /**
     * @return array<string, mixed>
     */
    public function buildForUser(User $user, int $periodYear, int $periodMonth, ?int $companyId = null): array
    {
        $periodQuery = HcmPayrollPeriod::query()
            ->where('period_year', $periodYear)
            ->where('period_month', $periodMonth);
        $this->applyTenantScope($periodQuery, $companyId);
        $period = $periodQuery->first();

        if ($period === null) {
            return [
                'period' => null,
                'run' => null,
                'runs' => [],
                'employee' => $this->serializeEmployee($user),
                'earnings' => [],
                'deductions' => [],
                'totals' => [
                    'earningsTotal' => 0.0,
                    'deductionsTotal' => 0.0,
                    'netPay' => 0.0,
                ],
                'slipNumber' => $this->slipNumber($periodYear, $periodMonth, $user->id),
                'downloadUrl' => null,
            ];
        }

        $monthlyRun = $this->latestFinalizedRun($period->id, HcmPayrollRun::PURPOSE_MONTHLY, $companyId);
        $thrRun = $this->latestFinalizedRun($period->id, HcmPayrollRun::PURPOSE_THR, $companyId);
        $pkwtRun = $this->latestFinalizedRun($period->id, HcmPayrollRun::PURPOSE_PKWT_COMPENSATION, $companyId);

        $runs = collect([$monthlyRun, $thrRun, $pkwtRun])->filter();
        $primaryRun = $monthlyRun ?? $thrRun ?? $pkwtRun;

        if ($runs->isEmpty()) {
            return [
                'period' => $this->serializePeriod($period),
                'run' => null,
                'runs' => [],
                'employee' => $this->serializeEmployee($user),
                'earnings' => [],
                'deductions' => [],
                'totals' => [
                    'earningsTotal' => 0.0,
                    'deductionsTotal' => 0.0,
                    'netPay' => 0.0,
                ],
                'slipNumber' => $this->slipNumber($periodYear, $periodMonth, $user->id),
                'downloadUrl' => null,
            ];
        }

        $lines = collect();
        $runBriefs = [];

        foreach ($runs as $run) {
            $runBriefs[] = $this->serializeRun($run);
            $chunk = HcmPayrollLine::query()
                ->where('hcm_payroll_run_id', $run->id)
                ->where('user_id', $user->id)
                ->orderBy('sort_order')
                ->get();
            $lines = $lines->concat($chunk);
        }

        $serializedLines = $lines->values()->map(fn (HcmPayrollLine $line) => $this->serializeLine($line));
        $netAffecting = $serializedLines->where('affectsNetPay', true)->values();
        $earnings = $netAffecting->where('kind', 'addition')->values();
        $deductions = $netAffecting->where('kind', 'deduction')->values();

        $earningsTotal = round((float) $earnings->sum('amount'), 2);
        $deductionsTotal = round((float) $deductions->sum('amount'), 2);
        $netPay = round($earningsTotal - $deductionsTotal, 2);

        return [
            'period' => $this->serializePeriod($period),
            'run' => $primaryRun ? $this->serializeRun($primaryRun) : null,
            'runs' => $runBriefs,
            'employee' => $this->serializeEmployee($user),
            'earnings' => $earnings->all(),
            'deductions' => $deductions->all(),
            'totals' => [
                'earningsTotal' => $earningsTotal,
                'deductionsTotal' => $deductionsTotal,
                'netPay' => $netPay,
            ],
            'slipNumber' => $this->slipNumber($periodYear, $periodMonth, $user->id),
            'downloadUrl' => url('/v1/hcm/payroll/my-slip-pdf?periodYear='.$periodYear.'&periodMonth='.$periodMonth),
        ];
    }

    public function renderPdf(User $user, int $periodYear, int $periodMonth, ?int $companyId = null): string
    {
        $slip = $this->buildForUser($user, $periodYear, $periodMonth, $companyId);

        $html = View::make('pdf.monthly-payslip', [
            'slip' => $slip,
            'logoDataUri' => $this->logoAsDataUri(),
            'companyAddress' => config('hcm.organization_address'),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePeriod(HcmPayrollPeriod $period): array
    {
        return [
            'id' => $period->id,
            'periodYear' => $period->period_year,
            'periodMonth' => $period->period_month,
            'status' => $period->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRun(HcmPayrollRun $run): array
    {
        return [
            'id' => $run->id,
            'purpose' => $run->purpose ?? HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => $run->status,
            'finalizedAt' => $run->finalized_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEmployee(User $user): array
    {
        $profile = $user->employeeProfile;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'team' => $profile?->team,
            'designation' => $profile?->designation ?: $profile?->designationRef?->name,
            'bankName' => $profile?->bank_name,
            'bankAccountNo' => $profile?->bank_account_no,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLine(HcmPayrollLine $line): array
    {
        $meta = is_array($line->meta) ? $line->meta : [];
        $affectsNetPay = array_key_exists('affectsNetPay', $meta)
            ? (bool) $meta['affectsNetPay']
            : ((string) $line->category !== 'employer_cost_display');

        return [
            'id' => $line->id,
            'componentCode' => $line->component_code,
            'componentName' => $line->component_name,
            'kind' => $line->kind,
            'category' => $line->category,
            'amount' => round((float) $line->amount, 2),
            'sortOrder' => $line->sort_order,
            'affectsNetPay' => $affectsNetPay,
            'meta' => $meta,
        ];
    }

    private function slipNumber(int $year, int $month, int $userId): string
    {
        return sprintf('PS-%04d-%02d-%d', $year, $month, $userId);
    }

    private function latestFinalizedRun(int $periodId, string $purpose, ?int $companyId = null): ?HcmPayrollRun
    {
        $query = HcmPayrollRun::query()
            ->where('hcm_payroll_period_id', $periodId)
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->where('purpose', $purpose)
            ->orderByDesc('id');
        $this->applyTenantScope($query, $companyId);

        return $query->first();
    }

    private function applyTenantScope($query, ?int $companyId): void
    {
        if ($companyId === null) {
            return;
        }

        $query->where(function ($q) use ($companyId): void {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }

    private function logoAsDataUri(): ?string
    {
        $candidates = [
            public_path('build/img/image111.png'),
            public_path('build/img/favicon.png'),
        ];

        foreach ($candidates as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }
            $raw = @file_get_contents($path);
            if (! is_string($raw) || $raw === '') {
                continue;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                default => 'application/octet-stream',
            };

            return 'data:'.$mime.';base64,'.base64_encode($raw);
        }

        return null;
    }
}
