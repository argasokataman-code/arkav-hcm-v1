<?php

namespace App\Services;

use App\Models\User;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Subscription;
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
                    'event_key' => 'billing.payment_received',
                    'payment_id' => $payment->id,
                    'admin_email' => $admin->email,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to send payment received notification", [
                'event_key' => 'billing.payment_received',
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
                    'event_key' => 'billing.invoice.overdue',
                    'invoice_id' => $invoice->id,
                    'admin_email' => $admin->email,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to send overdue invoice notification", [
                'event_key' => 'billing.invoice.overdue',
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
                    'event_key' => 'billing.subscription.cancelled',
                    'subscription_id' => $subscription->id,
                    'admin_email' => $admin->email,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to send subscription cancelled notification", [
                'event_key' => 'billing.subscription.cancelled',
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
                    'event_key' => 'billing.invoice.email_sent',
                    'invoice_id' => $invoice->id,
                    'admin_email' => $admin->email,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("Failed to send invoice sent notification", [
                'event_key' => 'billing.invoice.email_failed',
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
                'event_key' => 'billing.bulk_admin_notification',
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
            $billingContact = $company->owner;

            if (!$billingContact || !$billingContact->email) {
                \Log::warning("No billing contact found for invoice notification", [
                    'invoice_id' => $invoice->id,
                    'company_id' => $company->id,
                ]);
                return;
            }

            $subject = "Invoice #{$invoice->invoice_number} - " . config('app.name');
            $amount = number_format((float) $invoice->amount_due, 2);
            $currency = $company->currency ?? 'IDR';
            $dueDate = $invoice->due_date->format('d/m/Y');
            
            $message = <<<EOT
Dear {$billingContact->name},

We've issued an invoice for {$company->name}.

Invoice Details:
- Invoice Number: {$invoice->invoice_number}
- Amount: {$amount} {$currency}
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
                'event_key' => 'billing.invoice.issued',
                'invoice_id' => $invoice->id,
                'company_id' => $company->id,
                'recipient_email' => $billingContact->email,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to send invoice issued notification", [
                'event_key' => 'billing.invoice.issued',
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
            $billingContact = $company->owner;

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
                'event_key' => 'billing.subscription.expiring_in_7_days',
                'subscription_id' => $subscription->id,
                'company_id' => $company->id,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to send subscription expiration notification", [
                'event_key' => 'billing.subscription.expiring_in_7_days',
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
            $billingContact = $company->owner;

            if (!$billingContact || !$billingContact->email) {
                \Log::warning("No billing contact for payment failure notification", [
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            $subject = "Payment Failed - Invoice #{$invoice->invoice_number}";
            $amount = number_format((float) $invoice->amount_due, 2);
            $currency = $company->currency ?? 'IDR';

            $message = <<<EOT
Dear {$billingContact->name},

Unfortunately, we were unable to process payment for your invoice.

Invoice Details:
- Invoice Number: {$invoice->invoice_number}
- Amount: {$amount} {$currency}
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
                'event_key' => 'billing.payment_failed',
                'invoice_id' => $invoice->id,
                'company_id' => $company->id,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to send payment failure notification", [
                'event_key' => 'billing.payment_failed',
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify company when grace period starts after retry exhausted.
     */
    public function notifyGracePeriodStarted(Subscription $subscription, Invoice $invoice): void
    {
        try {
            $company = $subscription->company;
            $billingContact = $company->owner;

            if (! $billingContact || ! $billingContact->email) {
                \Log::warning('No billing contact for grace period notification', [
                    'subscription_id' => $subscription->id,
                ]);
                return;
            }

            $graceEndsAt = $subscription->grace_ends_at ? $subscription->grace_ends_at->format('d/m/Y') : '-';
            $currency = $company->currency ?? 'IDR';
            $subject = "Grace Period Started - Invoice #{$invoice->invoice_number}";

            $message = <<<EOT
Dear {$billingContact->name},

We could not complete payment for your renewal invoice.

Invoice Details:
- Invoice Number: {$invoice->invoice_number}
- Amount Due: {$invoice->amount_due} {$currency}
- Grace Period Ends: {$graceEndsAt}

Please complete your payment before the grace period ends to avoid suspension.

Thank you!
EOT;

            Mail::raw($message, function ($mail) use ($subject, $billingContact) {
                $mail->to($billingContact->email)
                    ->subject($subject);
            });

            \Log::info('Grace period notification sent', [
                'event_key' => 'billing.subscription.grace_started',
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'company_id' => $subscription->company_id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send grace period notification', [
                'event_key' => 'billing.subscription.grace_started',
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify company one day before suspension.
     */
    public function notifySuspensionWarning(Subscription $subscription): void
    {
        try {
            $company = $subscription->company;
            $billingContact = $company->owner;

            if (! $billingContact || ! $billingContact->email) {
                \Log::warning('No billing contact for suspension warning', [
                    'subscription_id' => $subscription->id,
                ]);
                return;
            }

            $graceEndsAt = $subscription->grace_ends_at ? $subscription->grace_ends_at->format('d/m/Y') : '-';
            $subject = 'Suspension Warning - Action Required';

            $message = <<<EOT
Dear {$billingContact->name},

Your subscription is in grace period and will be suspended soon if payment is still not completed.

- Grace Period Ends: {$graceEndsAt}

Please complete your payment immediately to avoid service interruption.

Thank you!
EOT;

            Mail::raw($message, function ($mail) use ($subject, $billingContact) {
                $mail->to($billingContact->email)
                    ->subject($subject);
            });

            \Log::info('Suspension warning notification sent', [
                'event_key' => 'billing.subscription.suspension_warning',
                'subscription_id' => $subscription->id,
                'company_id' => $subscription->company_id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send suspension warning notification', [
                'event_key' => 'billing.subscription.suspension_warning',
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify company when subscription becomes inactive due to billing delinquency.
     */
    public function notifySubscriptionSuspended(Subscription $subscription): void
    {
        try {
            $company = $subscription->company;
            $billingContact = $company->owner;

            if (! $billingContact || ! $billingContact->email) {
                \Log::warning('No billing contact for inactive subscription notification', [
                    'subscription_id' => $subscription->id,
                ]);
                return;
            }

            $subject = 'Subscription Inactive';
            $message = <<<EOT
Dear {$billingContact->name},

Your subscription is inactive because the grace period ended without successful payment.

Please complete outstanding payment and contact support to reactivate service.

Thank you!
EOT;

            Mail::raw($message, function ($mail) use ($subject, $billingContact) {
                $mail->to($billingContact->email)
                    ->subject($subject);
            });

            \Log::warning('Subscription inactive notification sent', [
                'event_key' => 'billing.subscription.inactive',
                'subscription_id' => $subscription->id,
                'company_id' => $subscription->company_id,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send subscription inactive notification', [
                'event_key' => 'billing.subscription.inactive',
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify company when subscription has been reactivated.
     */
    public function notifySubscriptionReactivated(Subscription $subscription): void
    {
        try {
            $company = $subscription->company;
            $billingContact = $company?->owner;

            if (! $billingContact || ! $billingContact->email) {
                \Log::warning('No billing contact for subscription reactivation notification', [
                    'subscription_id' => $subscription->id,
                    'company_id' => $subscription->company_id,
                ]);
                return;
            }

            $packageName = $subscription->package?->name ?? ($subscription->plan_code ?: 'your subscription');
            $endsAt = $subscription->ends_at ? $subscription->ends_at->format('d/m/Y') : '-';
            $subject = 'Subscription Reactivated';

            $message = <<<EOT
Dear {$billingContact->name},

Good news. Your subscription has been reactivated and service access is now restored.

Subscription Details:
- Package: {$packageName}
- Current Status: {$subscription->status}
- Active Until: {$endsAt}

Thank you!
EOT;

            Mail::raw($message, function ($mail) use ($subject, $billingContact) {
                $mail->to($billingContact->email)
                    ->subject($subject);
            });

            \Log::info('Subscription reactivation notification sent', [
                'event_key' => 'billing.subscription.reactivated',
                'subscription_id' => $subscription->id,
                'company_id' => $subscription->company_id,
                'recipient_email' => $billingContact->email,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send subscription reactivation notification', [
                'event_key' => 'billing.subscription.reactivated',
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send operational alert email to all super-admins.
     * Used for: Xendit gateway down, worker crash, failure spike.
     *
     * @param string $alertType  gateway_down | worker_crash | failure_spike
     * @param string $reasonCode XENDIT_DOWN | RENEWAL_WORKER_CRASHED | RENEWAL_FAILURE_SPIKE | ...
     * @param string $message    Human-readable detail
     * @param array  $context    Extra key-value pairs
     */
    public function notifyAdminOperationalAlert(string $alertType, string $reasonCode, string $message, array $context = []): void
    {
        try {
            $admins = User::where('is_super_admin', true)->get();
            if ($admins->isEmpty()) {
                \Log::warning('No super-admin found for operational alert email', [
                    'alert_type' => $alertType,
                    'reason_code' => $reasonCode,
                ]);
                return;
            }

            $appName = config('app.name', 'HCM System');
            $subject = "[{$appName}] Renewal Alert: {$reasonCode}";
            $contextLines = '';
            foreach ($context as $key => $value) {
                $contextLines .= "- {$key}: {$value}\n";
            }
            $ts = now()->toIso8601String();
            $body = <<<EOT
[OPERATIONAL ALERT]

Alert Type   : {$alertType}
Reason Code  : {$reasonCode}
Time         : {$ts}

{$message}

Context:
{$contextLines}
Please check the renewal monitoring dashboard for details.
EOT;

            foreach ($admins as $admin) {
                if (! $admin->email) {
                    continue;
                }
                Mail::raw($body, function ($mail) use ($subject, $admin) {
                    $mail->to($admin->email)->subject($subject);
                });
            }

            \Log::info('Admin operational alert sent', [
                'event_key'   => 'billing.renewal.operational_alert',
                'alert_type'  => $alertType,
                'reason_code' => $reasonCode,
                'recipient_count' => $admins->count(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send admin operational alert', [
                'event_key'   => 'billing.renewal.operational_alert',
                'alert_type'  => $alertType,
                'reason_code' => $reasonCode,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
