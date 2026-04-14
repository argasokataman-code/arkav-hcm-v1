<?php

namespace App\Services;

use App\Models\User;
use App\Models\Payment;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Notify admin about payment received
     */
    public function notifyPaymentReceived(Payment $payment, Invoice $invoice): void
    {
        try {
            $admin = User::where('isHcmAdmin', true)->first();

            if ($admin && $admin->email) {
                $subject = "Payment Received - {$invoice->invoice_number}";
                $message = "Payment of " . number_format($payment->amount, 2) . 
                          " received for invoice {$invoice->invoice_number} from {$invoice->company->name}";

                Mail::raw($message, function ($mail) use ($subject, $admin) {
                    $mail->to($admin->email)
                        ->subject($subject);
                });

                \Log::info("Payment received notification sent", [
                    'payment_id' => $payment->id,
                    'admin_email' => $admin->email,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to send payment received notification", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify admin about overdue invoice
     */
    public function notifyOverdueInvoice(Invoice $invoice): void
    {
        try {
            $admin = User::where('isHcmAdmin', true)->first();

            if ($admin && $admin->email) {
                $subject = "Overdue Invoice Alert - {$invoice->invoice_number}";
                $daysOverdue = abs($invoice->due_date->diffInDays(now()));
                $message = "Invoice {$invoice->invoice_number} from {$invoice->company->name} is now {$daysOverdue} days overdue. Amount due: " . 
                          number_format($invoice->amount_due, 2);

                Mail::raw($message, function ($mail) use ($subject, $admin) {
                    $mail->to($admin->email)
                        ->subject($subject);
                });

                \Log::info("Overdue invoice notification sent", [
                    'invoice_id' => $invoice->id,
                    'admin_email' => $admin->email,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to send overdue invoice notification", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify admin about subscription cancelled
     */
    public function notifySubscriptionCancelled($subscription): void
    {
        try {
            $admin = User::where('isHcmAdmin', true)->first();

            if ($admin && $admin->email) {
                $subject = "Subscription Cancelled - {$subscription->code}";
                $message = "Subscription {$subscription->code} from {$subscription->company->name} has been cancelled. " .
                          "Monthly value: " . number_format($subscription->amount, 2);

                Mail::raw($message, function ($mail) use ($subject, $admin) {
                    $mail->to($admin->email)
                        ->subject($subject);
                });

                \Log::info("Subscription cancelled notification sent", [
                    'subscription_id' => $subscription->id,
                    'admin_email' => $admin->email,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to send subscription cancelled notification", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify admin about invoice sent
     */
    public function notifyInvoiceSent(Invoice $invoice): void
    {
        try {
            $admin = User::where('isHcmAdmin', true)->first();

            if ($admin && $admin->email) {
                $subject = "Invoice Sent - {$invoice->invoice_number}";
                $message = "Invoice {$invoice->invoice_number} has been sent to {$invoice->company->name}. " .
                          "Amount due: " . number_format($invoice->amount_due, 2);

                Mail::raw($message, function ($mail) use ($subject, $admin) {
                    $mail->to($admin->email)
                        ->subject($subject);
                });

                \Log::info("Invoice sent notification sent", [
                    'invoice_id' => $invoice->id,
                    'admin_email' => $admin->email,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to send invoice sent notification", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send bulk notification to admins
     */
    public function notifyAdmins(string $subject, string $message): void
    {
        try {
            $admins = User::where('isHcmAdmin', true)->get();

            foreach ($admins as $admin) {
                if ($admin->email) {
                    Mail::raw($message, function ($mail) use ($subject, $admin) {
                        $mail->to($admin->email)
                            ->subject($subject);
                    });
                }
            }

            \Log::info("Bulk notification sent to " . $admins->count() . " admins");
        } catch (\Exception $e) {
            \Log::error("Failed to send bulk notifications", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
