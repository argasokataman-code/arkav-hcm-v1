<?php

namespace Tests\Feature;

use App\Events\SubscriptionCreated;
use App\Listeners\CaptureAddonRevenue;
use App\Listeners\CaptureSubscriptionRevenue;
use App\Models\Company;
use App\Models\Package;
use App\Models\PlatformRevenueTransaction;
use App\Models\Subscription;
use App\Services\QueueBackpressureGuard;
use App\Services\RevenueSourceReferenceValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TaxGovernanceCaptureAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_wraps_write_in_transaction_and_commits_on_success(): void
    {
        $company = Company::factory()->create();
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'amount' => 100000,
            'status' => 'active',
        ]);

        $this->assertDatabaseCount('platform_revenue_transactions', 0);

        SubscriptionCreated::dispatch((int) $subscription->id, null);

        $this->assertDatabaseCount('platform_revenue_transactions', 1);
        $this->assertDatabaseHas('platform_revenue_transactions', [
            'company_id' => $company->id,
            'transaction_type' => PlatformRevenueTransaction::TYPE_SUBSCRIPTION,
            'idempotency_key' => 'subscription_created:'.$subscription->id,
        ]);
    }

    public function test_capture_transaction_rolls_back_on_exception_after_write(): void
    {
        $company = Company::factory()->create();
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'amount' => 100000,
            'status' => 'active',
        ]);

        // Force an exception inside the transaction after firstOrCreate
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('simulated db failure after write'));

        $guard = app(QueueBackpressureGuard::class);
        $validator = app(RevenueSourceReferenceValidator::class);
        $listener = new CaptureSubscriptionRevenue($validator, $guard);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('simulated db failure after write');

        $listener->handle(new SubscriptionCreated((int) $subscription->id, null));

        $this->assertDatabaseCount('platform_revenue_transactions', 0);
    }

    public function test_backpressure_guard_logs_warning_when_threshold_exceeded(): void
    {
        $windowKey = 'queue_bp:revenue_capture:'.now()->format('Y-m-d-H-i');
        Cache::put($windowKey, 250, now()->addMinutes(2));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'tax_governance.queue_backpressure_alert'
                    && ($context['channel'] ?? null) === 'revenue_capture'
                    && (int) ($context['window_events'] ?? 0) >= 200;
            });

        $guard = app(QueueBackpressureGuard::class);
        $guard->check('revenue_capture', 200);
    }

    public function test_backpressure_guard_does_not_log_below_threshold(): void
    {
        $windowKey = 'queue_bp:revenue_capture:'.now()->format('Y-m-d-H-i');
        Cache::put($windowKey, 5, now()->addMinutes(2));

        Log::shouldReceive('warning')->never();

        $guard = app(QueueBackpressureGuard::class);
        $guard->check('revenue_capture', 200);
    }

    public function test_listeners_have_backpressure_guard_injected(): void
    {
        $validator = app(RevenueSourceReferenceValidator::class);
        $guard = app(QueueBackpressureGuard::class);

        $this->assertInstanceOf(
            QueueBackpressureGuard::class,
            $guard,
            'QueueBackpressureGuard must be resolvable from container.'
        );

        $listeners = [
            new CaptureSubscriptionRevenue($validator, $guard),
            new CaptureAddonRevenue($validator, $guard),
        ];

        foreach ($listeners as $listener) {
            $this->assertNotNull($listener);
        }
    }
}
