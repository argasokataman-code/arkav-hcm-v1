<?php

namespace App\Http\Controllers\Api\Auth\Concerns;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

trait HandlesAuthCompany
{
    private function resolveOwnerProfileSettings(?Company $activeCompany, string $activeCompanyRole): array
    {
        if (! $activeCompany || strtolower(trim($activeCompanyRole)) !== 'owner') {
            return [];
        }

        $settings = CompanySetting::query()
            ->where('company_id', $activeCompany->id)
            ->whereIn('key', ['owner_phone', 'owner_address', 'owner_address_detail', 'owner_profile_photo_path'])
            ->pluck('value', 'key');

        return [
            'phone' => $settings->get('owner_phone'),
            'address' => $settings->get('owner_address'),
            'addressDetail' => $settings->get('owner_address_detail'),
            'profilePhotoPath' => $settings->get('owner_profile_photo_path'),
        ];
    }

    private function storeOwnerProfileSettings(Company $company, ?string $phone, ?string $address, ?string $addressDetail, ?string $profilePhotoPath = null): void
    {
        $settings = [
            'owner_phone' => $phone,
            'owner_address' => $address,
            'owner_address_detail' => $addressDetail,
        ];

        // Photo path hanya diupdate jika secara eksplisit diberikan (null = hapus, tidak diberikan = skip).
        if (func_num_args() >= 5) {
            $settings['owner_profile_photo_path'] = $profilePhotoPath;
        }

        foreach ($settings as $key => $value) {
            CompanySetting::query()->updateOrCreate(
                ['company_id' => $company->id, 'key' => $key],
                ['value' => $value, 'type' => 'string']
            );
        }
    }

    /**
     * @return array<string, string|null>|null
     */
    private function resolveOwnerCompanyProfile(?Company $activeCompany, string $activeCompanyRole): ?array
    {
        if (! $activeCompany || strtolower(trim($activeCompanyRole)) !== 'owner') {
            return null;
        }

        $settings = CompanySetting::query()
            ->where('company_id', $activeCompany->id)
            ->whereIn('key', [
                'company_profile_address',
                'company_profile_city',
                'company_profile_state',
                'company_profile_country',
                'company_profile_postal_code',
                'company_profile_npwp',
            ])
            ->pluck('value', 'key');

        return [
            'name' => $activeCompany->name,
            'legalName' => $activeCompany->legal_name,
            'address' => $settings->get('company_profile_address'),
            'city' => $settings->get('company_profile_city'),
            'state' => $settings->get('company_profile_state'),
            'country' => $settings->get('company_profile_country'),
            'postalCode' => $settings->get('company_profile_postal_code'),
            'npwp' => $settings->get('company_profile_npwp'),
        ];
    }

    private function storeOwnerCompanyProfile(Request $request, Company $company): void
    {
        $companyName = trim((string) $request->input('companyName', ''));
        if ($companyName !== '') {
            $company->name = $companyName;
        }

        if ($request->has('companyLegalName')) {
            $legalName = trim((string) $request->input('companyLegalName', ''));
            $company->legal_name = $legalName !== '' ? $legalName : null;
        }

        if ($company->isDirty(['name', 'legal_name'])) {
            $company->save();
        }

        $normalizedNpwp = $this->normalizeNpwpInput((string) $request->input('companyNpwp', ''));

        $profileSettings = [
            'company_profile_address' => trim((string) $request->input('companyAddress', '')) ?: null,
            'company_profile_city' => trim((string) $request->input('companyCity', '')) ?: null,
            'company_profile_state' => trim((string) $request->input('companyState', '')) ?: null,
            'company_profile_country' => trim((string) $request->input('companyCountry', '')) ?: null,
            'company_profile_postal_code' => trim((string) $request->input('companyPostalCode', '')) ?: null,
            'company_profile_npwp' => $normalizedNpwp !== '' ? $normalizedNpwp : null,
        ];

        foreach ($profileSettings as $key => $value) {
            CompanySetting::query()->updateOrCreate(
                ['company_id' => $company->id, 'key' => $key],
                ['value' => $value, 'type' => 'string']
            );
        }
    }

    private function buildSubscriptionSummary(?Company $activeCompany): ?array
    {
        if (! $activeCompany) {
            return null;
        }

        $subscription = $activeCompany->latestSubscription;
        if (! $subscription) {
            return null;
        }

        $nextInvoice = Invoice::query()
            ->where('company_id', $activeCompany->id)
            ->where('subscription_id', $subscription->id)
            ->where('is_paid', false)
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('issue_date')
            ->orderBy('id')
            ->first();

        $nextPaymentDate = $nextInvoice?->due_date?->toDateString()
            ?? $subscription->ends_at?->toDateString();
        $nextPaymentAmount = $nextInvoice ? (float) $nextInvoice->amount_due : (float) ($subscription->amount ?? 0);
        // Feature code for employee count limit is 'max_employees' (see LandingPackagesSeeder).
        $employeeLimitFeature = $subscription->package?->getFeature('max_employees');
        $employeeLimit = $employeeLimitFeature?->limit;
        $employeeUsed = EmployeeProfile::query()
            ->where('company_id', $activeCompany->id)
            ->when($activeCompany->owner_user_id, fn ($q) => $q->where('user_id', '!=', $activeCompany->owner_user_id))
            ->count();

        // Collect enabled feature codes for this package (limit != 0 or unlimited).
        $enabledFeatures = $subscription->package
            ? $subscription->package->features()
                ->where(function ($q): void {
                    $q->whereNull('limit')->orWhere('limit', '!=', 0);
                })
                ->pluck('feature_code')
                ->values()
                ->all()
            : [];

        return [
            'id' => $subscription->id,
            'status' => (string) $subscription->status,
            'planCode' => (string) ($subscription->plan_code ?? ''),
            'packageCode' => (string) ($subscription->package?->code ?? $subscription->plan_code ?? ''),
            'packageName' => (string) ($subscription->package?->name ?? ''),
            'billingCycle' => (string) ($subscription->billing_cycle ?? ''),
            'startsAt' => $subscription->starts_at?->toIso8601String(),
            'endsAt' => $subscription->ends_at?->toIso8601String(),
            'trialEndsAt' => $subscription->trial_ends_at?->toIso8601String(),
            'amount' => (float) ($subscription->amount ?? 0),
            'autoRenew' => (bool) $subscription->auto_renew,
            'features' => $enabledFeatures,
            'nextPayment' => [
                'date' => $nextPaymentDate,
                'amount' => $nextPaymentAmount,
                'source' => $nextInvoice ? 'invoice' : 'subscription_cycle',
                'invoiceId' => $nextInvoice?->id,
                'invoiceNumber' => $nextInvoice?->invoice_number,
                'invoiceStatus' => $nextInvoice?->status,
            ],
            'employeeSlots' => [
                'limit' => $employeeLimit,
                'used' => $employeeUsed,
                'remaining' => $employeeLimit === null ? null : ($employeeLimit - $employeeUsed),
                'isUnlimited' => $employeeLimitFeature !== null && $employeeLimit === null,
                'isConfigured' => $employeeLimitFeature !== null,
                'isOverLimit' => $employeeLimit !== null && $employeeUsed > $employeeLimit,
            ],
        ];
    }

    private function attachUserToDefaultCompany(User $user): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('company_users')) {
            return;
        }

        $defaultCompany = Company::query()->firstOrCreate(
            ['code' => 'default_company'],
            [
                'name' => 'Default Company',
                'legal_name' => 'Default Company',
                'status' => 'active',
                'owner_user_id' => null,
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'country_code' => 'ID',
            ]
        );

        $legacyCompanyId = $this->resolveLegacyCompanyId($defaultCompany);
        $legacyUserId = $this->resolveLegacyUserId($user);
        if (! $legacyCompanyId || ! $legacyUserId) {
            return;
        }

        CompanyUser::query()->firstOrCreate(
            [
                'company_id' => $legacyCompanyId,
                'user_id' => $legacyUserId,
            ],
            [
                'role' => 'member',
                'status' => 'active',
                'joined_at' => now(),
                'invited_by_user_id' => null,
            ]
        );
    }

    private function ensureUserHasActiveCompanyMembership(User $user): void
    {
        if (! Schema::hasTable('company_users')) {
            return;
        }

        $legacyUserId = $this->resolveLegacyUserId($user);
        if (! $legacyUserId) {
            return;
        }

        $hasActiveMembership = CompanyUser::query()
            ->where('user_id', $legacyUserId)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveMembership) {
            return;
        }

        $this->attachUserToDefaultCompany($user);
    }

    private function resolveLegacyUserId(User $user): ?int
    {
        $id = $user->getAttribute('id');
        if (is_numeric($id)) {
            return (int) $id;
        }

        $uuid = (string) ($user->getAttribute('uuid') ?? '');
        if ($uuid === '' || ! Schema::hasColumn($user->getTable(), 'uuid')) {
            return null;
        }

        $resolved = User::query()->where('uuid', $uuid)->value('id');

        return is_numeric($resolved) ? (int) $resolved : null;
    }

    private function resolveLegacyCompanyId(Company $company): ?int
    {
        $id = $company->getAttribute('id');
        if (is_numeric($id)) {
            return (int) $id;
        }

        $uuid = (string) ($company->getAttribute('uuid') ?? '');
        if ($uuid === '' || ! Schema::hasColumn($company->getTable(), 'uuid')) {
            return null;
        }

        $resolved = Company::query()->where('uuid', $uuid)->value('id');

        return is_numeric($resolved) ? (int) $resolved : null;
    }

    /**
     * @return array{company?: Company, role?: string, error?: string}
     */
    private function resolveRequestedCompanyForLogin(Request $request, User $user): array
    {
        $companyCode = trim((string) $request->input('companyCode', ''));
        if ($companyCode === '') {
            if ($this->requiresCompanyModeLogin($user)) {
                return ['error' => 'AUTH_COMPANY_MODE_REQUIRED'];
            }

            return [];
        }

        $company = Company::query()->where('code', $companyCode)->first();
        if (! $company) {
            return ['error' => 'TENANT_FORBIDDEN'];
        }

        $membership = CompanyUser::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            return ['error' => 'TENANT_FORBIDDEN'];
        }

        return [
            'company' => $company,
            'role' => (string) $membership->role,
        ];
    }

    private function requiresCompanyModeLogin(User $user): bool
    {
        if ($user->isGlobalHcmAdmin()) {
            return false;
        }

        if (! Schema::hasTable('company_users')) {
            return false;
        }

        return CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
    }
}
