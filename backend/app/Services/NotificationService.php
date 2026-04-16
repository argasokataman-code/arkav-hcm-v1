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

    /**
     * Notify company about invoice issued
     */
    public function notifyInvoiceIssued(Invoice $invoice): void
    {
        try {
            $company = $invoice->company;
            $billingContact = $company->billingContact ?? $company->primaryContact;

            if (!$billingContact || !$billingContact->email) {
                \Log::warning("No billing contact found for invoice notification", [
                    'invoice_id' => $invoice->id,
                    'company_id' => $company->id,
                ]);
                return;
            }

            $subject = "Invoice #{$invoice->invoice_number} - {config('app.name')}";
            $amount = number_format($invoice->amount, 2);
            $dueDate = $invoice->due_date->format('d/m/Y');
            
            $message = <<<EOT
Dear {$billingContact->name},

We've issued an invoice for {$company->name}.

Invoice Details:
- Invoice Number: {$invoice->invoice_number}
- Amount: {$amount} {$invoice->currency}
- Due Date: {$dueDate}
- Description: {$invoice->description}

Please complete payment by the due date to avoid service interruption.

Thank you!
EOT;

            Mail::raw($message, function ($mail) use ($subject, $billingContact) {
                $mail->to($billingContact->email)
                    ->subject($subject);
            });

            \Log::info("Invoice issued notification sent", [
                'invoice_id' => $invoice->id,
                'company_id' => $company->id,
                'recipient_email' => $billingContact->email,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to send invoice issued notification", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify company about subscription expiring in 7 days
     */
    public function notifySubscriptionExpiringIn7Days($subscription): void
    {
        try {
            $company = $subscription->company;
            $billingContact = $company->billingContact ?? $company->primaryContact;

            if (!$billingContact || !$billingContact->email) {
                \Log::warning("No billing contact for expiration notification", [
                    'subscription_id' => $subscription->id,
                ]);
                return;
            }

            $expiryDate = $subscription->ends_at->format('d/m/Y');
            $packageName = $subscription->package->name ?? 'Your subscription';

            $subject = "Subscription Renewal Required - {$packageName}";
            $message = <<<EOT
Dear {$billingContact->name},

Your subscription to {$packageName} will expire in 7 days (on {$expiryDate}).

To avoid any service interruption, please ensure your payment method is up to date. Your subscription will automatically renew if auto-renewal is enabled.

If you have any questions or wish to upgrade/downgrade your plan, please contact our sales team.

Thank you!
EOT;

            Mail::raw($message, function ($mail) use ($subject, $billingContact) {
                $mail->to($billingContact->email)
                    ->subject($subject);
            });

            \Log::info("Subscription expiration reminder sent", [
                'subscription_id' => $subscription->id,
                'company_id' => $company->id,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to send subscription expiration notification", [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify company about payment failure
     */
    public function notifyPaymentFailed(Invoice $invoice): void
    {
        try {
            $company = $invoice->company;
            $billingContact = $company->billingContact ?? $company->primaryContact;

            if (!$billingContact || !$billingContact->email) {
                \Log::warning("No billing contact for payment failure notification", [
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            $subject = "Payment Failed - Invoice #{$invoice->invoice_number}";
            $amount = number_format($invoice->amount, 2);
            
            $message = <<<EOT
Dear {$billingContact->name},

Unfortunately, we were unable to process payment for your invoice.

Invoice Details:
- Invoice Number: {$invoice->invoice_number}
- Amount: {$amount} {$invoice->currency}
- Status: Payment Failed

Please update your payment method and try again. If this issue persists, please contact our support team.

Thank you!
EOT;

            Mail::raw($message, function ($mail) use ($subject, $billingContact) {
                $mail->to($billingContact->email)
                    ->subject($subject);
            });

            // Also notify admins
            $admin = User::where('isHcmAdmin', true)->first();
            if ($admin && $admin->email) {
                Mail::raw(
                    "Payment failed for invoice {$invoice->invoice_number} from {$company->name}. Amount: {$amount}",
                    function ($mail) use ($admin) {
                        $mail->to($admin->email)->subject("Alert: Payment Failed");
                    }
                );
            }

            \Log::warning("Payment failure notification sent", [
                'invoice_id' => $invoice->id,
                'company_id' => $company->id,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to send payment failure notification", [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
