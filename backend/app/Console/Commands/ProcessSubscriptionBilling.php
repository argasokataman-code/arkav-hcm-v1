<?php

namespace App\Console\Commands;

use App\Jobs\SubscriptionRenewalNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionBilling extends Command
{
    protected $signature = 'billing:process-renewals';

    protected $description = 'Send renewal notifications and create renewal invoices';

    public function handle(): int
    {
        Log::info('Starting subscription billing process command');
        $this->line('Processing subscription renewals...');

        try {
            SubscriptionRenewalNotifier::dispatch();
            $this->info('✓ Renewal notifier dispatched successfully');
            Log::info('Subscription billing job dispatched');

            return 0;
        } catch (\Exception $e) {
            $this->error('''✗ Failed to dispatch renewal notifier: .$e->getMessage());
            Log::error('Failed to dispatch subscription billing job', ['error' => $e->getMessage()]);

            return 1;
        }
    }
}
