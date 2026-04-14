<?php

namespace App\Console;

use App\Support\CronjobSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $paymentReminder = CronjobSettings::get('payment_reminder');
        if (($paymentReminder['enabled'] ?? true) === true) {
            $schedule->job(\App\Jobs\SendPaymentReminder::class)
                ->dailyAt((string) ($paymentReminder['time'] ?? '08:00'))
                ->timezone((string) ($paymentReminder['timezone'] ?? 'Asia/Jakarta'));
        }

        $wilayahSync = CronjobSettings::get('wilayah_sync');
        if (($wilayahSync['enabled'] ?? true) === true) {
            $schedule->command('wilayah:sync')
                ->monthlyOn((int) ($wilayahSync['dayOfMonth'] ?? 1), (string) ($wilayahSync['time'] ?? '01:00'))
                ->timezone((string) ($wilayahSync['timezone'] ?? 'Asia/Jakarta'));
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
