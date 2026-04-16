<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Stripe\Customer;
use Stripe\Stripe;
use Stripe\Invoice;
use Stripe\SubscriptionSchedule;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret_key'));
    }

    /**
     * Create or get a customer in Stripe
     * 
     * @param array $params Customer parameters
     * @return string Stripe customer ID
     */
    public function getOrCreateCustomer(array $params): string
    {
        $externalId = $params['external_id'] ?? null;
        
        // Search for existing customer with this email
        if (!empty($params['email'])) {
            $customers = Customer::all([
                'email' => $params['email'],
                'limit' => 1,
            ]);

            if (!empty($customers->data)) {
                Log::info('Found existing Stripe customer', [
                    'email' => $params['email'],
                    'customer_id' => $customers->data[0]->id,
                ]);
                return $customers->data[0]->id;
            }
        }

        // Create new customer
        $customer = Customer::create([
            'email' => $params['email'],
            'name' => $params['name'] ?? 'Customer',
            'description' => $params['description'] ?? null,
            'metadata' => [
                'external_id' => $externalId,
                'created_at' => now()->toIso8601String(),
            ],
        ]);

        Log::info('Stripe customer created', [
            'email' => $params['email'],
            'customer_id' => $customer->id,
        ]);

        return $customer->id;
    }

    /**
     * Create a payment intent (one-time payment)
     * 
     * @param array $params Payment parameters
     * @return array Payment intent data
     */
    public function createPaymentIntent(array $params): array
    {
        $customerId = $params['customer_id'];
        $amount = (int) ($params['amount'] * 100); // Convert to cents
        
        $intent = \Stripe\PaymentIntent::create([
            'customer' => $customerId,
            'amount' => $amount,
            'currency' => strtolower($params['currency'] ?? 'usd'),
            'description' => $params['description'] ?? 'Payment',
            'metadata' => [
                'invoice_id' => $params['invoice_id'] ?? null,
                'company_id' => $params['company_id'] ?? null,
            ],
            'statement_descriptor' => substr($params['statement_descriptor'] ?? 'Payment', 0, 22),
        ]);

        Log::info('Stripe payment intent created', [
            'customer_id' => $customerId,
            'intent_id' => $intent->id,
            'amount' => $params['amount'],
        ]);

        return [
            'id' => $intent->id,
            'client_secret' => $intent->client_secret,
            'amount' => $intent->amount / 100,
            'status' => $intent->status,
        ];
    }

    /**
     * Create an invoice in Stripe
     * 
     * @param array $params Invoice parameters
     * @return array Invoice data
     */
    public function createInvoice(array $params): array
    {
        $customerId = $params['customer_id'];
        
        $invoice = Invoice::create([
            'customer' => $customerId,
            'currency' => strtolower($params['currency'] ?? 'usd'),
            'description' => $params['description'] ?? null,
            'metadata' => [
                'external_id' => $params['external_id'] ?? null,
                'company_id' => $params['company_id'] ?? null,
            ],
        ]);

        // Add line items if provided
        if (!empty($params['items'])) {
            foreach ($params['items'] as $item) {
                \Stripe\InvoiceLineItem::create([
                    'invoice' => $invoice->id,
                    'amount' => (int) ($item['amount'] * 100),
                    'currency' => strtolower($params['currency'] ?? 'usd'),
                    'description' => $item['description'] ?? 'Item',
                ]);
            }
        }

        // Finalize and send if requested
        if ($params['auto_send'] ?? false) {
            $invoice->finalize();
            $invoice->send();
        }

        Log::info('Stripe invoice created', [
            'customer_id' => $customerId,
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total / 100,
        ]);

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'amount' => $invoice->total / 100,
            'status' => $invoice->status,
            'url' => $invoice->hosted_invoice_url,
        ];
    }

    /**
     * Create a subscription
     * 
     * @param array $params Subscription parameters
     * @return array Subscription data
     */
    public function createSubscription(array $params): array
    {
        $customerId = $params['customer_id'];
        $priceId = $params['price_id'];
        
        $subscription = \Stripe\Subscription::create([
            'customer' => $customerId,
            'items' => [
                [
                    'price' => $priceId,
                    'quantity' => $params['quantity'] ?? 1,
                ],
            ],
            'description' => $params['description'] ?? null,
            'billing_cycle_anchor' => $params['billing_cycle_anchor'] ?? null,
            'off_session' => true,
            'metadata' => [
                'external_id' => $params['external_id'] ?? null,
                'company_id' => $params['company_id'] ?? null,
            ],
        ]);

        Log::info('Stripe subscription created', [
            'customer_id' => $customerId,
            'subscription_id' => $subscription->id,
            'price_id' => $priceId,
        ]);

        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'current_period_start' => $subscription->current_period_start,
            'current_period_end' => $subscription->current_period_end,
        ];
    }

    /**
     * Create a subscription schedule for recurring billing
     * 
     * @param array $params Schedule parameters
     * @return array Schedule data
     */
    public function createSubscriptionSchedule(array $params): array
    {
        $customerId = $params['customer_id'];
        $phases = $params['phases']; // Array of billing phases
        
        $schedule = SubscriptionSchedule::create([
            'customer' => $customerId,
            'phases' => $phases,
            'start_date' => $params['start_date'] ?? 'now',
            'metadata' => [
                'external_id' => $params['external_id'] ?? null,
                'company_id' => $params['company_id'] ?? null,
            ],
        ]);

        Log::info('Stripe subscription schedule created', [
            'customer_id' => $customerId,
            'schedule_id' => $schedule->id,
        ]);

        return [
            'id' => $schedule->id,
            'status' => $schedule->status,
        ];
    }

    /**
     * Cancel a subscription
     * 
     * @param string $subscriptionId Stripe subscription ID
     * @param bool $atPeriodEnd Cancel at end of current period if true
     * @return bool Success status
     */
    public function cancelSubscription(string $subscriptionId, bool $atPeriodEnd = false): bool
    {
        try {
            $sub = \Stripe\Subscription::retrieve($subscriptionId);
            
            if ($atPeriodEnd) {
                $sub->cancel_at_period_end = true;
                $sub->save();
            } else {
                $sub->cancel();
            }

            Log::info('Stripe subscription canceled', [
                'subscription_id' => $subscriptionId,
                'at_period_end' => $atPeriodEnd,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Stripe subscription cancellation failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Retrieve subscription details
     * 
     * @param string $subscriptionId Stripe subscription ID
     * @return array|null Subscription data
     */
    public function getSubscription(string $subscriptionId): ?array
    {
        try {
            $sub = \Stripe\Subscription::retrieve($subscriptionId);
            return [
                'id' => $sub->id,
                'status' => $sub->status,
                'current_period_start' => $sub->current_period_start,
                'current_period_end' => $sub->current_period_end,
                'cancel_at' => $sub->cancel_at,
                'canceled_at' => $sub->canceled_at,
            ];
        } catch (\Exception $e) {
            Log::error('Stripe subscription retrieval failed', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Refund a charge
     * 
     * @param string $chargeId Stripe charge ID
     * @param int|null $amount Optional refund amount in cents (partial refund)
     * @return bool Success status
     */
    public function refundCharge(string $chargeId, ?int $amount = null): bool
    {
        try {
            $refund = \Stripe\Refund::create([
                'charge' => $chargeId,
                'amount' => $amount,
            ]);

            Log::info('Stripe charge refunded', [
                'charge_id' => $chargeId,
                'refund_id' => $refund->id,
                'amount' => $amount,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Stripe charge refund failed', [
                'charge_id' => $chargeId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
