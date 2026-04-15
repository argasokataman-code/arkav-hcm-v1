<?php

namespace App\Services\Reconciliation;

use App\Models\ExportReconciliationEvidence;
use App\Services\Reconciliation\Exceptions\ExportReconciliationException;

class ReconciliationGateService
{
    /**
     * @param  array<string, mixed>  $expectedFilterPayload
     */
    public function assertCanProceed(
        ?int $companyId,
        string $featureKey,
        string $actionKey,
        string $scopeRef,
        array $expectedFilterPayload = [],
        ?string $currentDatasetChecksum = null,
        bool $strictChecksum = false
    ): ExportReconciliationEvidence {
        $evidence = ExportReconciliationEvidence::latestByScope($companyId, $featureKey, $actionKey, $scopeRef);

        if ($evidence === null) {
            throw new ExportReconciliationException(
                'EXPORT_RECON_REQUIRED',
                'Export reconciliation evidence is required before this action.'
            );
        }

        if ($evidence->isExpired()) {
            throw new ExportReconciliationException(
                'EXPORT_RECON_EXPIRED',
                'Export reconciliation evidence has expired. Please export latest data.'
            );
        }

        if (! $this->filtersMatch($expectedFilterPayload, $evidence->filter_payload ?? [])) {
            throw new ExportReconciliationException(
                'EXPORT_RECON_SCOPE_MISMATCH',
                'Export reconciliation scope does not match current action context.'
            );
        }

        if ($strictChecksum && ! $this->checksumMatch($currentDatasetChecksum, $evidence->dataset_checksum)) {
            throw new ExportReconciliationException(
                'EXPORT_RECON_STALE_DATA',
                'Export reconciliation evidence is stale. Please re-export before continuing.'
            );
        }

        return $evidence;
    }

    /**
     * @param  array<string, mixed>  $expectedFilterPayload
     * @param  array<string, mixed>  $actualFilterPayload
     */
    private function filtersMatch(array $expectedFilterPayload, array $actualFilterPayload): bool
    {
        foreach ($expectedFilterPayload as $key => $value) {
            if (! array_key_exists($key, $actualFilterPayload)) {
                return false;
            }

            if ($actualFilterPayload[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    private function checksumMatch(?string $currentDatasetChecksum, ?string $evidenceChecksum): bool
    {
        if (empty($currentDatasetChecksum) || empty($evidenceChecksum)) {
            return false;
        }

        return hash_equals($evidenceChecksum, $currentDatasetChecksum);
    }
}
