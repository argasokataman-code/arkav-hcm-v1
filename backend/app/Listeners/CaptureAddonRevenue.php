<?php

namespace App\Listeners;

use App\Events\AddonPurchased;
use App\Models\PlatformRevenueTransaction;
use App\Models\PurchaseTransaction;
use App\Services\QueueBackpressureGuard;
use App\Services\RevenueSourceReferenceValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CaptureAddonRevenue implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        private readonly RevenueSourceReferenceValidator $referenceValidator,
        private readonly QueueBackpressureGuard $backpressureGuard,
    ) {}

    public function handle(AddonPurchased $event): void
    {
        $this->backpressureGuard->check('revenue_capture');

        $transaction = PurchaseTransaction::query()->find($event->purchaseTransactionId);
        if (! $transaction) {
            throw new RuntimeException('Purchase transaction source entity not found for revenue capture.');
        }

        if ($transaction->transaction_type !== 'addon') {
            throw new RuntimeException('Purchase transaction source entity is not addon type.');
        }

        $idempotencyKey = 'addon_purchased:'.$transaction->id;
        $status = strtolower((string) ($transaction->status ?? 'draft'));

        $this->referenceValidator->assertValid(
            'purchase_transactions',
            (int) $transaction->id,
            (string) $transaction->uuid,
            (int) $transaction->company_id
        );

        $runtimeStatus = match ($status) {
            'paid' => PlatformRevenueTransaction::STATUS_POSTED,
            'cancelled' => PlatformRevenueTransaction::STATUS_CANCELLED,
            default => PlatformRevenueTransaction::STATUS_PENDING,
        };

        $amount = (float) ($transaction->total_amount ?? 0);
        $taxAmount = (float) ($transaction->tax_amount ?? 0);
        $netAmount = round($amount - $taxAmount, 2);

        DB::transaction(function () use ($event, $transaction, $idempotencyKey, $runtimeStatus, $amount, $taxAmount, $netAmount): void {
            $captured = PlatformRevenueTransaction::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'company_id' => (int) $transaction->company_id,
                    'source_event_type' => 'addon.purchased',
                    'source_entity_type' => 'purchase_transactions',
                    'source_entity_id' => (int) $transaction->id,
                    'source_entity_uuid' => (string) $transaction->uuid,
                    'transaction_type' => PlatformRevenueTransaction::TYPE_ADDON_FEATURE,
                    'amount' => $amount,
                    'tax_amount' => $taxAmount,
                    'net_amount' => $netAmount,
                    'currency' => 'IDR',
                    'status' => $runtimeStatus,
                    'clearing_status' => PlatformRevenueTransaction::CLEARING_UNCLEARED,
                    'occurred_at' => $transaction->created_at ?? now(),
                    'metadata' => [
                        'package_addon_id' => $transaction->package_addon_id,
                        'purchase_status' => $transaction->status,
                        'actor_user_id' => $event->actorUserId,
                    ],
                ]
            );

            if (! $captured->wasRecentlyCreated) {
                Log::info('tax_governance.revenue_capture_duplicate_skipped', [
                    'source_event_type' => 'addon.purchased',
                    'idempotency_key' => $idempotencyKey,
                    'company_id' => (int) $transaction->company_id,
                    'source_entity_id' => (int) $transaction->id,
                ]);
            }
        });
    }

    public function failed(AddonPurchased $event, \Throwable $exception): void
    {
        Log::error('tax_governance.revenue_capture_failed', [
            'source_event_type' => 'addon.purchased',
            'source_entity_id' => $event->purchaseTransactionId,
            'actor_user_id' => $event->actorUserId,
            'error' => $exception->getMessage(),
        ]);
    }
}
