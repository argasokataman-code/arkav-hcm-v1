<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Jobs\SendInvoiceEmailJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HcmSubscriptionCheckoutController
{
    use ChecksPermissions;
    use EnsuresHcmAdmin;

    /**
     * POST /v1/hcm/billing/checkout
     *
     * Tenant checkout: create (or reuse) a pending_payment subscription + invoice for the active company.
     */
    public function checkout(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'package_uuid' => [
                'required',
                'uuid',
                Rule::exists('packages', 'uuid')->where(fn ($q) => $q->where('status', 'active')),
            ],
            'billing_cycle' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'billingEmail' => ['nullable', 'string', 'email:rfc', 'max:255'],
        ]);

        /** @var Package $package */
        $package = Package::query()->where('uuid', $validated['package_uuid'])->firstOrFail();

        if ((string) $package->code === 'trial') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Trial package cannot be used for checkout.',
                ],
            ], 422);
        }

        /** @var Company $company */
        $company = Company::query()->findOrFail($activeCompanyId);
        $billingCycle = (string) $validated['billing_cycle'];
        $amount = $billingCycle === 'yearly' ? (float) $package->yearly_price : (float) $package->monthly_price;

        // 7 days payment window by default.
        $dueDate = now()->addDays(7)->toDateString();

        return DB::transaction(function () use ($company, $package, $billingCycle, $amount, $dueDate, $validated): JsonResponse {
            // Reuse an existing unpaid invoice for an existing pending_payment subscription (if still valid).
            $existingPending = Subscription::query()
                ->where('company_id', $company->id)
                ->where('status', 'pending_payment')
                ->latest('id')
                ->first();

            if ($existingPending) {
                $existingInvoice = Invoice::query()
                    ->where('company_id', $company->id)
                    ->where('subscription_id', $existingPending->id)
                    ->where('is_paid', false)
                    ->whereIn('status', ['draft', 'sent'])
                    ->latest('id')
                    ->first();

                if ($existingInvoice && $existingInvoice->due_date && $existingInvoice->due_date->toDateString() >= now()->toDateString()) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'subscription' => [
                                'id' => $existingPending->id,
                                'status' => $existingPending->status,
                                'billingCycle' => $existingPending->billing_cycle,
                                'amount' => (float) $existingPending->amount,
                                'packageId' => $existingPending->package_uuid,
                                'packageCode' => $existingPending->plan_code,
                            ],
                            'invoice' => [
                                'id' => $existingInvoice->id,
                                'invoiceNumber' => $existingInvoice->invoice_number,
                                'issueDate' => $existingInvoice->issue_date?->toDateString(),
                                'dueDate' => $existingInvoice->due_date?->toDateString(),
                                'amountDue' => (float) $existingInvoice->amount_due,
                                'isPaid' => (bool) $existingInvoice->is_paid,
                                'status' => (string) $existingInvoice->status,
                            ],
                            'reused' => true,
                        ],
                    ]);
                }
            }

            // If there is an active trial subscription, convert it into pending_payment (upgrade path).
            $trialSub = Subscription::query()
                ->where('company_id', $company->id)
                ->where('status', 'trial')
                ->latest('id')
                ->first();

            $subscription = $trialSub ?: new Subscription();
            if (! $subscription->exists) {
                $subscription->company_id = $company->id;
                $subscription->starts_at = now()->startOfDay();
                $subscription->auto_renew = false;
            }

            $subscription->package_uuid = $package->uuid;
            $subscription->plan_code = $package->code;
            $subscription->status = 'pending_payment';
            $subscription->billing_cycle = $billingCycle;
            $subscription->amount = $amount;
            $subscription->trial_ends_at = null;
            $subscription->ends_at = now()->startOfDay()->addDays(7);
            $subscription->save();

            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
                'purchase_transaction_id' => null,
                'issue_date' => now()->toDateString(),
                'due_date' => $dueDate,
                'amount_due' => $amount,
                'status' => 'draft',
                'notes' => 'Created from tenant subscription checkout.',
            ]);

            $billingEmail = $validated['billingEmail'] ?? null;
            SendInvoiceEmailJob::dispatch($invoice->id, $billingEmail)->afterCommit();

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => [
                        'id' => $subscription->id,
                        'status' => $subscription->status,
                        'billingCycle' => $subscription->billing_cycle,
                        'amount' => (float) $subscription->amount,
                        'packageId' => $package->uuid,
                        'packageCode' => $package->code,
                        'packageName' => $package->name,
                    ],
                    'invoice' => [
                        'id' => $invoice->id,
                        'invoiceNumber' => $invoice->invoice_number,
                        'issueDate' => $invoice->issue_date?->toDateString(),
                        'dueDate' => $invoice->due_date?->toDateString(),
                        'amountDue' => (float) $invoice->amount_due,
                        'isPaid' => (bool) $invoice->is_paid,
                        'status' => (string) $invoice->status,
                    ],
                    'reused' => false,
                ],
            ], 201);
        });
    }
}

