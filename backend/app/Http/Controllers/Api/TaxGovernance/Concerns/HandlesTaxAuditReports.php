<?php

namespace App\Http\Controllers\Api\TaxGovernance\Concerns;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmTaxGovernanceAnomaly;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\HcmTaxGovernancePolicyEvent;
use App\Models\HcmTaxGovernanceProjection;
use App\Services\BillingTaxCalculationService;
use App\Services\TaxGovernanceReportingService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

trait HandlesTaxAuditReports
{
    public function tenantSelfAuditReport(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.report.export')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $request->validate([
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'format' => ['required', Rule::in(['json', 'csv', 'xlsx', 'pdf'])],
        ]);

        $summary = [
            'totalPolicies' => HcmTaxGovernancePolicy::query()->where('company_id', $companyId)->count(),
            'publishedPolicies' => HcmTaxGovernancePolicy::query()
                ->where('company_id', $companyId)
                ->where('status', HcmTaxGovernancePolicy::STATUS_PUBLISHED)
                ->count(),
            'eventsCount' => HcmTaxGovernancePolicyEvent::query()->where('company_id', $companyId)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'reportType' => 'tenant_self_audit',
                'generatedAt' => now()->toIso8601String(),
                'periodYear' => (int) $validated['period_year'],
                'periodMonth' => (int) $validated['period_month'],
                'format' => $validated['format'],
                'summary' => $summary,
            ],
        ]);
    }

    public function dashboardSummary(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.dashboard.view_all')) {
            return $response;
        }

        $validated = $request->validate([
            'risk_level_filter' => ['nullable', Rule::in(['green', 'yellow', 'red'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = HcmTaxGovernanceProjection::query()
            ->with(['company:id,name', 'lastActor:id,email'])
            ->orderByDesc('updated_at');

        if (! empty($validated['risk_level_filter'])) {
            $query->where('tenant_risk_level', $validated['risk_level_filter']);
        }

        $projections = $query->paginate($perPage);
        $companyIds = collect($projections->items())
            ->pluck('company_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $anomalyCountByCompany = HcmTaxGovernanceAnomaly::query()
            ->select('company_id')
            ->selectRaw('COUNT(*) as anomaly_count')
            ->selectRaw("SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_anomaly_count")
            ->whereNull('resolved_at')
            ->whereIn('company_id', $companyIds)
            ->groupBy('company_id')
            ->get()
            ->keyBy('company_id');

        // Build summary metrics
        $allProjections = HcmTaxGovernanceProjection::query()->get();
        $summary = [
            'total_tenants' => $allProjections->count(),
            'tenants_with_published_policy' => $allProjections->where('status', 'published')->count(),
            'tenants_with_draft_only' => $allProjections->where('status', 'draft')->count(),
            'tenants_with_no_policy' => 0, // Would query tenants without any projection
            'total_anomalies' => HcmTaxGovernanceAnomaly::whereNull('resolved_at')->count(),
            'critical_anomalies' => HcmTaxGovernanceAnomaly::whereNull('resolved_at')->where('severity', 'critical')->count(),
        ];

        $riskHeatmap = [
            'green' => $allProjections->where('tenant_risk_level', 'green')->count(),
            'yellow' => $allProjections->where('tenant_risk_level', 'yellow')->count(),
            'red' => $allProjections->where('tenant_risk_level', 'red')->count(),
        ];

        $billingMonth = now()->format('Y-m');
        $billingService = app(BillingTaxCalculationService::class);
        $billingReport = $billingService->generateCrossTenantMonthlyReport($billingMonth);
        $billingTaxHealth = [
            'billing_month' => $billingMonth,
            'tenant_count_with_policy' => (int) ($billingReport['summary']['tenant_count_with_policy'] ?? 0),
            'total_tax_due' => (float) ($billingReport['summary']['total_tax_due'] ?? 0),
            'total_invoice_amount' => (float) ($billingReport['summary']['total_invoice_amount'] ?? 0),
            'unpaid_invoice_count' => (int) ($billingReport['summary']['unpaid_invoice_count'] ?? 0),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'risk_heatmap' => $riskHeatmap,
                'billing_tax_health' => $billingTaxHealth,
                'tenants' => collect($projections->items())->map(function (HcmTaxGovernanceProjection $proj) use ($anomalyCountByCompany) {
                    $anomalyCounts = $anomalyCountByCompany->get($proj->company_id);

                    return [
                        'company_id' => $proj->company_id,
                        'company_name' => optional($proj->company)->name ?? 'Unknown',
                        'latest_policy_status' => $proj->status,
                        'latest_policy_version' => $proj->version,
                        'effective_since' => optional($proj->effective_date)?->toDateString(),
                        'policy_complexity_score' => $proj->policy_complexity_score,
                        'risk_level' => $proj->tenant_risk_level,
                        'anomaly_count' => (int) ($anomalyCounts->anomaly_count ?? 0),
                        'critical_anomaly_count' => (int) ($anomalyCounts->critical_anomaly_count ?? 0),
                        'last_change_at' => optional($proj->last_actor_timestamp)?->toIso8601String(),
                        'last_change_by' => optional($proj->lastActor)->email ?? 'System',
                    ];
                })->values(),
                'meta' => [
                    'page' => $projections->currentPage(),
                    'per_page' => $projections->perPage(),
                    'total' => $projections->total(),
                ],
            ],
        ]);
    }

    public function tenantSelfAuditReportEnhanced(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.view')) {
            return $response;
        }

        $companyId = $request->input('company_id');
        $userCompanyId = $this->activeCompanyId($request);

        // Authorization: tenant user can only view own tenant; global admin can view any
        $isGlobalAdmin = (bool) ($request->user()?->isGlobalHcmAdmin() ?? false);
        if (! $isGlobalAdmin && $companyId && (int) $companyId !== (int) $userCompanyId) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Cannot view other tenant self-audit report.', 403);
        }

        if (! $companyId && $userCompanyId) {
            $companyId = $userCompanyId;
        }

        if (! $companyId) {
            return $this->errorResponse('TENANT_REQUIRED', 'Company context is required.', 400);
        }

        $this->ensureDefaultTenantPolicyTemplate((int) $companyId, (int) ($request->user()?->id ?? 0) ?: null);

        $company = Company::find((int) $companyId);
        if (! $company) {
            return $this->errorResponse('COMPANY_NOT_FOUND', 'Company not found.', 404);
        }

        $validated = $request->validate([
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
        ]);

        $periodStart = ! empty($validated['period_start']) ? Carbon::parse($validated['period_start']) : now()->subDays(90);
        $periodEnd = ! empty($validated['period_end']) ? Carbon::parse($validated['period_end']) : now();

        $policies = HcmTaxGovernancePolicy::where('company_id', (int) $companyId)->get();
        $currentPublishedPolicy = $policies->where('status', 'published')->first();

        // Build change history from events
        $events = HcmTaxGovernancePolicyEvent::where('company_id', (int) $companyId)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $changeHistory = collect($events)
            ->map(function (HcmTaxGovernancePolicyEvent $event) {
                return [
                    'event_type' => $event->event_type,
                    'created_at' => optional($event->created_at)->toIso8601String(),
                    'created_by' => optional($event->actor)->email ?? 'System',
                    'note' => $event->note,
                ];
            })
            ->values();

        // Compute compliance checklist
        // AN-011: Replaced hardcoded all_payroll_runs_covered=true with real payroll query
        $payrollRunsInPeriod = 0;
        $payrollRunsUsingPolicy = 0;
        $allPayrollRunsCovered = true;
        if (Schema::hasTable('hcm_payroll_runs')) {
            $payrollRuns = DB::table('hcm_payroll_runs')
                ->where('company_id', (int) $companyId)
                ->whereBetween('finalized_at', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()])
                ->where('status', 'finalized')
                ->select('id', 'hcm_tax_governance_policy_id')
                ->get();
            $payrollRunsInPeriod = $payrollRuns->count();
            $payrollRunsUsingPolicy = $payrollRuns->whereNotNull('hcm_tax_governance_policy_id')->count();
            $allPayrollRunsCovered = $payrollRunsInPeriod === 0 || $payrollRunsUsingPolicy === $payrollRunsInPeriod;
        }

        $complianceChecklist = [
            'has_published_policy' => (bool) $currentPublishedPolicy,
            'has_recent_publication' => $currentPublishedPolicy && $currentPublishedPolicy->published_at && $currentPublishedPolicy->published_at->diffInDays(now()) < 90,
            'all_payroll_runs_covered' => $allPayrollRunsCovered,
            'no_unresolved_anomalies' => HcmTaxGovernanceAnomaly::where('company_id', (int) $companyId)->whereNull('resolved_at')->count() === 0,
        ];

        $billingService = app(BillingTaxCalculationService::class);
        $billingTaxCompliance = $billingService->calculateBillingTax((int) $companyId, now()->format('Y-m'));

        return response()->json([
            'success' => true,
            'data' => [
                'company_id' => $companyId,
                'company_name' => $company->name,
                'period' => [
                    'start' => $periodStart->toDateString(),
                    'end' => $periodEnd->toDateString(),
                ],
                'policy_snapshot' => [
                    'current_published_version' => $currentPublishedPolicy?->version,
                    'effective_date' => optional($currentPublishedPolicy?->effective_start_date)?->toDateString(),
                    'policy_summary' => [
                        'policy_code' => $currentPublishedPolicy?->policy_code,
                        'name' => $currentPublishedPolicy?->name,
                        'rules_count' => count($currentPublishedPolicy?->rules ?? []),
                    ],
                ],
                'change_history' => $changeHistory,
                'payroll_impact' => [
                    'payroll_runs_in_period' => $payrollRunsInPeriod,
                    'payroll_runs_using_published_policy' => $payrollRunsUsingPolicy,
                    'anomalies_in_period' => [],
                ],
                'compliance_checklist' => $complianceChecklist,
                'billing_tax_compliance' => [
                    'billing_month' => $billingTaxCompliance['billing_month'] ?? now()->format('Y-m'),
                    'policy_uuid' => $billingTaxCompliance['policy_uuid'] ?? null,
                    'invoice_count' => (int) ($billingTaxCompliance['invoice_count'] ?? 0),
                    'paid_invoice_count' => (int) ($billingTaxCompliance['paid_invoice_count'] ?? 0),
                    'unpaid_invoice_count' => (int) ($billingTaxCompliance['unpaid_invoice_count'] ?? 0),
                    'total_invoice_amount' => (float) ($billingTaxCompliance['total_invoice_amount'] ?? 0),
                    'taxable_revenue_amount' => (float) ($billingTaxCompliance['taxable_revenue_amount'] ?? 0),
                    'cleared_revenue_amount' => (float) ($billingTaxCompliance['cleared_revenue_amount'] ?? 0),
                    'uncleared_revenue_amount' => (float) ($billingTaxCompliance['uncleared_revenue_amount'] ?? 0),
                    'disputed_revenue_amount' => (float) ($billingTaxCompliance['disputed_revenue_amount'] ?? 0),
                    'reversed_revenue_amount' => (float) ($billingTaxCompliance['reversed_revenue_amount'] ?? 0),
                    'tax_amount_due' => (float) ($billingTaxCompliance['tax_amount'] ?? 0),
                    'tax_rate_percentage' => (float) ($billingTaxCompliance['tax_rate_percentage'] ?? 0),
                ],
            ],
        ]);
    }

    public function tenantComplianceStatus(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.report.export')) {
            return $response;
        }

        $companyId = $request->input('company_id');
        $userCompanyId = $this->activeCompanyId($request);
        $isGlobalAdmin = (bool) ($request->user()?->isGlobalHcmAdmin() ?? false);

        if (! $isGlobalAdmin && $companyId && (int) $companyId !== (int) $userCompanyId) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Cannot view other tenant compliance status.', 403);
        }

        if (! $companyId && $userCompanyId) {
            $companyId = $userCompanyId;
        }

        if (! $companyId) {
            return $this->errorResponse('TENANT_REQUIRED', 'Company context is required.', 400);
        }

        $this->ensureDefaultTenantPolicyTemplate((int) $companyId, (int) ($request->user()?->id ?? 0) ?: null);

        $company = Company::find((int) $companyId);
        if (! $company) {
            return $this->errorResponse('COMPANY_NOT_FOUND', 'Company not found.', 404);
        }

        $currentPolicy = HcmTaxGovernancePolicy::query()
            ->where('company_id', (int) $companyId)
            ->where('status', HcmTaxGovernancePolicy::STATUS_PUBLISHED)
            ->orderByDesc('version')
            ->first();

        $unresolvedAnomalies = HcmTaxGovernanceAnomaly::query()
            ->where('company_id', (int) $companyId)
            ->whereNull('resolved_at')
            ->count();

        $billingCompliance = app(BillingTaxCalculationService::class)
            ->calculateBillingTax((int) $companyId, now()->format('Y-m'));

        $employeePph21Compliance = $this->buildEmployeePph21ComplianceSnapshot((int) $companyId);

        $overallStatus = (
            $currentPolicy
            && $unresolvedAnomalies === 0
            && (int) ($billingCompliance['unpaid_invoice_count'] ?? 0) === 0
            && (int) ($employeePph21Compliance['missing_npwp'] ?? 0) === 0
            && (int) ($employeePph21Compliance['invalid_npwp_format'] ?? 0) === 0
            && (int) ($employeePph21Compliance['missing_ptkp_status'] ?? 0) === 0
        )
            ? 'compliant'
            : 'attention_required';

        $recommendedActions = [];
        if (! $currentPolicy) {
            $recommendedActions[] = [
                'priority' => 'high',
                'action' => 'Publish active statutory tax policy.',
                'target_date' => now()->addDays(7)->toDateString(),
            ];
        }
        if ($unresolvedAnomalies > 0) {
            $recommendedActions[] = [
                'priority' => 'high',
                'action' => 'Resolve unresolved tax governance anomalies.',
                'target_date' => now()->addDays(5)->toDateString(),
            ];
        }
        if ((int) ($billingCompliance['unpaid_invoice_count'] ?? 0) > 0) {
            $recommendedActions[] = [
                'priority' => 'medium',
                'action' => 'Reconcile unpaid billing tax invoices.',
                'target_date' => now()->addDays(10)->toDateString(),
            ];
        }
        if ((int) ($employeePph21Compliance['missing_npwp'] ?? 0) > 0) {
            $recommendedActions[] = [
                'priority' => 'high',
                'action' => 'Lengkapi NPWP untuk seluruh karyawan aktif yang wajib pajak.',
                'target_date' => now()->addDays(7)->toDateString(),
            ];
        }
        if ((int) ($employeePph21Compliance['invalid_npwp_format'] ?? 0) > 0) {
            $recommendedActions[] = [
                'priority' => 'high',
                'action' => 'Normalisasi format NPWP karyawan agar sesuai format numerik resmi.',
                'target_date' => now()->addDays(7)->toDateString(),
            ];
        }
        if ((int) ($employeePph21Compliance['missing_ptkp_status'] ?? 0) > 0) {
            $recommendedActions[] = [
                'priority' => 'high',
                'action' => 'Tetapkan status PTKP valid untuk seluruh karyawan aktif.',
                'target_date' => now()->addDays(7)->toDateString(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'company_id' => (int) $companyId,
                'company_name' => $company->name,
                'reporting_period' => now()->year.'-Q'.now()->quarter,
                'compliance_status' => [
                    'statutory_tax_compliance' => [
                        'has_active_policy' => (bool) $currentPolicy,
                        'policy_version' => $currentPolicy?->version,
                        'last_publication_date' => optional($currentPolicy?->published_at)?->toDateString(),
                        'anomalies_unresolved' => $unresolvedAnomalies,
                    ],
                    'billing_tax_compliance' => [
                        'billing_cycle_active' => ! empty($billingCompliance['policy_uuid']),
                        'invoices_issued' => ! empty($billingCompliance['policy_uuid']) ? (int) ($billingCompliance['unpaid_invoice_count'] ?? 0) : 0,
                        'invoices_paid' => ! empty($billingCompliance['policy_uuid']) ? (int) ($billingCompliance['paid_invoice_count'] ?? 0) : 0,
                        'amount_outstanding' => ! empty($billingCompliance['policy_uuid']) ? (float) ($billingCompliance['outstanding_invoice_amount'] ?? 0) : 0,
                        'taxable_revenue_amount' => (float) ($billingCompliance['taxable_revenue_amount'] ?? 0),
                        'cleared_revenue_amount' => (float) ($billingCompliance['cleared_revenue_amount'] ?? 0),
                        'uncleared_revenue_amount' => (float) ($billingCompliance['uncleared_revenue_amount'] ?? 0),
                        'disputed_revenue_amount' => (float) ($billingCompliance['disputed_revenue_amount'] ?? 0),
                        'reversed_revenue_amount' => (float) ($billingCompliance['reversed_revenue_amount'] ?? 0),
                        'payment_status' => ((int) ($billingCompliance['unpaid_invoice_count'] ?? 0) === 0) ? 'current' : 'overdue',
                    ],
                    'employee_pph21_compliance' => [
                        'active_employees' => (int) ($employeePph21Compliance['active_employees'] ?? 0),
                        'profiles_available' => (int) ($employeePph21Compliance['profiles_available'] ?? 0),
                        'complete_profiles' => (int) ($employeePph21Compliance['complete_profiles'] ?? 0),
                        'missing_npwp' => (int) ($employeePph21Compliance['missing_npwp'] ?? 0),
                        'invalid_npwp_format' => (int) ($employeePph21Compliance['invalid_npwp_format'] ?? 0),
                        'missing_ptkp_status' => (int) ($employeePph21Compliance['missing_ptkp_status'] ?? 0),
                        'completion_rate' => (float) ($employeePph21Compliance['completion_rate'] ?? 0),
                        'non_compliant_employees' => array_values((array) ($employeePph21Compliance['non_compliant_employees'] ?? [])),
                    ],
                    'overall_status' => $overallStatus,
                    'next_review_date' => now()->addMonth()->toDateString(),
                ],
                'recommended_actions' => $recommendedActions,
            ],
        ]);
    }

    /**
     * @return array<string, int|float|array<int, array<string, mixed>>>
     */
    private function buildEmployeePph21ComplianceSnapshot(int $companyId): array
    {
        $activeUserIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('role', '!=', 'owner')
            ->pluck('user_id');

        $activeUsers = User::query()
            ->whereIn('id', $activeUserIds)
            ->get(['id', 'uuid', 'name', 'email'])
            ->keyBy('id');

        $activeEmployeeCount = $activeUserIds->count();
        if ($activeEmployeeCount === 0) {
            return [
                'active_employees' => 0,
                'profiles_available' => 0,
                'complete_profiles' => 0,
                'missing_npwp' => 0,
                'invalid_npwp_format' => 0,
                'missing_ptkp_status' => 0,
                'completion_rate' => 0.0,
                'non_compliant_employees' => [],
            ];
        }

        $employeeProfiles = EmployeeProfile::query()
            ->whereIn('user_id', $activeUserIds)
            ->get(['id', 'user_id', 'marital_status']);

        $profileByUserId = $employeeProfiles->keyBy('user_id');
        $profileIds = $employeeProfiles->pluck('id');

        $latestTaxProfiles = EmployeeTaxProfile::query()
            ->whereIn('employee_id', $profileIds)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($items) => $items->first());

        $allowedPtkpStatuses = $this->allowedPtkpStatuses();
        $profilesAvailable = 0;
        $completeProfiles = 0;
        $missingNpwp = 0;
        $invalidNpwpFormat = 0;
        $missingPtkpStatus = 0;
        $nonCompliantEmployees = [];

        foreach ($activeUserIds as $userId) {
            $user = $activeUsers->get((int) $userId);
            $profile = $profileByUserId->get($userId);
            $issues = [];

            if (! $profile) {
                $missingNpwp++;
                $missingPtkpStatus++;
                $issues[] = ['code' => 'employee_profile_missing', 'label' => 'Profil karyawan belum tersedia.'];
                $issues[] = ['code' => 'npwp_missing', 'label' => 'NPWP belum diisi.'];
                $issues[] = ['code' => 'ptkp_status_missing', 'label' => 'Status PTKP belum diisi.'];

                $nonCompliantEmployees[] = [
                    'user_id' => (int) $userId,
                    'user_uuid' => $user?->uuid,
                    'full_name' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'issues' => $issues,
                ];

                continue;
            }

            $derivedPtkpStatus = $this->inferTaxStatusFromMaritalStatus($profile->marital_status);

            /** @var EmployeeTaxProfile|null $taxProfile */
            $taxProfile = $latestTaxProfiles->get((int) $profile->id);
            if (! $taxProfile) {
                $missingNpwp++;
                $issues[] = ['code' => 'npwp_missing', 'label' => 'NPWP belum diisi.'];
                if ($derivedPtkpStatus === null) {
                    $missingPtkpStatus++;
                    $issues[] = ['code' => 'ptkp_status_missing', 'label' => 'Status PTKP belum diisi.'];
                }

                $nonCompliantEmployees[] = [
                    'user_id' => (int) $userId,
                    'user_uuid' => $user?->uuid,
                    'full_name' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'issues' => $issues,
                ];

                continue;
            }

            $profilesAvailable++;

            $rawNpwp = trim((string) ($taxProfile->npwp ?? ''));
            $npwp = $this->normalizeNpwp($rawNpwp);
            $hasRawNpwp = $rawNpwp !== '';
            $npwpFormatValid = $hasRawNpwp && $this->isValidNpwpFormat($npwp);

            if (! $hasRawNpwp) {
                $missingNpwp++;
                $issues[] = ['code' => 'npwp_missing', 'label' => 'NPWP belum diisi.'];
            } elseif (! $npwpFormatValid) {
                $invalidNpwpFormat++;
                $issues[] = [
                    'code' => 'npwp_invalid_format',
                    'label' => 'Format NPWP tidak valid.',
                    'current_value' => $rawNpwp,
                ];
            }

            $ptkpStatus = strtoupper(trim((string) ($taxProfile->ptkp_status ?: $taxProfile->tax_status ?: $derivedPtkpStatus ?: '')));
            $ptkpValid = $ptkpStatus !== '' && in_array($ptkpStatus, $allowedPtkpStatuses, true);
            if (! $ptkpValid) {
                $missingPtkpStatus++;
                $issues[] = [
                    'code' => 'ptkp_status_missing',
                    'label' => 'Status PTKP belum valid.',
                    'current_value' => $ptkpStatus,
                ];
            }

            if ($npwpFormatValid && $ptkpValid) {
                $completeProfiles++;
            } else {
                $nonCompliantEmployees[] = [
                    'user_id' => (int) $userId,
                    'user_uuid' => $user?->uuid,
                    'full_name' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'issues' => $issues,
                ];
            }
        }

        $completionRate = $activeEmployeeCount > 0
            ? round(($completeProfiles / $activeEmployeeCount) * 100, 2)
            : 0;

        return [
            'active_employees' => $activeEmployeeCount,
            'profiles_available' => $profilesAvailable,
            'complete_profiles' => $completeProfiles,
            'missing_npwp' => $missingNpwp,
            'invalid_npwp_format' => $invalidNpwpFormat,
            'missing_ptkp_status' => $missingPtkpStatus,
            'completion_rate' => $completionRate,
            'non_compliant_employees' => array_slice($nonCompliantEmployees, 0, 100),
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedPtkpStatuses(): array
    {
        $statuses = (array) config('hcm.tax_statuses', ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3']);

        return array_values(array_unique(array_map(fn ($item): string => strtoupper(trim((string) $item)), $statuses)));
    }

    private function inferTaxStatusFromMaritalStatus(?string $maritalStatus): ?string
    {
        $normalized = strtolower(trim((string) $maritalStatus));

        return match ($normalized) {
            'married' => 'K0',
            'single', 'divorced', 'widowed' => 'TK0',
            default => null,
        };
    }

    private function buildStatutoryRules(string $regulationReference, string $regulationSourceType): array
    {
        return [
            'scheme' => 'STATUTORY_PPH21',
            'currency' => 'IDR',
            'country' => 'ID',
            'calculationMethod' => 'monthly_ter_lookup',
            'regulationReference' => $regulationReference,
            'regulationSourceType' => $regulationSourceType,
            'source' => 'tenant_statutory_policy',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDefaultStatutorySchedules(string $effectiveStartDate, ?string $effectiveEndDate, string $regulationReference, string $regulationSourceType): array
    {
        $schedules = [];
        foreach (['A', 'B', 'C'] as $category) {
            $schedules[] = [
                'category' => $category,
                'lookupTableCode' => $category,
                'calculationMode' => 'ter_lookup',
                'rate' => null,
                'upperBound' => null,
                'effectiveStartDate' => $effectiveStartDate,
                'effectiveEndDate' => $effectiveEndDate,
                'regulationReference' => $regulationReference,
                'regulationSourceType' => $regulationSourceType,
            ];
        }

        return $schedules;
    }

    public function tenantSelfAuditReportExport(Request $request): JsonResponse|Response
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.report.export')) {
            return $response;
        }

        $companyIdInput = $request->input('company_id');
        $companyId = is_numeric($companyIdInput) ? (int) $companyIdInput : null;
        $userCompanyId = $this->activeCompanyId($request);
        $format = $request->input('format', 'json'); // json or pdf

        // Authorization: tenant user can only export own tenant; global admin can export any
        $isGlobalAdmin = (bool) ($request->user()?->isGlobalHcmAdmin() ?? false);
        if (! $isGlobalAdmin && $companyId !== null && $companyId !== $userCompanyId) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Cannot export other tenant audit report.', 403);
        }

        if ($companyId === null && $userCompanyId) {
            $companyId = $userCompanyId;
        }

        if ($companyId === null) {
            return $this->errorResponse('TENANT_REQUIRED', 'Company context is required.', 400);
        }

        // Validate company exists
        $company = Company::find($companyId);
        if (! $company) {
            return $this->errorResponse('COMPANY_NOT_FOUND', 'Company not found.', 404);
        }

        $validated = $request->validate([
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
        ]);

        $periodStart = $validated['period_start'] ? Carbon::parse($validated['period_start']) : now()->subDays(90);
        $periodEnd = $validated['period_end'] ? Carbon::parse($validated['period_end']) : now();

        // Validate period is within last 2 years
        if ($periodStart->diffInYears($periodEnd) > 2) {
            return $this->errorResponse('INVALID_PERIOD', 'Period cannot exceed 2 years.', 422);
        }

        // Generate report data
        $reportService = app(TaxGovernanceReportingService::class);
        $reportData = $reportService->generateTenantSelfAuditReport($companyId, $periodStart, $periodEnd);

        if ($format === 'pdf') {
            $pdfBinary = $this->renderTenantSelfAuditPdf($reportData);

            return response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="tenant-self-audit-'.((string) $companyId).'-'.now()->format('Ymd-His').'.pdf"',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $reportData,
        ]);
    }

    private function renderTenantSelfAuditPdf(array $reportData): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);

        $companyName = (string) ($reportData['company_name'] ?? 'Unknown Company');
        $generatedAt = (string) ($reportData['report_generated_at'] ?? now()->toIso8601String());
        $periodStart = (string) ($reportData['period']['start'] ?? '-');
        $periodEnd = (string) ($reportData['period']['end'] ?? '-');
        $policyVersion = (string) ($reportData['policy_snapshot']['current_version'] ?? '-');
        $readinessScore = (string) ($reportData['compliance_checklist']['readiness_score'] ?? '-');
        $anomalyCount = (int) count($reportData['anomalies_detected'] ?? []);

        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;}h1{font-size:18px;}table{width:100%;border-collapse:collapse;}td,th{border:1px solid #ccc;padding:6px;vertical-align:top;}th{background:#f5f5f5;text-align:left;}</style></head><body>';
        $html .= '<h1>Tenant Self-Audit Report</h1>';
        $html .= '<p><strong>Company:</strong> '.e($companyName).'<br><strong>Generated At:</strong> '.e($generatedAt).'</p>';
        $html .= '<table><tr><th>Period Start</th><th>Period End</th><th>Current Policy Version</th><th>Readiness Score</th><th>Unresolved Anomalies</th></tr>';
        $html .= '<tr><td>'.e($periodStart).'</td><td>'.e($periodEnd).'</td><td>'.e($policyVersion).'</td><td>'.e($readinessScore).'</td><td>'.e((string) $anomalyCount).'</td></tr>';
        $html .= '</table>';
        $html .= '<p>Generated by Arkav Tax Governance Reporting.</p>';
        $html .= '</body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
