<?php

namespace App\Listeners;

use App\Events\PayrollFinalized;
use App\Models\HcmPayrollRun;
use App\Models\PlatformRevenueTransaction;
use App\Services\QueueBackpressureGuard;
use App\Services\RevenueSourceReferenceValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CapturePayrollServiceRevenue implements ShouldQueue
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

    public function handle(PayrollFinalized $event): void
    {
        $this->backpressureGuard->check('revenue_capture');

        $run = HcmPayrollRun::query()->with('lines')->find($event->payrollRunId);
        if (! $run) {
            throw new RuntimeException('Payroll run source entity not found for revenue capture.');
        }

        $idempotencyKey = 'payroll_finalized:' . $run->id;
        $meta = is_array($run->meta) ? $run->meta : [];

        $this->referenceValidator->assertValid(
            'hcm_payroll_runs',
            (int) $run->id,
            (string) $run->uuid,
            (int) $run->company_id
        );

        $grossPayrollBase = (float) $run->lines
            ->where('kind', 'earning')
            ->sum('amount');

        $serviceFeeAmount = (float) ($meta['platform_service_fee_amount'] ?? 0);

        DB::transaction(function () use ($event, $run, $idempotencyKey, $grossPayrollBase, $serviceFeeAmount): void {
            $captured = PlatformRevenueTransaction::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'company_id' => (int) $run->company_id,
                    'source_event_type' => 'payroll.finalized',
                    'source_entity_type' => 'hcm_payroll_runs',
                    'source_entity_id' => (int) $run->id,
                    'source_entity_uuid' => (string) $run->uuid,
                    'transaction_type' => PlatformRevenueTransaction::TYPE_PAYROLL_SERVICE,
                    'amount' => $serviceFeeAmount,
                    'tax_amount' => 0,
                    'net_amount' => $serviceFeeAmount,
                    'currency' => 'IDR',
                    'status' => PlatformRevenueTransaction::STATUS_POSTED,
                    'clearing_status' => PlatformRevenueTransaction::CLEARING_UNCLEARED,
                    'occurred_at' => $run->finalized_at ?? now(),
                    'metadata' => [
                        'gross_payroll_base' => $grossPayrollBase,
                        'line_count' => $run->lines->count(),
                        'purpose' => $run->purpose,
                        'actor_user_id' => $event->actorUserId,
                    ],
                ]
            );

            if (! $captured->wasRecentlyCreated) {
                Log::info('tax_governance.revenue_capture_duplicate_skipped', [
                    'source_event_type' => 'payroll.finalized',
                    'idempotency_key' => $idempotencyKey,
                    'company_id' => (int) $run->company_id,
                    'source_entity_id' => (int) $run->id,
                ]);
            }
        });
    }

    public function failed(PayrollFinalized $event, \Throwable $exception): void
    {
        Log::error('tax_governance.revenue_capture_failed', [
            'source_event_type' => 'payroll.finalized',
            'source_entity_id' => $event->payrollRunId,
            'actor_user_id' => $event->actorUserId,
            'error' => $exception->getMessage(),
        ]);
    }
}
