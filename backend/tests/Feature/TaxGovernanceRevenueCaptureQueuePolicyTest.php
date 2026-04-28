<?php

namespace Tests\Feature;

use App\Events\AddonPurchased;
use App\Events\SubscriptionCreated;
use App\Listeners\CaptureAddonRevenue;
use App\Listeners\CapturePayrollServiceRevenue;
use App\Listeners\CaptureSubscriptionRevenue;
use App\Services\QueueBackpressureGuard;
use App\Services\RevenueSourceReferenceValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class TaxGovernanceRevenueCaptureQueuePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_listeners_are_queued_with_retry_policy(): void
    {
        $validator = app(RevenueSourceReferenceValidator::class);

        $guard = app(QueueBackpressureGuard::class);
        $listeners = [
            new CaptureSubscriptionRevenue($validator, $guard),
            new CapturePayrollServiceRevenue($validator, $guard),
            new CaptureAddonRevenue($validator, $guard),
        ];

        foreach ($listeners as $listener) {
            $this->assertInstanceOf(ShouldQueue::class, $listener);
            $this->assertSame(3, $listener->tries);
            $this->assertSame([30, 120, 300], $listener->backoff);
        }
    }

    public function test_subscription_listener_throws_when_source_entity_is_missing_for_retry(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Subscription source entity not found');

        $listener = new CaptureSubscriptionRevenue(app(RevenueSourceReferenceValidator::class), app(QueueBackpressureGuard::class));
        $listener->handle(new SubscriptionCreated(99999999, 1));
    }

    public function test_addon_listener_throws_when_source_entity_is_not_addon_type_for_retry(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Purchase transaction source entity not found');

        $listener = new CaptureAddonRevenue(app(RevenueSourceReferenceValidator::class), app(QueueBackpressureGuard::class));
        $listener->handle(new AddonPurchased(99999999, 1));
    }

    public function test_failed_listener_logs_capture_failure_observability(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'tax_governance.revenue_capture_failed'
                    && ($context['source_event_type'] ?? null) === 'subscription.created'
                    && (int) ($context['source_entity_id'] ?? 0) === 12345
                    && (int) ($context['actor_user_id'] ?? 0) === 10
                    && str_contains((string) ($context['error'] ?? ''), 'simulated failure');
            });

        $listener = new CaptureSubscriptionRevenue(app(RevenueSourceReferenceValidator::class), app(QueueBackpressureGuard::class));
        $listener->failed(new SubscriptionCreated(12345, 10), new RuntimeException('simulated failure'));

        $this->assertTrue(true);
    }
}
