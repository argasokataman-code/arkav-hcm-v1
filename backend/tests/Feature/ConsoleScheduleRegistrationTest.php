<?php

namespace Tests\Feature;

use App\Jobs\CheckEmployeeCountLimitsJob;
use App\Jobs\ConvertExpiredTrialsToPendingPaymentJob;
use App\Jobs\SuspendServicesForOverdueInvoicesJob;
use App\Jobs\TerminateExpiredSubscriptionsJob;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

/**
 * M1 — memastikan semua event scheduler HCM/SaaS terdaftar dengan nama & cron
 * expression yang benar. Mencegah regresi akibat edit `routes/console.php` tanpa
 * rilis koordinasi dengan ops (misal nama event hilang, waktu tergeser, dll).
 */
#[IgnoreDeprecations]
class ConsoleScheduleRegistrationTest extends TestCase
{
    /**
     * Kanonikal schedule: (expression, timezone, description). `description`
     * adalah string yang terlihat di `schedule:list` (di-set oleh `->description()`
     * atau fallback ke `->name()` bila description belum di-set).
     *
     * @return array<int, array{expression:string,timezone:string,description:string}>
     */
    private function expectedSchedules(): array
    {
        return [
            ['expression' => '0 8 * * *',   'timezone' => 'Asia/Jakarta', 'description' => 'Dispatch SendPaymentReminder job.'],
            ['expression' => '0 1 1 * *',   'timezone' => 'Asia/Jakarta', 'description' => 'Sync wilayah.id master data to local DB.'],
            ['expression' => '0 0 * * *',   'timezone' => 'Asia/Jakarta', 'description' => 'Refresh monthly payroll draft at 00:00 WIB for the active open period.'],
            ['expression' => '10 0 * * *',  'timezone' => 'Asia/Jakarta', 'description' => 'Post monthly earned-leave accrual on end of month.'],
            ['expression' => '15 0 * * *',  'timezone' => 'Asia/Jakarta', 'description' => 'Run yearly carry-forward on Jan 1.'],
            ['expression' => '20 0 * * *',  'timezone' => 'Asia/Jakarta', 'description' => 'Expire carry-forward balances after policy cutoff.'],
            ['expression' => '20 0 * * *',  'timezone' => 'Asia/Jakarta', 'description' => 'Convert ended trials into pending_payment and generate invoices'],
            ['expression' => '30 0 * * *',  'timezone' => 'Asia/Jakarta', 'description' => 'Auto-terminate subscriptions with expired end_date'],
            ['expression' => '0 6 * * *',   'timezone' => 'Asia/Jakarta', 'description' => 'Auto-suspend services with overdue unpaid invoices'],
            ['expression' => '0 1 * * *',   'timezone' => 'Asia/Jakarta', 'description' => 'Monitor and enforce employee count limits against subscription plans'],
            ['expression' => '*/30 * * * *', 'timezone' => 'Asia/Jakarta', 'description' => 'Process subscription renewals and recurring billing tasks'],
            ['expression' => '*/30 * * * *', 'timezone' => 'Asia/Jakarta', 'description' => 'Reconcile pending renewal payments against gateway status and surface anomalies'],
        ];
    }

    public function test_all_expected_scheduled_events_are_registered(): void
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);
        /** @var Event[] $events */
        $events = $schedule->events();

        $registered = [];
        foreach ($events as $event) {
            $registered[] = [
                'expression' => (string) $event->expression,
                'timezone' => (string) $event->timezone,
                'description' => (string) ($event->description ?? ''),
            ];
        }

        foreach ($this->expectedSchedules() as $expected) {
            $found = false;
            foreach ($registered as $reg) {
                if (
                    $reg['expression'] === $expected['expression']
                    && $reg['timezone'] === $expected['timezone']
                    && $reg['description'] === $expected['description']
                ) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue(
                $found,
                sprintf(
                    'Scheduled event not registered: [%s] %s @ %s',
                    $expected['expression'],
                    $expected['description'],
                    $expected['timezone']
                )
            );
        }

        $this->assertGreaterThanOrEqual(
            count($this->expectedSchedules()),
            count($events),
            'Unexpected reduction in scheduled events count.'
        );
    }

    public function test_saas_jobs_exist_as_resolvable_job_classes(): void
    {
        // Sanity: kelas job yang dispatch dari scheduler masih dapat di-instantiate
        // (mencegah nama class drift tanpa update routes/console.php).
        $this->assertTrue(class_exists(ConvertExpiredTrialsToPendingPaymentJob::class));
        $this->assertTrue(class_exists(TerminateExpiredSubscriptionsJob::class));
        $this->assertTrue(class_exists(SuspendServicesForOverdueInvoicesJob::class));
        $this->assertTrue(class_exists(CheckEmployeeCountLimitsJob::class));
    }
}
