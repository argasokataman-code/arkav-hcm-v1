<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\HcmThrYearlySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmPayrollThrSettingsController extends Controller
{
    use EnsuresHcmAdmin;

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $rows = HcmThrYearlySetting::query()
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

    public function upsert(Request $request, int $calendarYear): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        if ($calendarYear < 2000 || $calendarYear > 2100) {
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

        $row = HcmThrYearlySetting::query()->updateOrCreate(
            ['calendar_year' => $calendarYear],
            [
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
}
