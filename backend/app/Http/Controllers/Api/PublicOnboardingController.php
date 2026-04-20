<?php

namespace App\Http\Controllers\Api;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\HcmManualActivity;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Jobs\SendInvoiceEmailJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicOnboardingController
{
    private function verifyTurnstileOrFail(Request $request, array $validated): void
    {
        if (!config('turnstile.enabled')) {
            return;
        }

        $token = trim((string) ($validated['turnstile_token'] ?? ''));
        if ($token === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'turnstile_token' => ['Turnstile token is required.'],
            ]);
        }

        $secret = (string) config('turnstile.secret_key', '');
        if (!$secret) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'turnstile_token' => ['Turnstile is enabled but secret key is not configured.'],
            ]);
        }

        return;
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
            $candidate = $base . '_' . $suffix;
            $candidate = substr($candidate, 0, 100);

            $exists = Company::query()->where('code', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        // Fallback: last resort, longer suffix.
        return $base . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
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
            'company.name' => ['required', 'string', 'max:255'],
            'company.legal_name' => ['nullable', 'string', 'max:255'],
            'company.timezone' => ['required', 'string', 'max:100'],
            'company.currency' => ['required', 'string', 'max:10'],
            'company.country_code' => ['required', 'string', 'max:10'],
            'company.contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'company.contact_person_name' => ['nullable', 'string', 'max:120'],
            'company.contact_person_role' => ['nullable', 'string', 'max:120'],
            'company.address' => ['required', 'string', 'max:500'],
            'company.city' => ['required', 'string', 'max:120'],
            'company.postal_code' => ['nullable', 'string', 'max:12', 'regex:/^[0-9]{3,12}$/'],

            'owner.name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[A-Za-z][A-Za-z\s\'.-]{1,149}$/'],
            'owner.email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'owner.phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'owner.password' => ['required', 'string', 'min:8', 'max:64', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/'],
            'owner.confirmPassword' => ['required', 'same:owner.password'],
            'billingEmail' => ['nullable', 'string', 'email:rfc', 'max:255'],
        ]);

        $this->verifyTurnstileOrFail($request, $validated);

        return DB::transaction(function () use ($validated): JsonResponse {
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
            $provisioningEndsAt = (clone $startsAt)->addDays(7);

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
                $amountDue = (float) $subscription->amount;

                $invoice = Invoice::query()->create([
                    'company_id' => $company->id,
                    'subscription_id' => $subscription->id,
                    'purchase_transaction_id' => null,
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'amount_due' => $amountDue,
                    'status' => 'draft',
                    'notes' => 'Created from public onboarding.',
                ]);

                // Best-effort async email send (falls back to owner email if billingEmail is not provided)
                $billingEmail = $validated['billingEmail'] ?? null;
                SendInvoiceEmailJob::dispatch($invoice->id, $billingEmail)->afterCommit();
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
                    ] : null,
                ],
            ], 201);
        });
    }
}

