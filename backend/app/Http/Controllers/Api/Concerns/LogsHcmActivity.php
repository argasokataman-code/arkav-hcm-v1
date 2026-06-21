<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\HcmActivityLog;
use Illuminate\Http\Request;

trait LogsHcmActivity
{
    /**
     * Write one audit row to hcm_activity_logs.
     *
     * @param  string  $entityType  e.g. 'employee', 'payroll_run', 'leave_request'
     * @param  string  $entityUuid  UUID of the entity that was acted on
     * @param  string  $action  e.g. 'created', 'updated', 'deleted', 'exported', 'approved', 'rejected'
     * @param  array  $changedFields  Names of fields that changed (not values)
     * @param  array  $meta  Any extra context (format, period, etc.)
     */
    protected function logHcmActivity(
        Request $request,
        string $entityType,
        string $entityUuid,
        string $action,
        array $changedFields = [],
        array $meta = [],
    ): void {
        $actor = $request->user();
        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);

        if ($companyId <= 0) {
            return; // No tenant context — skip logging
        }

        HcmActivityLog::query()->create([
            'company_id' => $companyId,
            'entity_type' => $entityType,
            'entity_uuid' => $entityUuid,
            'action' => $action,
            'performed_by_uuid' => $actor?->uuid ?? null,
            'performed_by_email' => $actor?->email ?? null,
            'ip_address' => $request->ip(),
            'changed_fields' => $changedFields ?: null,
            'meta' => $meta ?: null,
            'occurred_at' => now(),
        ]);
    }
}
