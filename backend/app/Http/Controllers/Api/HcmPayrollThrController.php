<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Services\Hcm\ThrProRataCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmPayrollThrController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        private readonly ThrProRataCalculator $calculator
    ) {}

    /**
     * Estimasi THR bruto (Permenaker 6/2016, pro rata). Bukan slip final / bukan perhitungan pajak.
     */
    public function calculate(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'joinDate' => ['required', 'date'],
            'cutoffDate' => ['required', 'date'],
            'baseMonthlySalary' => ['required', 'numeric', 'min:0'],
            'fixedMonthlyAllowance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = $this->calculator->calculate(
            $validated['joinDate'],
            $validated['cutoffDate'],
            (float) $validated['baseMonthlySalary'],
            (float) ($validated['fixedMonthlyAllowance'] ?? 0),
        );

        $result['regulationReference'] = ThrProRataCalculator::REGULATION_LABEL;

        return response()->json(['success' => true, 'data' => $result]);
    }
}
