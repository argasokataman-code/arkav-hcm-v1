<?php

namespace App\Services;

use App\Models\HcmPayrollRun;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

class RevenueSourceReferenceValidator
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const SOURCE_MODEL_MAP = [
        'subscriptions' => Subscription::class,
        'hcm_payroll_runs' => HcmPayrollRun::class,
        'purchase_transactions' => PurchaseTransaction::class,
    ];

    /**
     * Validate canonical source references before persisting revenue capture rows.
     *
     * @throws RuntimeException
     */
    public function assertValid(
        string $sourceEntityType,
        ?int $sourceEntityId,
        ?string $sourceEntityUuid,
        int $companyId
    ): void {
        if (! isset(self::SOURCE_MODEL_MAP[$sourceEntityType])) {
            throw new RuntimeException("Unsupported source_entity_type [{$sourceEntityType}].");
        }

        if (! $sourceEntityId || ! $sourceEntityUuid) {
            throw new RuntimeException('source_entity_id/source_entity_uuid is required for revenue source validation.');
        }

        if (! Str::isUuid($sourceEntityUuid)) {
            throw new RuntimeException('source_entity_uuid must be a valid UUID string.');
        }

        $modelClass = self::SOURCE_MODEL_MAP[$sourceEntityType];
        $record = $modelClass::query()
            ->whereKey($sourceEntityId)
            ->where('uuid', $sourceEntityUuid)
            ->where('company_id', $companyId)
            ->first();

        if (! $record) {
            throw new RuntimeException(sprintf(
                'Revenue source reference mismatch for [%s] id=%d uuid=%s company_id=%d.',
                $sourceEntityType,
                $sourceEntityId,
                $sourceEntityUuid,
                $companyId
            ));
        }
    }
}