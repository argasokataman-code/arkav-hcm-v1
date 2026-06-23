<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Company;
use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Services\AddonRecurringSubscriptionService;
use App\Services\BillingTaxCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HcmSubscriptionCheckoutController
{
    use ChecksPermissions;
    use EnsuresHcmAdmin;

    public function __construct(
        private readonly AddonRecurringSubscriptionService $addonRecurringSubscriptionService,
    ) {}

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
        $baseAmount = $billingCycle === 'yearly' ? (float) $package->yearly_price : (float) $package->monthly_price;
        $pricingBreakdown = $this->buildSubscriptionPricingBreakdown($company->id, $baseAmount);
        $amountDue = (float) $pricingBreakdown['total_amount'];

        // 24-hour payment window by default.
        $dueDate = now()->addDay()->toDateString();

        return DB::transaction(function () use ($company, $package, $billingCycle, $baseAmount, $amountDue, $pricingBreakdown, $dueDate, $validated): JsonResponse {
            // Global guard: if there is ANY unpaid invoice for this company, reuse it and
            // never create a duplicate — regardless of subscription status.
            $anyUnpaid = Invoice::query()
                ->where('company_id', $company->id)
                ->where('is_paid', false)
                ->whereIn('status', ['draft', 'sent'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($anyUnpaid) {
                if ((float) $anyUnpaid->amount_due <= 0) {
                    $anyUnpaid->markAsPaid();
                    $anyUnpaid = $anyUnpaid->fresh();
                }

                // If user selected a DIFFERENT package, cancel old invoice + create new one
                $oldSub = Subscription::where('id', $anyUnpaid->subscription_id)->first();
                $oldPkgUuid = $oldSub?->package_uuid;
                if ($oldPkgUuid && $oldPkgUuid !== $package->uuid) {
                    $anyUnpaid->status = 'cancelled';
                    $anyUnpaid->save();
                } else {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'invoice' => [
                                'id' => $anyUnpaid->id,
                                'invoiceNumber' => $anyUnpaid->invoice_number,
                                'issueDate' => $anyUnpaid->issue_date?->toDateString(),
                                'dueDate' => $anyUnpaid->due_date?->toDateString(),
                                'amountDue' => (float) $anyUnpaid->amount_due,
                                'isPaid' => (bool) $anyUnpaid->is_paid,
                                'status' => (string) $anyUnpaid->status,
                            ],
                            'reused' => true,
                        ],
                    ]);
                }
            }

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
                    if ((float) $existingInvoice->amount_due <= 0) {
                        $existingInvoice->markAsPaid();
                        $existingInvoice = $existingInvoice->fresh();
                        $existingPending = $existingPending->fresh();
                    }

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

            $subscription = $trialSub ?: new Subscription;
            if (! $subscription->exists) {
                $subscription->company_id = $company->id;
                $subscription->starts_at = now();
                $subscription->auto_renew = false;
            }

            $subscription->package_uuid = $package->uuid;
            $subscription->plan_code = $package->code;
            $subscription->status = 'pending_payment';
            $subscription->billing_cycle = $billingCycle;
            $subscription->amount = $baseAmount;
            $subscription->trial_ends_at = null;
            $subscription->ends_at = now()->addHours(24);
            $subscription->save();

            // Restore addon amounts from old paid transactions
            $this->addonRecurringSubscriptionService->restoreForSubscription($subscription);

            $taxRateSnapshot = app(BillingTaxCalculationService::class)
                ->resolvePolicyRateSnapshot($company->id, now()->format('Y-m'));

            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
                'purchase_transaction_id' => null,
                'issue_date' => now()->toDateString(),
                'due_date' => $dueDate,
                'amount_due' => $amountDue,
                'billing_tax_rate_snapshot' => $taxRateSnapshot > 0 ? $taxRateSnapshot : null,
                'status' => 'draft',
                'notes' => $this->buildInvoicePricingNotes(
                    'tenant_subscription_checkout',
                    $pricingBreakdown,
                    'Created from tenant subscription checkout.'
                ),
            ]);

            // Unlimited / zero-priced plans should not be locked in pending payment.
            if ($amountDue <= 0) {
                $invoice->markAsPaid();
                $invoice = $invoice->fresh();
                $subscription = $subscription->fresh();
            } else {
                $billingEmail = $validated['billingEmail'] ?? null;
                SendInvoiceEmailJob::dispatch($invoice->id, $billingEmail)->afterCommit();
            }

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

    /**
     * POST /v1/hcm/billing/addons/checkout
     *
     * Tenant checkout for a single add-on: create (or reuse) pending invoice for active company.
     */
    public function checkoutAddon(Request $request): JsonResponse
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
            'addon_id' => ['nullable', 'integer', Rule::exists('package_addons', 'id')],
            'addon_uuid' => ['nullable', 'uuid', Rule::exists('package_addons', 'uuid')],
            'billingEmail' => ['nullable', 'string', 'email:rfc', 'max:255'],
        ]);

        if (! isset($validated['addon_id']) && ! isset($validated['addon_uuid'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'addon_id or addon_uuid is required.',
                ],
            ], 422);
        }

        /** @var Company $company */
        $company = Company::query()->findOrFail($activeCompanyId);

        /** @var PackageAddon $addon */
        $addonQuery = PackageAddon::query()->where('status', 'active');
        if (isset($validated['addon_id'])) {
            $addonQuery->whereKey((int) $validated['addon_id']);
        } else {
            $addonQuery->where('uuid', (string) $validated['addon_uuid']);
        }
        $addon = $addonQuery->firstOrFail();

        // Guard: must have active or pending_payment subscription
        $hasActiveSub = Subscription::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['active', 'pending_payment'])
            ->exists();

        if (! $hasActiveSub) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'Anda harus memiliki subscription aktif sebelum membeli add-on.',
                ],
            ], 422);
        }

        $alreadyActiveAddon = PurchaseTransaction::query()
            ->where('company_id', $company->id)
            ->where('package_addon_id', $addon->id)
            ->where('transaction_type', 'addon')
            ->where('status', 'paid')
            ->whereHas('subscription', function ($q): void {
                $q->whereIn('status', ['active', 'grace_period']);
            })
            ->exists();

        if ($alreadyActiveAddon) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADDON_ALREADY_ACTIVE',
                    'message' => 'Add-on ini sudah aktif pada company Anda.',
                ],
            ], 409);
        }

        $baseAmount = (float) $addon->price_per_unit;
        $pricingBreakdown = $this->buildAddonPricingBreakdown($company->id, $baseAmount);
        $amountDue = (float) $pricingBreakdown['total_amount'];
        $dueDate = now()->addDay()->toDateString();

        return DB::transaction(function () use ($company, $addon, $baseAmount, $amountDue, $pricingBreakdown, $dueDate, $validated): JsonResponse {
            $anyUnpaid = Invoice::query()
                ->where('company_id', $company->id)
                ->where('is_paid', false)
                ->whereIn('status', ['draft', 'sent'])
                ->whereHas('purchaseTransaction', function ($query) use ($addon): void {
                    $query->where('transaction_type', 'addon')
                        ->where('package_addon_id', $addon->id);
                })
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($anyUnpaid) {
                if ((float) $anyUnpaid->amount_due <= 0) {
                    $anyUnpaid->markAsPaid();
                    $anyUnpaid = $anyUnpaid->fresh();
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'invoice' => [
                            'id' => $anyUnpaid->id,
                            'invoiceNumber' => $anyUnpaid->invoice_number,
                            'issueDate' => $anyUnpaid->issue_date?->toDateString(),
                            'dueDate' => $anyUnpaid->due_date?->toDateString(),
                            'amountDue' => (float) $anyUnpaid->amount_due,
                            'isPaid' => (bool) $anyUnpaid->is_paid,
                            'status' => (string) $anyUnpaid->status,
                        ],
                        'reused' => true,
                    ],
                ]);
            }

            $activeSubscription = Subscription::query()
                ->where('company_id', $company->id)
                ->whereIn('status', ['active', 'pending_payment'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $addonTaxAmount = (float) ($pricingBreakdown['addon_tax_amount'] ?? 0);

            $billingPeriodStart = $activeSubscription?->ends_at ?? now();
            $billingPeriodEnd = $billingPeriodStart->copy()->addMonth();
            if ($activeSubscription?->billing_cycle === 'yearly') {
                $billingPeriodEnd = $billingPeriodStart->copy()->addYear();
            }

            $transaction = PurchaseTransaction::query()->create([
                'transaction_code' => PurchaseTransaction::generateCode(),
                'company_id' => $company->id,
                'subscription_id' => $activeSubscription?->id,
                'package_addon_id' => $addon->id,
                'transaction_type' => 'addon',
                'description' => 'Addon checkout: '.$addon->name,
                'amount' => $baseAmount,
                'tax_amount' => $addonTaxAmount,
                'discount_amount' => 0,
                'total_amount' => $amountDue,
                'billing_period_start' => $billingPeriodStart->toDateString(),
                'billing_period_end' => $billingPeriodEnd->toDateString(),
                'status' => 'issued',
                'due_date' => now()->addDay(),
            ]);

            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'subscription_id' => $activeSubscription?->id,
                'purchase_transaction_id' => $transaction->id,
                'issue_date' => now()->toDateString(),
                'due_date' => $dueDate,
                'amount_due' => $amountDue,
                'status' => 'draft',
                'notes' => $this->buildInvoicePricingNotes(
                    'tenant_addon_checkout',
                    array_merge($pricingBreakdown, [
                        'addon_code' => $addon->code,
                        'addon_name' => $addon->name,
                    ]),
                    'Created from tenant add-on checkout.'
                ),
            ]);

            if ($amountDue <= 0) {
                $invoice->markAsPaid();
                $invoice = $invoice->fresh();
                $transaction->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
                $this->addonRecurringSubscriptionService->applyFromTransaction($transaction->fresh());
            } else {
                $billingEmail = $validated['billingEmail'] ?? null;
                SendInvoiceEmailJob::dispatch($invoice->id, $billingEmail)->afterCommit();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'addon' => [
                        'id' => $addon->id,
                        'uuid' => $addon->uuid,
                        'code' => $addon->code,
                        'name' => $addon->name,
                        'pricePerUnit' => (float) $addon->price_per_unit,
                        'unitName' => $addon->unit_name,
                    ],
                    'transaction' => [
                        'id' => $transaction->id,
                        'code' => $transaction->transaction_code,
                        'status' => $transaction->status,
                        'amount' => (float) $transaction->amount,
                        'taxAmount' => (float) $transaction->tax_amount,
                        'totalAmount' => (float) $transaction->total_amount,
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

    public function cancelAddon(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
            ], 422);
        }

        $validated = $request->validate([
            'addon_id' => ['nullable', 'integer', Rule::exists('package_addons', 'id')],
            'addon_uuid' => ['nullable', 'uuid', Rule::exists('package_addons', 'uuid')],
        ]);

        if (! isset($validated['addon_id']) && ! isset($validated['addon_uuid'])) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'addon_id or addon_uuid is required.'],
            ], 422);
        }

        return DB::transaction(function () use ($activeCompanyId, $validated): JsonResponse {
            $subscription = Subscription::query()
                ->where('company_id', $activeCompanyId)
                ->whereIn('status', ['active', 'trial', 'grace_period'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $subscription) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'NO_ACTIVE_SUBSCRIPTION', 'message' => 'Tidak ada subscription aktif.'],
                ], 422);
            }

            $addonQuery = PackageAddon::query();
            if (isset($validated['addon_id'])) {
                $addonQuery->whereKey((int) $validated['addon_id']);
            } else {
                $addonQuery->where('uuid', (string) $validated['addon_uuid']);
            }
            $addon = $addonQuery->firstOrFail();

            $transaction = PurchaseTransaction::query()
                ->where('company_id', $activeCompanyId)
                ->where('subscription_id', $subscription->id)
                ->where('package_addon_id', $addon->id)
                ->where('transaction_type', 'addon')
                ->where('status', 'paid')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $transaction) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'ADDON_NOT_FOUND', 'message' => 'Addon tidak ditemukan atau belum aktif.'],
                ], 404);
            }

            $addonAmount = (float) ($transaction->total_amount ?? 0);
            $currentAmount = (float) ($subscription->amount ?? 0);
            $newAmount = round(max(0, $currentAmount - $addonAmount), 2);

            $metadata = (array) ($subscription->metadata ?? []);
            $appliedIds = array_values(array_filter((array) ($metadata['addon_applied_transaction_ids'] ?? []), fn ($v) => is_numeric($v)));
            $filteredIds = array_values(array_filter($appliedIds, fn ($id) => (int) $id !== (int) $transaction->id));
            $currentRecurring = (float) ($metadata['addon_recurring_total'] ?? 0);
            $newRecurring = round(max(0, $currentRecurring - $addonAmount), 2);

            $transaction->update(['status' => 'cancelled']);

            $metadata['addon_applied_transaction_ids'] = $filteredIds;
            $metadata['addon_recurring_total'] = $newRecurring;
            $metadata['cancelled_addon_ids'] = array_values(array_unique(array_merge(
                (array) ($metadata['cancelled_addon_ids'] ?? []),
                [$transaction->id]
            )));

            $subscription->update([
                'amount' => $newAmount,
                'metadata' => $metadata,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'addon' => [
                        'id' => $addon->id,
                        'code' => $addon->code,
                        'name' => $addon->name,
                    ],
                    'previousAmount' => $currentAmount,
                    'newAmount' => $newAmount,
                    'effective' => 'next_billing_cycle',
                ],
            ]);
        });
    }

    private function buildSubscriptionPricingBreakdown(int $companyId, float $baseAmount): array
    {
        $billingMonth = now()->format('Y-m');

        $policy = HcmBillingTaxPolicy::query()
            ->where('company_id', $companyId)
            ->where('billing_month', $billingMonth)
            ->where('status', 'active')
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->first();

        if (! $policy) {
            $globalPolicyCandidates = HcmBillingTaxPolicy::query()
                ->where('billing_month', $billingMonth)
                ->where('status', 'active')
                ->orderByDesc('effective_from')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            foreach ($globalPolicyCandidates as $candidate) {
                $decoded = json_decode((string) ($candidate->notes ?? ''), true);
                if (is_array($decoded) && isset($decoded['global_rates']) && is_array($decoded['global_rates'])) {
                    $policy = $candidate;
                    break;
                }
            }
        }

        $defaultSubscriptionTaxRate = $this->resolveDefaultSubscriptionTaxRate($policy);
        [$components, $subscriptionTaxRate, $subscriptionTaxAmount] =
            $this->resolvePricingComponents($policy, $baseAmount, $defaultSubscriptionTaxRate);

        $totalAdjustments = round((float) collect($components)->sum(fn (array $component): float => (float) ($component['amount'] ?? 0)), 2);
        $totalAmount = round($baseAmount + $totalAdjustments, 2);

        return [
            'billing_month' => $billingMonth,
            'policy_id' => $policy?->id,
            'base_amount' => round($baseAmount, 2),
            'components' => $components,
            'total_adjustments' => $totalAdjustments,
            'subscription_tax_rate' => $subscriptionTaxRate,
            'subscription_tax_amount' => $subscriptionTaxAmount,
            'total_amount' => $totalAmount,
        ];
    }

    private function buildAddonPricingBreakdown(int $companyId, float $baseAmount): array
    {
        $billingMonth = now()->format('Y-m');

        $policy = HcmBillingTaxPolicy::query()
            ->where('company_id', $companyId)
            ->where('billing_month', $billingMonth)
            ->where('status', 'active')
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->first();

        if (! $policy) {
            $globalPolicyCandidates = HcmBillingTaxPolicy::query()
                ->where('billing_month', $billingMonth)
                ->where('status', 'active')
                ->orderByDesc('effective_from')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            foreach ($globalPolicyCandidates as $candidate) {
                $decoded = json_decode((string) ($candidate->notes ?? ''), true);
                if (is_array($decoded) && isset($decoded['global_rates']) && is_array($decoded['global_rates'])) {
                    $policy = $candidate;
                    break;
                }
            }
        }

        $notes = json_decode((string) ($policy?->notes ?? ''), true);
        $globalRates = is_array($notes) && isset($notes['global_rates']) && is_array($notes['global_rates'])
            ? $notes['global_rates']
            : [];
        $customLabels = is_array($notes) && isset($notes['global_rate_labels']) && is_array($notes['global_rate_labels'])
            ? $notes['global_rate_labels']
            : [];

        $addonRate = isset($globalRates['addon_markup_rate']) && is_numeric($globalRates['addon_markup_rate'])
            ? (float) $globalRates['addon_markup_rate']
            : 0.0;
        $addonAmount = round($baseAmount * ($addonRate / 100), 2);
        $addonLabel = (string) ($customLabels['addon_markup_rate'] ?? 'Corporate tax');

        $components = [[
            'key' => 'addon_markup_rate',
            'label' => $addonLabel,
            'rate' => $addonRate,
            'amount' => $addonAmount,
        ]];

        return [
            'billing_month' => $billingMonth,
            'policy_id' => $policy?->id,
            'base_amount' => round($baseAmount, 2),
            'components' => $components,
            'total_adjustments' => $addonAmount,
            'addon_tax_rate' => $addonRate,
            'addon_tax_amount' => $addonAmount,
            'total_amount' => round($baseAmount + $addonAmount, 2),
        ];
    }

    private function resolvePricingComponents(?HcmBillingTaxPolicy $policy, float $baseAmount, float $defaultSubscriptionTaxRate): array
    {
        $notes = json_decode((string) ($policy?->notes ?? ''), true);
        $globalRates = is_array($notes) && isset($notes['global_rates']) && is_array($notes['global_rates'])
            ? $notes['global_rates']
            : [];
        $customLabels = is_array($notes) && isset($notes['global_rate_labels']) && is_array($notes['global_rate_labels'])
            ? $notes['global_rate_labels']
            : [];

        $resolvedRates = [];
        foreach ($globalRates as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $componentKey = Str::snake((string) $key);
            if ($componentKey === '') {
                continue;
            }

            $resolvedRates[$componentKey] = (float) $value;
        }

        // Government compliance policy stores customer transaction tax in nested notes,
        // while global subscription_tax_rate is used for corporate tax reporting.
        if (is_array($notes) && (string) ($notes['source'] ?? '') === 'government_tax_compliance_policy') {
            $transactionTaxRate = $this->extractGovernmentTransactionTaxRate($notes);
            if ($transactionTaxRate !== null) {
                $resolvedRates['subscription_tax_rate'] = $transactionTaxRate;
            }
        }

        if (! array_key_exists('subscription_tax_rate', $resolvedRates)) {
            $resolvedRates['subscription_tax_rate'] = $defaultSubscriptionTaxRate;
        }

        $defaultLabels = [
            'subscription_tax_rate' => 'Pajak langganan',
            'addon_markup_rate' => 'Corporate tax',
        ];

        $components = [];
        foreach ($resolvedRates as $componentKey => $rate) {
            $amount = round($baseAmount * ($rate / 100), 2);
            $label = $customLabels[$componentKey] ?? $defaultLabels[$componentKey] ?? Str::title(str_replace('_', ' ', $componentKey));

            $components[] = [
                'key' => $componentKey,
                'label' => (string) $label,
                'rate' => $rate,
                'amount' => $amount,
            ];
        }

        $subscriptionTaxRate = 0.0;
        $subscriptionTaxAmount = 0.0;
        foreach ($components as $component) {
            if (($component['key'] ?? null) === 'subscription_tax_rate') {
                $subscriptionTaxRate = (float) ($component['rate'] ?? 0);
                $subscriptionTaxAmount = (float) ($component['amount'] ?? 0);
            }
        }

        return [$components, $subscriptionTaxRate, $subscriptionTaxAmount];
    }

    private function resolveDefaultSubscriptionTaxRate(?HcmBillingTaxPolicy $policy): float
    {
        $defaultRate = (float) ($policy?->tax_rate_percentage ?? 0);
        $notes = json_decode((string) ($policy?->notes ?? ''), true);

        if (! is_array($notes) || (string) ($notes['source'] ?? '') !== 'government_tax_compliance_policy') {
            return max(0.0, min(100.0, $defaultRate));
        }

        $transactionTaxRate = $this->extractGovernmentTransactionTaxRate($notes);
        if ($transactionTaxRate === null) {
            return max(0.0, min(100.0, $defaultRate));
        }

        return $transactionTaxRate;
    }

    private function extractGovernmentTransactionTaxRate(array $policyNotes): ?float
    {
        $rawNotes = $policyNotes['notes'] ?? null;
        $nestedNotes = is_array($rawNotes)
            ? $rawNotes
            : (is_string($rawNotes) ? json_decode($rawNotes, true) : null);

        if (! is_array($nestedNotes)) {
            return null;
        }

        $rate = $nestedNotes['transaction_tax']['tax_rate'] ?? null;
        if (! is_numeric($rate)) {
            return null;
        }

        return max(0.0, min(100.0, (float) $rate));
    }

    private function buildInvoicePricingNotes(string $source, array $pricingBreakdown, string $fallbackMessage): string
    {
        $payload = [
            'source' => $source,
            'message' => $fallbackMessage,
            'pricing_breakdown' => $pricingBreakdown,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : $fallbackMessage;
    }
}
