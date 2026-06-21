<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Jobs\SendInvoiceEmailJob;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\HcmBillingTaxPolicy;
use App\Models\HcmManualActivity;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BillingTaxCalculationService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\HcmUserManagementSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicOnboardingController
{
    private function verifyTurnstileOrFail(Request $request, array $validated): void
    {
        if (! config('turnstile.enabled')) {
            return;
        }

        $token = trim((string) ($validated['turnstile_token'] ?? ''));
        if ($token === '') {
            throw ValidationException::withMessages([
                'turnstile_token' => ['Turnstile token is required.'],
            ]);
        }

        $secret = (string) config('turnstile.secret_key', '');
        if (! $secret) {
            throw ValidationException::withMessages([
                'turnstile_token' => ['Turnstile is enabled but secret key is not configured.'],
            ]);
        }

        $verifyUrl = (string) config('turnstile.verify_url', 'https://challenges.cloudflare.com/turnstile/v0/siteverify');

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post($verifyUrl, [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => (string) $request->ip(),
                ]);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'turnstile_token' => ['Failed to verify Turnstile token. Please retry.'],
            ]);
        }

        if (! $response->ok()) {
            throw ValidationException::withMessages([
                'turnstile_token' => ['Turnstile verification failed. Please retry.'],
            ]);
        }

        $payload = $response->json();
        $success = (bool) data_get($payload, 'success', false);
        if (! $success) {
            $codes = data_get($payload, 'error-codes', []);
            $codesText = is_array($codes) ? implode(', ', array_map('strval', $codes)) : '';
            $suffix = $codesText !== '' ? ' ('.$codesText.')' : '';

            throw ValidationException::withMessages([
                'turnstile_token' => ['Turnstile token is invalid or expired'.$suffix.'.'],
            ]);
        }

    }

    private function parsePricingBreakdownFromNotes(?string $notes): ?array
    {
        if ($notes === null || $notes === '') {
            return null;
        }

        $decoded = json_decode($notes, true);
        if (! is_array($decoded)) {
            return null;
        }

        $pricing = $decoded['pricing_breakdown'] ?? null;
        if (! is_array($pricing)) {
            return null;
        }

        $components = is_array($pricing['components'] ?? null) ? array_values($pricing['components']) : [];
        $publicComponents = array_values(array_filter($components, function ($component): bool {
            if (! is_array($component)) {
                return false;
            }

            return $this->isPublicPricingComponentKey((string) ($component['key'] ?? ''));
        }));

        return [
            'base_amount' => isset($pricing['base_amount']) ? (float) $pricing['base_amount'] : null,
            'subscription_tax_rate' => isset($pricing['subscription_tax_rate']) ? (float) $pricing['subscription_tax_rate'] : null,
            'subscription_tax_amount' => isset($pricing['subscription_tax_amount']) ? (float) $pricing['subscription_tax_amount'] : null,
            'total_amount' => isset($pricing['total_amount']) ? (float) $pricing['total_amount'] : null,
            'components' => $publicComponents,
        ];
    }

    private function isPublicPricingComponentKey(string $componentKey): bool
    {
        return Str::snake($componentKey) === 'subscription_tax_rate';
    }

    private function normalizeCompanyCodeBase(string $name): string
    {
        $raw = strtolower(trim($name));
        $raw = preg_replace('/\s+/', '-', $raw) ?? $raw;
        $raw = preg_replace('/[^a-z0-9_-]/', '', $raw) ?? $raw;
        $raw = trim($raw, '-_');

        if ($raw === '') {
            $raw = 'company';
        }

        return substr($raw, 0, 40);
    }

    private function generateUniqueCompanyCode(string $companyName): string
    {
        $base = $this->normalizeCompanyCodeBase($companyName);

        for ($i = 0; $i < 25; $i++) {
            // 4 chars gives 1.6M combos, enough for a tenant code suffix.
            $suffix = substr(bin2hex(random_bytes(2)), 0, 4);
            $candidate = $base.'_'.$suffix;
            $candidate = substr($candidate, 0, 100);

            $exists = Company::query()->where('code', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        // Fallback: last resort, longer suffix.
        return $base.'_'.substr(bin2hex(random_bytes(4)), 0, 8);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_uuid' => ['required', 'uuid', Rule::exists('packages', 'uuid')->where(fn ($q) => $q->where('status', 'active'))],
            'billing_cycle' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'start_mode' => ['nullable', 'string', Rule::in(['trial', 'pending_payment'])],
            'turnstile_token' => ['nullable', 'string', 'max:2048'],
            'website' => ['nullable', 'string', 'max:200'], // honeypot

            // company.code is auto-generated if not provided (must still match the same regex when provided).
            'company.code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:companies,code'],
            'company.name' => ['required', 'string', 'min:2', 'max:255'],
            'company.legal_name' => ['nullable', 'string', 'max:255'],
            'company.timezone' => ['required', 'string', 'max:100'],
            'company.currency' => ['required', 'string', 'max:10'],
            'company.country_code' => ['required', 'string', 'max:10'],
            'company.contact_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s().]{6,20}$/'],
            'company.contact_person_name' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z\s\'.\-]+$/'],
            'company.contact_person_role' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9\s\'.\-\/&,]+$/'],
            'company.address' => ['required', 'string', 'max:500'],
            'company.city' => ['required', 'string', 'max:120'],
            'company.postal_code' => ['nullable', 'string', 'max:12', 'regex:/^[0-9]{3,12}$/'],

            'owner.name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,149}$/'],
            'owner.email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'owner.phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s().]{6,20}$/'],
            'owner.password' => ['required', 'string', 'min:8', 'max:64', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/'],
            'owner.confirmPassword' => ['required', 'same:owner.password'],
            'billingEmail' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'consent_accepted' => ['required', 'accepted'],
        ]);

        $this->verifyTurnstileOrFail($request, $validated);

        $consentIp = $request->ip();

        return DB::transaction(function () use ($validated, $consentIp): JsonResponse {
            /** @var Package $package */
            $package = Package::query()->where('uuid', $validated['package_uuid'])->firstOrFail();

            if ((string) $package->code === 'trial') {
                // Guardrail: trial package is only for start_mode=trial.
                $startMode = (string) ($validated['start_mode'] ?? 'trial');
                if ($startMode === 'pending_payment') {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'VALIDATION_ERROR',
                            'message' => 'Trial package cannot be started with pending_payment mode.',
                        ],
                    ], 422);
                }
            }

            $owner = User::query()->create([
                'name' => $validated['owner']['name'],
                'email' => $validated['owner']['email'],
                'password' => Hash::make($validated['owner']['password']),
            ]);

            $companyCode = $validated['company']['code'] ?? null;
            $companyCode = is_string($companyCode) ? trim($companyCode) : null;
            if (! $companyCode) {
                $companyCode = $this->generateUniqueCompanyCode($validated['company']['name']);
            }

            $company = Company::query()->create([
                'code' => $companyCode,
                'name' => $validated['company']['name'],
                'legal_name' => $validated['company']['legal_name'] ?? null,
                'status' => 'active',
                'owner_user_id' => $owner->id,
                'timezone' => $validated['company']['timezone'],
                'currency' => $validated['company']['currency'],
                'country_code' => $validated['company']['country_code'],
                'onboarding_consent_accepted' => true,
                'onboarding_consent_at' => now(),
                'onboarding_consent_ip' => $consentIp,
            ]);

            $companyContactPhone = $validated['company']['contact_phone'] ?? null;
            if (is_string($companyContactPhone)) {
                $companyContactPhone = trim($companyContactPhone);
            }
            if ($companyContactPhone) {
                CompanySetting::query()->updateOrCreate(
                    ['company_id' => $company->id, 'key' => 'business_phone'],
                    ['value' => $companyContactPhone, 'type' => 'string']
                );
            }

            $contactName = $validated['company']['contact_person_name'] ?? null;
            if (is_string($contactName)) {
                $contactName = trim($contactName);
            }
            if ($contactName) {
                CompanySetting::query()->updateOrCreate(
                    ['company_id' => $company->id, 'key' => 'business_contact_name'],
                    ['value' => $contactName, 'type' => 'string']
                );
            }

            $contactRole = $validated['company']['contact_person_role'] ?? null;
            if (is_string($contactRole)) {
                $contactRole = trim($contactRole);
            }
            if ($contactRole) {
                CompanySetting::query()->updateOrCreate(
                    ['company_id' => $company->id, 'key' => 'business_contact_role'],
                    ['value' => $contactRole, 'type' => 'string']
                );
            }

            $addr = $validated['company']['address'] ?? null;
            if (is_string($addr)) {
                $addr = trim($addr);
            }
            if ($addr) {
                CompanySetting::query()->updateOrCreate(
                    ['company_id' => $company->id, 'key' => 'business_address'],
                    ['value' => $addr, 'type' => 'string']
                );
            }

            $city = $validated['company']['city'] ?? null;
            if (is_string($city)) {
                $city = trim($city);
            }
            if ($city) {
                CompanySetting::query()->updateOrCreate(
                    ['company_id' => $company->id, 'key' => 'business_city'],
                    ['value' => $city, 'type' => 'string']
                );
            }

            $postal = $validated['company']['postal_code'] ?? null;
            if (is_string($postal)) {
                $postal = trim($postal);
            }
            if ($postal) {
                CompanySetting::query()->updateOrCreate(
                    ['company_id' => $company->id, 'key' => 'business_postal_code'],
                    ['value' => $postal, 'type' => 'string']
                );
            }

            $ownerPhone = $validated['owner']['phone'] ?? null;
            if (is_string($ownerPhone)) {
                $ownerPhone = trim($ownerPhone);
            }
            if ($ownerPhone) {
                CompanySetting::query()->updateOrCreate(
                    ['company_id' => $company->id, 'key' => 'owner_phone'],
                    ['value' => $ownerPhone, 'type' => 'string']
                );
            }

            CompanyUser::query()->create([
                'company_id' => $company->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            // Provision default tenant RBAC catalog (roles/permissions + owner admin assignment)
            // so newly subscribed companies can immediately use employee/admin flows.
            try {
                app(HcmUserManagementSeeder::class)->run();
            } catch (\Throwable $e) {
                Log::error(
                    'HcmUserManagementSeeder failed during public onboarding',
                    [
                        'company_id' => $company->id,
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]
                );
                throw ValidationException::withMessages([
                    'company_provisioning' => ['Failed to provision company roles and permissions. Please contact support.'],
                ]);
            }

            // Seed default company policies so admin can immediately view/edit starter templates.
            try {
                DatabaseSeeder::seedDefaultPoliciesForCompany($company);
            } catch (\Throwable $e) {
                Log::error(
                    'Default policies seeding failed during public onboarding',
                    [
                        'company_id' => $company->id,
                        'company_uuid' => $company->uuid ?? 'NULL',
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]
                );
                throw ValidationException::withMessages([
                    'company_provisioning' => ['Failed to provision company default policies. Please contact support.'],
                ]);
            }

            $startMode = (string) ($validated['start_mode'] ?? 'trial');

            // Log company registration/trial signup as manual activity
            $modeLabel = $startMode === 'pending_payment' ? 'pending payment' : 'trial';
            HcmManualActivity::query()->create([
                'company_id' => $company->id,
                'created_by_user_id' => $owner->id,
                'title' => 'Company Registration',
                'description' => 'Company registered and started '.$modeLabel.' with '.$package->name.' package',
                'status' => 'completed',
                'priority' => 'normal',
            ]);

            $startsAt = now()->startOfDay();
            $billingCycle = $validated['billing_cycle'];
            $periodEndsAt = $billingCycle === 'yearly'
                ? (clone $startsAt)->addYear()
                : (clone $startsAt)->addMonth();

            $trialEndsAt = (clone $startsAt)->addDays(30);

            // When starting as pending_payment, ends_at becomes a provisioning/payment window.
            $provisioningEndsAt = now()->addHours(24);

            $subscription = Subscription::query()->create([
                'company_id' => $company->id,
                'package_uuid' => $package->uuid,
                'plan_code' => $package->code,
                'status' => $startMode === 'pending_payment' ? 'pending_payment' : 'trial',
                'starts_at' => $startsAt,
                // ends_at becomes "provisioning/payment window end" after trial ends (see ConvertExpiredTrialsToPendingPaymentJob).
                'ends_at' => $startMode === 'pending_payment' ? $provisioningEndsAt : $periodEndsAt,
                'trial_ends_at' => $startMode === 'pending_payment' ? null : $trialEndsAt,
                'auto_renew' => false,
                'billing_cycle' => $billingCycle,
                'amount' => $billingCycle === 'yearly' ? $package->yearly_price : $package->monthly_price,
            ]);

            $invoice = null;
            if ($startMode === 'pending_payment') {
                try {
                    // Safety check: verify company and subscription exist
                    $companyExists = Company::query()->where('id', $company->id)->exists();
                    $subscriptionExists = Subscription::query()->where('id', $subscription->id)->exists();

                    if (! $companyExists) {
                        throw new \RuntimeException("Company {$company->id} not found in database");
                    }
                    if (! $subscriptionExists) {
                        throw new \RuntimeException("Subscription {$subscription->id} not found in database");
                    }

                    $baseAmount = (float) $subscription->amount;
                    Log::debug('Onboarding invoice: calculating pricing breakdown', [
                        'company_id' => $company->id,
                        'base_amount' => $baseAmount,
                    ]);

                    try {
                        $pricingBreakdown = $this->buildSubscriptionPricingBreakdown($company->id, $baseAmount);
                    } catch (\Throwable $priceEx) {
                        throw new \RuntimeException(
                            'Pricing breakdown calculation failed: '.$priceEx->getMessage(),
                            0,
                            $priceEx
                        );
                    }

                    $amountDue = (float) $pricingBreakdown['total_amount'];

                    Log::debug('Onboarding invoice: resolving tax rate', [
                        'company_id' => $company->id,
                        'amount_due' => $amountDue,
                    ]);

                    try {
                        $taxRateSnapshot = app(BillingTaxCalculationService::class)
                            ->resolvePolicyRateSnapshot($company->id, now()->format('Y-m'));
                    } catch (\Throwable $taxEx) {
                        throw new \RuntimeException(
                            'Tax rate resolution failed: '.$taxEx->getMessage(),
                            0,
                            $taxEx
                        );
                    }

                    Log::debug('Onboarding invoice: creating invoice record', [
                        'company_id' => $company->id,
                        'subscription_id' => $subscription->id,
                        'amount_due' => $amountDue,
                        'tax_rate' => $taxRateSnapshot,
                    ]);

                    try {
                        $invoiceData = [
                            'company_id' => $company->id,
                            'subscription_id' => $subscription->id,
                            'purchase_transaction_id' => null,
                            'issue_date' => now()->toDateString(),
                            'due_date' => now()->addDay()->toDateString(),
                            'amount_due' => $amountDue,
                            'billing_tax_rate_snapshot' => $taxRateSnapshot > 0 ? $taxRateSnapshot : null,
                            'status' => 'draft',
                            'notes' => $this->buildInvoicePricingNotes(
                                'public_onboarding',
                                $pricingBreakdown,
                                'Created from public onboarding.'
                            ),
                        ];

                        Log::debug('Onboarding invoice: prepared data', [
                            'keys' => array_keys($invoiceData),
                            'data' => $invoiceData,
                        ]);

                        $invoice = Invoice::query()->create($invoiceData);
                    } catch (\Throwable $createEx) {
                        throw new \RuntimeException(
                            'Invoice record creation failed: '.$createEx->getMessage(),
                            0,
                            $createEx
                        );
                    }

                    // Best-effort async email send (falls back to owner email if billingEmail is not provided)
                    // Wrap in try-catch to prevent email failures from failing the entire registration
                    try {
                        $billingEmail = $validated['billingEmail'] ?? null;
                        SendInvoiceEmailJob::dispatch($invoice->id, $billingEmail)->afterCommit();
                    } catch (\Throwable $emailEx) {
                        // Log but don't fail the request if email dispatch fails
                        Log::warning(
                            'Failed to dispatch invoice email during onboarding',
                            [
                                'invoice_id' => $invoice->id,
                                'company_id' => $company->id,
                                'exception' => get_class($emailEx),
                                'message' => $emailEx->getMessage(),
                            ]
                        );
                    }
                } catch (\Throwable $invoiceEx) {
                    Log::error(
                        'Failed to create invoice during pending_payment onboarding',
                        [
                            'company_id' => $company->id,
                            'subscription_id' => $subscription->id,
                            'exception' => get_class($invoiceEx),
                            'message' => $invoiceEx->getMessage(),
                            'file' => $invoiceEx->getFile(),
                            'line' => $invoiceEx->getLine(),
                            'trace' => $invoiceEx->getTraceAsString(),
                            'previous' => $invoiceEx->getPrevious() ? [
                                'exception' => get_class($invoiceEx->getPrevious()),
                                'message' => $invoiceEx->getPrevious()->getMessage(),
                            ] : null,
                        ]
                    );
                    throw ValidationException::withMessages([
                        'invoice_creation' => ['Failed to create invoice. Please contact support.'],
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'company' => [
                        'id' => $company->id,
                        'code' => $company->code,
                        'name' => $company->name,
                    ],
                    'owner' => [
                        'id' => $owner->id,
                        'name' => $owner->name,
                        'email' => $owner->email,
                    ],
                    'subscription' => [
                        'id' => $subscription->id,
                        'status' => $subscription->status,
                        'startsAt' => $subscription->starts_at,
                        'endsAt' => $subscription->ends_at,
                        'trialEndsAt' => $subscription->trial_ends_at,
                        'billingCycle' => $subscription->billing_cycle,
                        'amount' => $subscription->amount,
                        'packageId' => $package->uuid,
                        'packageCode' => $package->code,
                        'packageName' => $package->name,
                    ],
                    'invoice' => $invoice ? [
                        'id' => $invoice->id,
                        'invoiceNumber' => $invoice->invoice_number,
                        'issueDate' => $invoice->issue_date->toDateString(),
                        'dueDate' => $invoice->due_date->toDateString(),
                        'amountDue' => (float) $invoice->amount_due,
                        'isPaid' => (bool) $invoice->is_paid,
                        'status' => (string) $invoice->status,
                        'billingTaxRateSnapshot' => $invoice->billing_tax_rate_snapshot !== null ? (float) $invoice->billing_tax_rate_snapshot : null,
                        'pricingBreakdown' => $this->parsePricingBreakdownFromNotes($invoice->notes),
                    ] : null,
                ],
            ], 201);
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
