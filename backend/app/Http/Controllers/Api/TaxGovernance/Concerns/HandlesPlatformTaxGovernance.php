<?php

namespace App\Http\Controllers\Api\TaxGovernance\Concerns;

use App\Models\Company;
use App\Models\HcmBillingTaxPolicy;
use App\Services\BillingTaxCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait HandlesPlatformTaxGovernance
{
    public function platformBillingPolicies(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.policy.view')) {
            return $response;
        }

        if (! ($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $validated = $request->validate([
            'billing_month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive'])],
            'global_mode' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HcmBillingTaxPolicy::query()->with('company')->orderByDesc('effective_from')->orderByDesc('created_at');

        if (! empty($validated['billing_month'])) {
            $query->where('billing_month', $validated['billing_month']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $globalSourceQuery = clone $query;
        $rows = $query->paginate((int) ($validated['per_page'] ?? 20));

        $rawItems = collect($rows->items());
        $globalMode = (bool) ($validated['global_mode'] ?? false);
        $globalSourceItems = $globalMode ? $globalSourceQuery->get() : $rawItems;
        $globalItems = $this->buildGlobalPlatformPolicyItems($globalSourceItems);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $rawItems->map(fn (HcmBillingTaxPolicy $policy): array => [
                    'id' => $policy->id,
                    'company_id' => $policy->company_id,
                    'company_name' => optional($policy->company)->name,
                    'billing_month' => $policy->billing_month,
                    'billing_cycle_type' => $policy->billing_cycle_type,
                    'tax_rate_percentage' => (float) $policy->tax_rate_percentage,
                    'base_calculation_method' => $policy->base_calculation_method,
                    'effective_from' => optional($policy->effective_from)?->toDateString(),
                    'effective_to' => optional($policy->effective_to)?->toDateString(),
                    'status' => $policy->status,
                    'notes' => $policy->notes,
                    'created_at' => optional($policy->created_at)?->toIso8601String(),
                ])->values(),
                'items_global' => $globalItems,
                'view_mode' => $globalMode ? 'global' : 'company',
                'meta' => [
                    'page' => $rows->currentPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                    'items_global_total' => count($globalItems),
                ],
            ],
        ]);
    }

    public function storePlatformBillingPolicy(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.policy.manage')) {
            return $response;
        }

        if (! ($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $isGlobalPayload = $request->hasAny(['subscription_tax_rate', 'addon_markup_rate']);

        if ($isGlobalPayload) {
            $validated = $request->validate([
                'subscription_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'addon_markup_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'billing_cycle_type' => ['nullable', Rule::in(['monthly', 'yearly', 'custom'])],
                'billing_month' => ['nullable', 'date_format:Y-m'],
                'effective_from' => ['nullable', 'date'],
                'status' => ['nullable', Rule::in(['draft', 'active', 'inactive'])],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $billingCycleType = (string) ($validated['billing_cycle_type'] ?? 'monthly');

            $service = app(BillingTaxCalculationService::class);
            if (! $service->validateBillingTaxPolicy([
                'tax_rate_percentage' => $validated['subscription_tax_rate'],
                'billing_cycle_type' => $billingCycleType,
                'base_calculation_method' => 'invoice_amount_due',
            ])) {
                return $this->errorResponse('BILLING_TAX_POLICY_INVALID', 'Billing tax policy validation failed.', 422);
            }

            $actorId = (int) ($request->user()?->id ?? 0) ?: null;
            $billingMonth = (string) ($validated['billing_month'] ?? now()->format('Y-m'));
            $effectiveFrom = (string) ($validated['effective_from'] ?? now()->toDateString());
            $status = (string) ($validated['status'] ?? 'active');

            $companyIds = Company::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->values();
            if ($companyIds->isEmpty()) {
                return $this->errorResponse('COMPANY_NOT_FOUND', 'No company available for global policy propagation.', 422);
            }

            $globalRates = [
                'subscription_tax_rate' => (float) $validated['subscription_tax_rate'],
                'addon_markup_rate' => (float) $validated['addon_markup_rate'],
            ];
            $propagationKey = (string) Str::uuid();

            $policySource = (string) $request->input('policy_source', 'global_platform_policy');
            $policyDomain = (string) $request->input('policy_domain', 'platform_billing');

            $notesPayload = [
                'global_rates' => $globalRates,
                'notes' => $validated['notes'] ?? null,
                'source' => $policySource,
                'domain' => $policyDomain,
                'propagation_key' => $propagationKey,
            ];

            DB::transaction(function () use ($companyIds, $billingMonth, $billingCycleType, $effectiveFrom, $status, $globalRates, $notesPayload, $actorId): void {
                foreach ($companyIds as $companyId) {
                    Company::query()->whereKey($companyId)->lockForUpdate()->first();

                    if ($status === 'active') {
                        HcmBillingTaxPolicy::query()
                            ->where('company_id', $companyId)
                            ->where('billing_month', $billingMonth)
                            ->where('status', 'active')
                            ->lockForUpdate()
                            ->update([
                                'status' => 'inactive',
                                'updated_by_user_id' => $actorId,
                                'updated_at' => now(),
                            ]);
                    }

                    $existing = HcmBillingTaxPolicy::query()
                        ->where('company_id', $companyId)
                        ->where('billing_month', $billingMonth)
                        ->where('billing_cycle_type', $billingCycleType)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        $existing->fill([
                            'tax_rate_percentage' => $globalRates['subscription_tax_rate'],
                            'base_calculation_method' => 'invoice_amount_due',
                            'effective_from' => $effectiveFrom,
                            'effective_to' => null,
                            'status' => $status,
                            'notes' => json_encode($notesPayload, JSON_UNESCAPED_UNICODE),
                            'updated_by_user_id' => $actorId,
                        ]);
                        $existing->save();
                    } else {
                        HcmBillingTaxPolicy::query()->create([
                            'id' => (string) Str::uuid(),
                            'company_id' => $companyId,
                            'billing_month' => $billingMonth,
                            'billing_cycle_type' => $billingCycleType,
                            'tax_rate_percentage' => $globalRates['subscription_tax_rate'],
                            'base_calculation_method' => 'invoice_amount_due',
                            'effective_from' => $effectiveFrom,
                            'effective_to' => null,
                            'status' => $status,
                            'notes' => json_encode($notesPayload, JSON_UNESCAPED_UNICODE),
                            'created_by_user_id' => $actorId,
                            'updated_by_user_id' => $actorId,
                        ]);
                    }

                    if ($status === 'active') {
                        $activeCount = HcmBillingTaxPolicy::query()
                            ->where('company_id', $companyId)
                            ->where('billing_month', $billingMonth)
                            ->where('status', 'active')
                            ->count();

                        if ($activeCount > 1) {
                            throw new \RuntimeException('Detected conflicting active billing policies for the same company and month.');
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'version' => 'v'.now()->format('YmdHis'),
                    'billing_month' => $billingMonth,
                    'billing_cycle_type' => $billingCycleType,
                    'effective_from' => $effectiveFrom,
                    'status' => $status,
                    'subscription_tax_rate' => $globalRates['subscription_tax_rate'],
                    'addon_markup_rate' => $globalRates['addon_markup_rate'],
                    'affected_company_count' => $companyIds->count(),
                    'notes' => $validated['notes'] ?? null,
                ],
            ], 201);
        }

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'billing_month' => ['required', 'date_format:Y-m'],
            'billing_cycle_type' => ['required', Rule::in(['monthly', 'yearly', 'custom'])],
            'tax_rate_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'base_calculation_method' => ['required', Rule::in(['invoice_amount_due'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service = app(BillingTaxCalculationService::class);
        if (! $service->validateBillingTaxPolicy($validated)) {
            return $this->errorResponse('BILLING_TAX_POLICY_INVALID', 'Billing tax policy validation failed.', 422);
        }

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        $policy = HcmBillingTaxPolicy::query()
            ->where('company_id', (int) $validated['company_id'])
            ->where('billing_month', $validated['billing_month'])
            ->where('billing_cycle_type', $validated['billing_cycle_type'])
            ->first();

        if (! $policy) {
            $policy = new HcmBillingTaxPolicy;
            $policy->id = (string) Str::uuid();
            $policy->company_id = (int) $validated['company_id'];
            $policy->billing_month = $validated['billing_month'];
            $policy->billing_cycle_type = $validated['billing_cycle_type'];
            $policy->created_by_user_id = $actorId;
        }

        $policy->tax_rate_percentage = $validated['tax_rate_percentage'];
        $policy->base_calculation_method = $validated['base_calculation_method'];
        $policy->effective_from = $validated['effective_from'];
        $policy->effective_to = $validated['effective_to'] ?? null;
        $policy->status = $validated['status'] ?? 'active';
        $policy->notes = $validated['notes'] ?? null;
        $policy->updated_by_user_id = $actorId;
        $policy->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $policy->id,
                'company_id' => $policy->company_id,
                'billing_month' => $policy->billing_month,
                'billing_cycle_type' => $policy->billing_cycle_type,
                'tax_rate_percentage' => (float) $policy->tax_rate_percentage,
                'base_calculation_method' => $policy->base_calculation_method,
                'effective_from' => optional($policy->effective_from)?->toDateString(),
                'effective_to' => optional($policy->effective_to)?->toDateString(),
                'status' => $policy->status,
            ],
        ], 201);
    }

    public function platformBillingReports(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.report.view_all')) {
            return $response;
        }

        if (! ($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $report = app(BillingTaxCalculationService::class)->generateCrossTenantMonthlyReport($validated['month']);

        $tenantGlobal = collect($report['tenants'] ?? [])->map(function (array $item): array {
            return [
                'tenant' => $item['company_name'] ?? '-',
                'plan' => $item['plan_name'] ?? '-',
                'billing_month' => $item['billing_month'] ?? null,
                'billing_cycle_type' => $item['billing_cycle_type'] ?? null,
                'next_renewal_month' => $item['next_renewal_month'] ?? null,
                'subscription_revenue' => (float) ($item['subscription_revenue'] ?? 0),
                'addon_revenue' => (float) ($item['addon_revenue'] ?? 0),
                'gross_revenue' => (float) ($item['gross_revenue'] ?? 0),
                'tax_amount_due' => (float) ($item['tax_amount_due'] ?? 0),
                'net_revenue' => (float) ($item['net_revenue'] ?? 0),
                'company_id' => $item['company_id'] ?? null,
                'company_name' => $item['company_name'] ?? '-',
            ];
        })->values();

        $summary = $report['summary'] ?? [];
        $summaryGlobal = [
            'total_subscription_revenue' => (float) ($summary['total_subscription_revenue'] ?? 0),
            'total_addon_revenue' => (float) ($summary['total_addon_revenue'] ?? 0),
            'total_gross_revenue' => (float) ($summary['total_gross_revenue'] ?? 0),
            'total_tax_due' => (float) ($summary['total_tax_due'] ?? 0),
            'total_net_revenue' => (float) ($summary['total_net_revenue'] ?? 0),
            'effective_tax_rate' => (float) ($summary['effective_tax_rate'] ?? 0),
        ];

        return response()->json([
            'success' => true,
            'data' => array_merge($report, [
                'summary_global' => $summaryGlobal,
                'tenants_global' => $tenantGlobal,
            ]),
        ]);
    }

    public function platformTaxCompliancePolicies(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.policy.view')) {
            return $response;
        }

        if (! ($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $request->merge(['global_mode' => true]);
        $baseResponse = $this->platformBillingPolicies($request);
        $payload = $baseResponse->getData(true);

        if (isset($payload['data']['items_global']) && is_array($payload['data']['items_global'])) {
            $filteredItems = array_values(array_filter($payload['data']['items_global'], function (array $item): bool {
                return (string) ($item['source'] ?? '') === 'government_tax_compliance_policy';
            }));

            $activeByMonth = [];
            foreach ($filteredItems as $item) {
                $status = strtolower((string) ($item['status'] ?? ''));
                if ($status !== 'active') {
                    continue;
                }

                $month = (string) ($item['billing_month'] ?? 'unknown');
                if (! isset($activeByMonth[$month])) {
                    $activeByMonth[$month] = (string) ($item['version'] ?? '');
                }
            }

            $payload['data']['items_global'] = array_map(function (array $item) use ($activeByMonth): array {
                $item['government_tax_rate'] = (float) ($item['subscription_tax_rate'] ?? $item['tax_rate_percentage'] ?? 0);
                $item['addon_component_rate'] = (float) ($item['addon_markup_rate'] ?? 0);

                $notesDecoded = json_decode((string) ($item['notes'] ?? ''), true);
                $transactionTaxRate = 0.0;
                if (is_array($notesDecoded)) {
                    $notesPayload = $notesDecoded;
                    if (! isset($notesPayload['transaction_tax'])) {
                        $rawNotes = $notesDecoded['notes'] ?? null;
                        $notesPayload = is_array($rawNotes)
                            ? $rawNotes
                            : (is_string($rawNotes) ? json_decode($rawNotes, true) : []);
                    }

                    if (is_array($notesPayload)) {
                        $transactionTaxRate = (float) ($notesPayload['transaction_tax']['tax_rate'] ?? 0);
                    }
                }
                $item['transaction_tax_rate'] = max(0.0, min(100.0, $transactionTaxRate));

                $month = (string) ($item['billing_month'] ?? 'unknown');
                $currentActiveVersion = (string) ($activeByMonth[$month] ?? '');
                $item['is_current_active_rule'] = $currentActiveVersion !== '' && $currentActiveVersion === (string) ($item['version'] ?? '');

                return $item;
            }, $filteredItems);
        }

        $payload['data']['view_context'] = 'government_tax_compliance';

        return response()->json($payload, $baseResponse->getStatusCode());
    }

    public function storePlatformTaxCompliancePolicy(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.policy.manage')) {
            return $response;
        }

        if (! ($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $request->merge([
            'global_mode' => true,
            'policy_source' => 'government_tax_compliance_policy',
            'policy_domain' => 'platform_tax_compliance',
        ]);

        return $this->storePlatformBillingPolicy($request);
    }

    public function platformTaxComplianceReports(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.report.view_all')) {
            return $response;
        }

        if (! ($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $baseResponse = $this->platformBillingReports($request);
        $payload = $baseResponse->getData(true);
        $selectedMonth = (string) $request->input('month', now()->format('Y-m'));
        $compliancePolicyConfigured = $this->hasGovernmentCompliancePolicyForMonth($selectedMonth);
        $policySnapshotsByCompany = $compliancePolicyConfigured
            ? $this->governmentCompliancePolicySnapshotsForMonth($selectedMonth)
            : [];
        $invoiceSnapshots = isset($payload['data']['invoice_snapshots']) && is_array($payload['data']['invoice_snapshots'])
            ? $payload['data']['invoice_snapshots']
            : [];
        $liabilityByCompany = [];

        if ($compliancePolicyConfigured && $invoiceSnapshots !== []) {
            foreach ($invoiceSnapshots as $invoice) {
                if (! is_array($invoice)) {
                    continue;
                }

                $companyId = (int) ($invoice['company_id'] ?? 0);
                if ($companyId <= 0) {
                    continue;
                }

                $amountDue = (float) ($invoice['amount_due'] ?? 0);
                if ($amountDue <= 0) {
                    continue;
                }

                $issueDate = is_string($invoice['issue_date'] ?? null) ? $invoice['issue_date'] : null;
                $rate = $this->resolveGovernmentTransactionTaxRateForInvoice($companyId, $issueDate, $policySnapshotsByCompany);
                $liability = round($amountDue * ($rate / 100), 2);
                $liabilityByCompany[$companyId] = round(($liabilityByCompany[$companyId] ?? 0.0) + $liability, 2);
            }
        }

        if (isset($payload['data']['tenants_global']) && is_array($payload['data']['tenants_global'])) {
            $payload['data']['tenants_global'] = array_map(function (array $item) use ($liabilityByCompany): array {
                $companyId = (int) ($item['company_id'] ?? 0);
                $item['collected_tax_liability'] = (float) ($liabilityByCompany[$companyId] ?? 0.0);

                return $item;
            }, $payload['data']['tenants_global']);

            $payload['data']['summary_global']['total_collected_tax_liability'] = round(
                array_reduce($payload['data']['tenants_global'], function (float $carry, array $item): float {
                    return $carry + (float) ($item['collected_tax_liability'] ?? 0);
                }, 0.0),
                2
            );
        }

        if (! $compliancePolicyConfigured) {
            $summaryGlobal = $payload['data']['summary_global'] ?? [];
            $grossRevenue = (float) ($summaryGlobal['total_gross_revenue'] ?? 0);

            $payload['data']['summary_global']['total_tax_due'] = 0.0;
            $payload['data']['summary_global']['total_collected_tax_liability'] = 0.0;
            $payload['data']['summary_global']['total_net_revenue'] = $grossRevenue;
            $payload['data']['summary_global']['effective_tax_rate'] = 0.0;

            if (isset($payload['data']['tenants_global']) && is_array($payload['data']['tenants_global'])) {
                $payload['data']['tenants_global'] = array_map(function (array $item): array {
                    $grossRevenue = (float) ($item['gross_revenue'] ?? 0);
                    $item['tax_amount_due'] = 0.0;
                    $item['collected_tax_liability'] = 0.0;
                    $item['net_revenue'] = $grossRevenue;

                    return $item;
                }, $payload['data']['tenants_global']);
            }
        }

        $summaryGlobal = $payload['data']['summary_global'] ?? [];
        $payload['data']['summary_compliance'] = [
            'total_taxable_revenue' => (float) ($summaryGlobal['total_gross_revenue'] ?? 0),
            'total_collected_tax_liability' => (float) ($summaryGlobal['total_collected_tax_liability'] ?? 0),
            'total_addon_component' => (float) ($summaryGlobal['total_addon_revenue'] ?? 0),
            'total_tax_payable' => (float) ($summaryGlobal['total_tax_due'] ?? 0),
            'total_net_revenue' => (float) ($summaryGlobal['total_net_revenue'] ?? 0),
            'effective_tax_rate' => (float) ($summaryGlobal['effective_tax_rate'] ?? 0),
        ];

        if (isset($payload['data']['tenants_global']) && is_array($payload['data']['tenants_global'])) {
            $payload['data']['tenants_compliance'] = array_map(function (array $item): array {
                return array_merge($item, [
                    'taxable_revenue' => (float) ($item['gross_revenue'] ?? $item['taxable_revenue_amount'] ?? 0),
                    'collected_tax_liability' => (float) ($item['collected_tax_liability'] ?? 0),
                    'addon_component' => (float) ($item['addon_revenue'] ?? 0),
                    'total_tax_payable' => (float) ($item['tax_amount_due'] ?? 0),
                ]);
            }, $payload['data']['tenants_global']);
        }

        $payload['data']['view_context'] = 'government_tax_compliance';
        $payload['data']['policy_configured'] = $compliancePolicyConfigured;

        return response()->json($payload, $baseResponse->getStatusCode());
    }

    public function platformBillingInvoices(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.report.export_all')) {
            return $response;
        }

        if (! ($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $report = app(BillingTaxCalculationService::class)->generateCrossTenantMonthlyReport($validated['month']);

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $validated['month'],
                'invoice_snapshots' => $report['invoice_snapshots'] ?? [],
            ],
        ]);
    }

    /**
     * @param  Collection<int, HcmBillingTaxPolicy>  $policies
     * @return array<int, array<string, mixed>>
     */
    private function buildGlobalPlatformPolicyItems($policies): array
    {
        $items = [];
        $seen = [];

        foreach ($policies as $policy) {
            $rates = $this->extractGlobalRatesFromPolicy($policy);
            $key = (string) ($rates['propagation_key'] ?? '');
            if ($key === '') {
                $key = implode('|', [
                    (string) ($policy->billing_month ?? ''),
                    (string) $rates['subscription_tax_rate'],
                    (string) $rates['addon_markup_rate'],
                    (string) ($policy->billing_cycle_type ?? ''),
                    (string) $rates['source'],
                    (string) $rates['domain'],
                    (string) $policy->status,
                    (string) optional($policy->effective_from)?->toDateString(),
                    (string) optional($policy->created_at)?->toDateTimeString(),
                ]);
            }

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $items[] = [
                'version' => 'v'.optional($policy->created_at)->format('YmdHis'),
                'billing_month' => (string) ($policy->billing_month ?? ''),
                'billing_cycle_type' => (string) ($policy->billing_cycle_type ?? ''),
                'subscription_tax_rate' => $rates['subscription_tax_rate'],
                'addon_markup_rate' => $rates['addon_markup_rate'],
                'source' => $rates['source'],
                'domain' => $rates['domain'],
                'status' => $policy->status,
                'created_at' => optional($policy->created_at)?->toIso8601String(),
                'effective_from' => optional($policy->effective_from)?->toDateString(),
                'notes' => $rates['notes'],
            ];
        }

        return $items;
    }

    /**
     * @return array{subscription_tax_rate: float, addon_markup_rate: float, notes: string, source: string, domain: string, propagation_key: string}
     */
    private function extractGlobalRatesFromPolicy(HcmBillingTaxPolicy $policy): array
    {
        $rawNotes = $policy->notes;
        $decoded = json_decode((string) $rawNotes, true);
        $globalRates = is_array($decoded) && isset($decoded['global_rates']) && is_array($decoded['global_rates'])
            ? $decoded['global_rates']
            : [];

        return [
            'subscription_tax_rate' => (float) ($globalRates['subscription_tax_rate'] ?? $policy->tax_rate_percentage ?? 0),
            'addon_markup_rate' => (float) ($globalRates['addon_markup_rate'] ?? 0),
            'notes' => (string) ($decoded['notes'] ?? (string) ($rawNotes ?? '')),
            'source' => (string) ($decoded['source'] ?? 'global_platform_policy'),
            'domain' => (string) ($decoded['domain'] ?? 'platform_billing'),
            'propagation_key' => (string) ($decoded['propagation_key'] ?? ''),
        ];
    }

    private function hasGovernmentCompliancePolicyForMonth(string $month): bool
    {
        return HcmBillingTaxPolicy::query()
            ->where('billing_month', $month)
            ->where('status', 'active')
            ->where('notes', 'like', '%government_tax_compliance_policy%')
            ->exists();
    }

    /**
     * @return array<int, list<array{effective_from:?string, created_at:?string, transaction_tax_rate:float}>>
     */
    private function governmentCompliancePolicySnapshotsForMonth(string $month): array
    {
        $policies = HcmBillingTaxPolicy::query()
            ->where('billing_month', $month)
            ->where('status', 'active')
            ->where('notes', 'like', '%government_tax_compliance_policy%')
            ->orderBy('company_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->get();

        $snapshots = [];
        foreach ($policies as $policy) {
            $companyId = (int) $policy->company_id;
            if ($companyId <= 0) {
                continue;
            }

            if (! isset($snapshots[$companyId])) {
                $snapshots[$companyId] = [];
            }

            $snapshots[$companyId][] = [
                'effective_from' => optional($policy->effective_from)?->toDateString(),
                'created_at' => optional($policy->created_at)?->toIso8601String(),
                'transaction_tax_rate' => $this->extractGovernmentTransactionTaxRate($policy),
            ];
        }

        return $snapshots;
    }

    /**
     * @param  array<int, list<array{effective_from:?string, created_at:?string, transaction_tax_rate:float}>>  $policySnapshotsByCompany
     */
    private function resolveGovernmentTransactionTaxRateForInvoice(int $companyId, ?string $issueDate, array $policySnapshotsByCompany): float
    {
        $rows = $policySnapshotsByCompany[$companyId] ?? [];
        if ($rows === []) {
            return 0.0;
        }

        $targetTimestamp = $issueDate ? strtotime($issueDate) : false;
        if ($targetTimestamp === false) {
            return max(0.0, min(100.0, (float) ($rows[0]['transaction_tax_rate'] ?? 0.0)));
        }

        foreach ($rows as $row) {
            $effectiveTs = isset($row['effective_from']) && is_string($row['effective_from'])
                ? strtotime($row['effective_from'])
                : false;

            if ($effectiveTs === false || $effectiveTs <= $targetTimestamp) {
                return max(0.0, min(100.0, (float) ($row['transaction_tax_rate'] ?? 0.0)));
            }
        }

        return max(0.0, min(100.0, (float) ($rows[0]['transaction_tax_rate'] ?? 0.0)));
    }

    private function extractGovernmentTransactionTaxRate(HcmBillingTaxPolicy $policy): float
    {
        $decoded = json_decode((string) ($policy->notes ?? ''), true);
        if (! is_array($decoded)) {
            return 0.0;
        }

        $rawNotes = $decoded['notes'] ?? null;
        $notesPayload = is_array($rawNotes)
            ? $rawNotes
            : (is_string($rawNotes) ? json_decode($rawNotes, true) : null);

        $rate = is_array($notesPayload)
            ? (float) ($notesPayload['transaction_tax']['tax_rate'] ?? 0)
            : 0.0;

        return max(0.0, min(100.0, $rate));
    }
}
