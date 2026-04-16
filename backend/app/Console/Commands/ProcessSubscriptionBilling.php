<?php

namespace App\Console\Commands;

use App\Jobs\ProcessRecurringSubscriptionBilling;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionBilling extends Command
{
    protected $signature = 'billing:process-renewals';
    protected $description = 'Process subscription renewals and attempt payment collection';

    public function handle(): int
    {
        Log::info('Starting subscription billing process command');
        $this->line('Processing subscription renewals...');

        try {
            ProcessRecurringSubscriptionBilling::dispatch();
            $this->info('✓ Subscription billing job dispatched successfully');
            Log::info('Subscription billing job dispatched');
            return 0;
        } catch (\Exception $e) {
            $this->error('✗ Failed to dispatch subscription billing job: ' . $e->getMessage());
            Log::error('Failed to dispatch subscription billing job', ['error' => $e->getMessage()]);
            return 1;
        }
    }
}
