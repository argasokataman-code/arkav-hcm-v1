<?php

namespace App\Services\Reconciliation;

use App\Models\ExportReconciliationEvidence;
use Carbon\CarbonInterface;

class ReconciliationExportService
{
    public const FORMAT_CSV = 'csv';

    public const FORMAT_XLSX = 'xlsx';

    /**
     * Persist reconciliation export evidence for a specific feature/action/scope.
     */
    public function createEvidence(
        ?int $companyId,
        string $featureKey,
        string $actionKey,
        string $scopeRef,
        int $exportedByUserId,
        string $fileFormat,
        string $filePath,
        int $rowCount,
        array $filterPayload = [],
        ?string $datasetChecksum = null,
        ?CarbonInterface $expiresAt = null
    ): ExportReconciliationEvidence {
        return ExportReconciliationEvidence::query()->create([
            'company_id' => $companyId,
            'feature_key' => $featureKey,
            'action_key' => $actionKey,
            'scope_ref' => $scopeRef,
            'exported_by_user_id' => $exportedByUserId,
            'exported_at' => now(),
            'file_format' => strtolower($fileFormat),
            'file_path' => $filePath,
            'row_count' => max(0, $rowCount),
            'filter_payload' => $filterPayload,
            'dataset_checksum' => $datasetChecksum,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Build deterministic checksum from export payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function checksumForPayload(array $payload): string
    {
        $normalized = $this->normalizePayload($payload);
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $encoded === false ? '{}' : $encoded);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        ksort($payload);
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->normalizeNestedArray($value);
            }
        }

        return $payload;
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    private function normalizeNestedArray(array $value): array
    {
        if ($this->isAssoc($value)) {
            ksort($value);
        }

        foreach ($value as $nestedKey => $nestedValue) {
            if (is_array($nestedValue)) {
                $value[$nestedKey] = $this->normalizeNestedArray($nestedValue);
            }
        }

        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
