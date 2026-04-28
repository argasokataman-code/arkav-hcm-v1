<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Models\PlatformRevenueTransaction;
use App\Models\Subscription;
use App\Services\QueueBackpressureGuard;
use App\Services\RevenueSourceReferenceValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CaptureSubscriptionRevenue implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        private readonly RevenueSourceReferenceValidator $referenceValidator,
        private readonly QueueBackpressureGuard $backpressureGuard,
    ) {
    }

    public function handle(SubscriptionCreated $event): void
    {
        $this->backpressureGuard->check('revenue_capture');

        $subscription = Subscription::query()->find($event->subscriptionId);
        if (! $subscription) {
            throw new RuntimeException('Subscription source entity not found for revenue capture.');
        }

        $idempotencyKey = 'subscription_created:' . $subscription->id;

        $this->referenceValidator->assertValid(
            'subscriptions',
            (int) $subscription->id,
            (string) $subscription->uuid,
            (int) $subscription->company_id
        );

        DB::transaction(function () use ($event, $subscription, $idempotencyKey): void {
            $captured = PlatformRevenueTransaction::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'company_id' => (int) $subscription->company_id,
                    'source_event_type' => 'subscription.created',
                    'source_entity_type' => 'subscriptions',
                    'source_entity_id' => (int) $subscription->id,
                    'source_entity_uuid' => (string) $subscription->uuid,
                    'transaction_type' => PlatformRevenueTransaction::TYPE_SUBSCRIPTION,
                    'amount' => (float) ($subscription->amount ?? 0),
                    'tax_amount' => 0,
                    'net_amount' => (float) ($subscription->amount ?? 0),
                    'currency' => 'IDR',
                    'status' => PlatformRevenueTransaction::STATUS_POSTED,
                    'clearing_status' => PlatformRevenueTransaction::CLEARING_UNCLEARED,
                    'occurred_at' => $subscription->created_at ?? now(),
                    'metadata' => [
                        'plan_code' => $subscription->plan_code,
                        'billing_cycle' => $subscription->billing_cycle,
                        'status' => $subscription->status,
                        'actor_user_id' => $event->actorUserId,
                    ],
                ]
            );

            if (! $captured->wasRecentlyCreated) {
                Log::info('tax_governance.revenue_capture_duplicate_skipped', [
                    'source_event_type' => 'subscription.created',
                    'idempotency_key' => $idempotencyKey,
                    'company_id' => (int) $subscription->company_id,
                    'source_entity_id' => (int) $subscription->id,
                ]);
            }
        });
    }

    public function failed(SubscriptionCreated $event, \Throwable $exception): void
    {
        Log::error('tax_governance.revenue_capture_failed', [
            'source_event_type' => 'subscription.created',
            'source_entity_id' => $event->subscriptionId,
            'actor_user_id' => $event->actorUserId,
            'error' => $exception->getMessage(),
        ]);
    }
}
