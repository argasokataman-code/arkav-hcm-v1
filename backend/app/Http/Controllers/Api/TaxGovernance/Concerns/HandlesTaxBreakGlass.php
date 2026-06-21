<?php

namespace App\Http\Controllers\Api\TaxGovernance\Concerns;

use App\Models\Company;
use App\Models\HcmTaxGovernanceBreakGlassRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesTaxBreakGlass
{
    public function requestBreakGlassAccess(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.break_glass.request')) {
            return $response;
        }

        if (! ($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $validated = $request->validate([
            'targetTenantUuid' => ['required', 'string', 'uuid', 'exists:companies,uuid'],
            'reasonCode' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $targetCompany = Company::query()->where('uuid', $validated['targetTenantUuid'])->firstOrFail();
        $record = HcmTaxGovernanceBreakGlassRequest::query()->create([
            'target_company_id' => (int) $targetCompany->id,
            'target_company_uuid' => (string) $targetCompany->uuid,
            'requested_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            'reason_code' => $validated['reasonCode'],
            'reason' => $validated['reason'],
            'status' => 'requested',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->breakGlassPayload($record),
        ], 201);
    }

    public function approveBreakGlassRequest(Request $request, string $requestUuid): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.break_glass.approve')) {
            return $response;
        }

        if (! ($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $validated = $request->validate([
            'approvalNote' => ['required', 'string', 'max:1000'],
            'expiresAt' => ['required', 'date', 'after:now'],
        ]);

        $record = HcmTaxGovernanceBreakGlassRequest::query()
            ->where('uuid', $requestUuid)
            ->first();

        if (! $record) {
            return $this->errorResponse('BREAK_GLASS_REQUEST_NOT_FOUND', 'Break-glass request not found.', 404);
        }

        if ($record->status !== 'requested') {
            return $this->errorResponse('BREAK_GLASS_REQUEST_INVALID_STATE', 'Break-glass request can only be approved from requested status.', 422);
        }

        $record->fill([
            'status' => 'approved',
            'approval_note' => $validated['approvalNote'],
            'approved_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            'approved_at' => now(),
            'expires_at' => Carbon::parse($validated['expiresAt']),
        ]);
        $record->save();

        return response()->json([
            'success' => true,
            'data' => $this->breakGlassPayload($record),
        ]);
    }

    private function breakGlassPayload(HcmTaxGovernanceBreakGlassRequest $record): array
    {
        return [
            'requestUuid' => $record->uuid,
            'targetTenantUuid' => $record->target_company_uuid,
            'status' => $record->status,
            'requestedByUserUuid' => $record->requested_by_user_uuid,
            'approvedByUserUuid' => $record->approved_by_user_uuid,
            'expiresAt' => optional($record->expires_at)?->toIso8601String(),
            'createdAt' => optional($record->created_at)?->toIso8601String(),
            'updatedAt' => optional($record->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
}
