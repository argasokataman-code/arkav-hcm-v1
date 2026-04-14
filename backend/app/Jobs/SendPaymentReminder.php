<?php

namespace App\Jobs;

use App\Mail\PaymentReminderMailable;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Find invoices due soon (within 7 days) or overdue
        $invoices = Invoice::where('is_paid', false)
            ->where(function ($query) {
                $query->whereBetween('due_date', [now(), now()->addDays(7)])
                    ->orWhere('due_date', '<', now());
            })
            ->get();

        foreach ($invoices as $invoice) {
            try {
                $email = $invoice->company->email;

                if ($email) {
                    Mail::to($email)
                        ->send(new PaymentReminderMailable($invoice));

                    // Log reminder sent
                    \Log::info("Payment reminder sent for invoice {$invoice->invoice_number}");
                }
            } catch (\Exception $e) {
                \Log::error("Failed to send payment reminder for invoice {$invoice->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
