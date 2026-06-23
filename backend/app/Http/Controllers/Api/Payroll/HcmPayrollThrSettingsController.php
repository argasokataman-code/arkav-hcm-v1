<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmThrYearlySetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmPayrollThrSettingsController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        $rows = HcmThrYearlySetting::query()
            ->where(function (Builder $query) use ($companyId): void {
                if ($companyId !== null) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id');
            })
            ->orderByDesc('calendar_year')
            ->limit(25)
            ->get()
            ->map(fn (HcmThrYearlySetting $r) => $this->toApiRow($r));

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $rows,
            ],
        ]);
    }

    public function upsert(Request $request, string $calendarYear): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.thr.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        if (! ctype_digit($calendarYear)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'THR_SETTINGS_YEAR_INVALID',
                    'message' => 'calendarYear must be a numeric year between 2000 and 2100.',
                ],
            ], 422);
        }

        $calendarYearInt = (int) $calendarYear;
        if ($calendarYearInt < 2000 || $calendarYearInt > 2100) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'THR_SETTINGS_YEAR_INVALID',
                    'message' => 'calendarYear must be between 2000 and 2100.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'eidDate' => ['required', 'date'],
            'paymentDate' => ['nullable', 'date'],
            'calculationCutoffDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $paymentDate = $validated['paymentDate'] ?? null;
        $calculationCutoffDate = $validated['calculationCutoffDate'] ?? null;
        $notes = $validated['notes'] ?? null;
        if ($paymentDate === '') {
            $paymentDate = null;
        }
        if ($calculationCutoffDate === '') {
            $calculationCutoffDate = null;
        }
        if ($notes === '') {
            $notes = null;
        }

        $existing = HcmThrYearlySetting::query()
            ->where('company_id', $companyId)
            ->where('calendar_year', $calendarYearInt)
            ->first();

        if ($existing && $existing->calculation_cutoff_date && $calculationCutoffDate && $calculationCutoffDate < $existing->calculation_cutoff_date->toDateString()) {
             return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'THR_CUTOFF_DATE_INVALID',
                    'message' => 'Tanggal cut-off tidak boleh lebih awal dari tanggal cut-off yang sudah tersimpan.',
                ],
            ], 422);
        }

        $row = HcmThrYearlySetting::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'calendar_year' => $calendarYearInt,
            ],
            [
                'company_id' => $companyId,
                'eid_date' => $validated['eidDate'],
                'payment_date' => $paymentDate,
                'calculation_cutoff_date' => $calculationCutoffDate,
                'notes' => $notes,
            ],
        );

        return response()->json([
            'success' => true,
            'data' => $this->toApiRow($row->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toApiRow(HcmThrYearlySetting $r): array
    {
        return [
            'calendarYear' => (int) $r->calendar_year,
            'eidDate' => $r->eid_date->toDateString(),
            'paymentDate' => optional($r->payment_date)->toDateString(),
            'calculationCutoffDate' => optional($r->calculation_cutoff_date)->toDateString(),
            'notes' => $r->notes,
            'updatedAt' => optional($r->updated_at)->toIso8601String(),
        ];
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }
}
