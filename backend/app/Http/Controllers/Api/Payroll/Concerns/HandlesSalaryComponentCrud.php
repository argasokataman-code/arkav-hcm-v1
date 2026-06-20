<?php

namespace App\Http\Controllers\Api\Payroll\Concerns;

use App\Models\EmployeeBenefit;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmBpjsGovernancePolicy;
use App\Models\HcmEmployeeAllowancePolicy;
use App\Models\HcmEmployeePayrollItemAssignment;
use App\Models\HcmPayrollItem;
use App\Models\HcmSalaryComponent;
use App\Models\HcmSalaryComponentCategory;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait HandlesSalaryComponentCrud
{
public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'kind' => ['nullable', 'string', Rule::in(['addition', 'deduction'])],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $query = $this->scopedComponentQuery($request);

        if (! empty($validated['kind'] ?? null)) {
            $query->where('kind', $validated['kind']);
        }
        if (array_key_exists('isActive', $validated)) {
            $query->where('is_active', (bool) $validated['isActive']);
        }

        $rows = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (HcmSalaryComponent $c) => $this->serialize($c))
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $c = $this->scopedComponentQuery($request)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($c),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'MANUAL_COMPONENT_CREATION_DISABLED',
                'message' => 'Penambahan komponen manual dinonaktifkan. Komponen gaji dikelola otomatis oleh modul governance.',
            ],
        ], 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'kind' => ['required', 'string', Rule::in(['addition', 'deduction'])],
            'category' => [
                'required',
                'string',
                Rule::in(HcmSalaryComponent::allCategoryCodes()),
            ],
            'includeBpjsHealthWageBase' => ['nullable', 'boolean'],
            'includeBpjsTkWageBase' => ['nullable', 'boolean'],
            'includeThrCalculationBase' => ['nullable', 'boolean'],
            'includePph21TerGross' => ['nullable', 'boolean'],
            'includePph21AnnualReconciliation' => ['nullable', 'boolean'],
            'taxTreatmentCode' => ['nullable', 'string', Rule::in(HcmSalaryComponent::TAX_TREATMENT_CODES)],
            'subjectOvertimeRegulation' => ['nullable', 'boolean'],
            'affectsNetPay' => ['nullable', 'boolean'],
            'employerCostLine' => ['nullable', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if (! HcmSalaryComponent::isValidCategoryForKind($validated['kind'], $validated['category'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'category does not match kind.',
                ],
            ], 422);
        }

        if ($this->hasDuplicateNameInKindCategory($validated['name'], $validated['kind'], $validated['category'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'name already exists for this kind and category.',
                ],
            ], 422);
        }

        if (! empty($validated['code']) && HcmSalaryComponent::query()->where('code', $validated['code'])->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'code already exists.',
                ],
            ], 422);
        }

        $code = $this->uniqueCode($validated['code'] ?? null, $validated['name']);
        $taxTreatmentCode = $this->resolveTaxTreatmentCodeFromValidated($validated);
        $taxFlags = HcmSalaryComponent::taxFlagsForTreatment($taxTreatmentCode);

        $c = HcmSalaryComponent::query()->create([
            'company_id' => $this->activeCompanyId($request),
            'code' => $code,
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'category' => $validated['category'],
            'include_bpjs_health_wage_base' => (bool) ($validated['includeBpjsHealthWageBase'] ?? false),
            'include_bpjs_tk_wage_base' => (bool) ($validated['includeBpjsTkWageBase'] ?? false),
            'include_thr_calculation_base' => (bool) ($validated['includeThrCalculationBase'] ?? false),
            'include_pph21_ter_gross' => $taxFlags['include_pph21_ter_gross'],
            'include_pph21_annual_reconciliation' => $taxFlags['include_pph21_annual_reconciliation'],
            'tax_treatment_code' => $taxTreatmentCode,
            'subject_overtime_regulation' => (bool) ($validated['subjectOvertimeRegulation'] ?? false),
            'affects_net_pay' => (bool) ($validated['affectsNetPay'] ?? true),
            'employer_cost_line' => (bool) ($validated['employerCostLine'] ?? false),
            'is_system_locked' => false,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $c->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $c = $this->scopedComponentQuery($request)->findOrFail($id);

        if ($c->is_system_locked) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SYSTEM_LOCKED',
                    'message' => 'Komponen ini dikunci oleh modul governance (' . ($c->source_module ?? 'system') . ') dan tidak dapat diubah secara manual. Gunakan modul governance yang bersangkutan untuk mengubah kebijakan terkait.',
                ],
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'kind' => ['required', 'string', Rule::in(['addition', 'deduction'])],
            'category' => [
                'required',
                'string',
                Rule::in(HcmSalaryComponent::allCategoryCodes()),
            ],
            'includeBpjsHealthWageBase' => ['required', 'boolean'],
            'includeBpjsTkWageBase' => ['required', 'boolean'],
            'includeThrCalculationBase' => ['required', 'boolean'],
            'includePph21TerGross' => ['required', 'boolean'],
            'includePph21AnnualReconciliation' => ['required', 'boolean'],
            'taxTreatmentCode' => ['nullable', 'string', Rule::in(HcmSalaryComponent::TAX_TREATMENT_CODES)],
            'subjectOvertimeRegulation' => ['required', 'boolean'],
            'affectsNetPay' => ['required', 'boolean'],
            'employerCostLine' => ['required', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if (! HcmSalaryComponent::isValidCategoryForKind($validated['kind'], $validated['category'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'category does not match kind.',
                ],
            ], 422);
        }

        if ($this->hasDuplicateNameInKindCategory($validated['name'], $validated['kind'], $validated['category'], $c->id)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'name already exists for this kind and category.',
                ],
            ], 422);
        }

        $code = $validated['code'];
        if ($code !== $c->code && HcmSalaryComponent::query()->where('code', $code)->whereKeyNot($c->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'code already exists.',
                ],
            ], 422);
        }

        $taxTreatmentCode = $this->resolveTaxTreatmentCodeFromValidated($validated);
        $taxFlags = HcmSalaryComponent::taxFlagsForTreatment($taxTreatmentCode);

        $c->update([
            'code' => $code,
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'category' => $validated['category'],
            'include_bpjs_health_wage_base' => $validated['includeBpjsHealthWageBase'],
            'include_bpjs_tk_wage_base' => $validated['includeBpjsTkWageBase'],
            'include_thr_calculation_base' => $validated['includeThrCalculationBase'],
            'include_pph21_ter_gross' => $taxFlags['include_pph21_ter_gross'],
            'include_pph21_annual_reconciliation' => $taxFlags['include_pph21_annual_reconciliation'],
            'tax_treatment_code' => $taxTreatmentCode,
            'subject_overtime_regulation' => $validated['subjectOvertimeRegulation'],
            'affects_net_pay' => $validated['affectsNetPay'],
            'employer_cost_line' => $validated['employerCostLine'],
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? $c->sort_order),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $c = $this->scopedComponentQuery($request)->findOrFail($id);

        if ($c->is_system_locked) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SYSTEM_LOCKED',
                    'message' => 'Komponen ini dikunci oleh modul governance (' . ($c->source_module ?? 'system') . ') dan tidak dapat dihapus. Nonaktifkan melalui modul governance yang bersangkutan jika tidak lagi diperlukan.',
                ],
            ], 403);
        }

        $c->delete();

        return response()->json(['success' => true]);
    }

    /**
     * PATCH /salary-components/{id}/tax-flags
     * Update only the PPh21 tax classification flags from the Tax Rate settings page.
     */
    public function patchTaxFlags(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $c = $this->scopedComponentQuery($request)->findOrFail($id);

        $validated = $request->validate([
            'includePph21TerGross' => ['nullable', 'boolean'],
            'includePph21AnnualReconciliation' => ['nullable', 'boolean'],
            'taxTreatmentCode' => ['nullable', 'string', Rule::in(HcmSalaryComponent::TAX_TREATMENT_CODES)],
        ]);

        if (! array_key_exists('includePph21TerGross', $validated) && ! array_key_exists('includePph21AnnualReconciliation', $validated) && ! array_key_exists('taxTreatmentCode', $validated)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'At least one tax classification field is required.'],
            ], 422);
        }

        $updates = [];
        if (array_key_exists('taxTreatmentCode', $validated) && $validated['taxTreatmentCode'] !== null) {
            $updates['tax_treatment_code'] = (string) $validated['taxTreatmentCode'];
            $updates += HcmSalaryComponent::taxFlagsForTreatment($updates['tax_treatment_code']);
        } else {
            $taxTreatmentCode = HcmSalaryComponent::inferTaxTreatmentCode(
                array_key_exists('includePph21TerGross', $validated) && $validated['includePph21TerGross'] !== null
                    ? (bool) $validated['includePph21TerGross']
                    : (bool) $c->include_pph21_ter_gross,
                array_key_exists('includePph21AnnualReconciliation', $validated) && $validated['includePph21AnnualReconciliation'] !== null
                    ? (bool) $validated['includePph21AnnualReconciliation']
                    : (bool) $c->include_pph21_annual_reconciliation,
                (bool) $c->employer_cost_line,
            );

            $updates['tax_treatment_code'] = $taxTreatmentCode;
            $updates += HcmSalaryComponent::taxFlagsForTreatment($taxTreatmentCode);
        }

        if (! empty($updates)) {
            $c->update($updates);
        }

        $c->refresh();

        return response()->json([
            'success' => true,
            'data' => $this->serialize($c),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(HcmSalaryComponent $c): array
    {
        $integrations = $this->integrationEntries($c);

        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'kind' => $c->kind,
            'category' => $c->category,
            'categoryName' => $this->categoryName($c->kind, $c->category),
            'includeBpjsHealthWageBase' => (bool) $c->include_bpjs_health_wage_base,
            'includeBpjsTkWageBase' => (bool) $c->include_bpjs_tk_wage_base,
            'includeThrCalculationBase' => (bool) $c->include_thr_calculation_base,
            'includePph21TerGross' => (bool) $c->include_pph21_ter_gross,
            'includePph21AnnualReconciliation' => (bool) $c->include_pph21_annual_reconciliation,
            'taxTreatmentCode' => $c->tax_treatment_code ?: HcmSalaryComponent::inferTaxTreatmentCode(
                (bool) $c->include_pph21_ter_gross,
                (bool) $c->include_pph21_annual_reconciliation,
                (bool) $c->employer_cost_line,
            ),
            'subjectOvertimeRegulation' => (bool) $c->subject_overtime_regulation,
            'affectsNetPay' => (bool) $c->affects_net_pay,
            'employerCostLine' => (bool) $c->employer_cost_line,
            'isSystemLocked' => (bool) $c->is_system_locked,
            'integrationLocked' => $this->isManagedBySourceModule($c),
            'integrations' => $integrations,
            'sourceModule' => $c->source_module,
            'isActive' => (bool) $c->is_active,
            'sortOrder' => (int) $c->sort_order,
        ];
    }

    private function isManagedBySourceModule(HcmSalaryComponent $c): bool
    {
        return (bool) $c->is_system_locked && in_array((string) $c->source_module, [
            HcmSalaryComponent::SOURCE_MODULE_SYSTEM,
            HcmSalaryComponent::SOURCE_MODULE_BPJS,
            HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE,
            HcmSalaryComponent::SOURCE_MODULE_PPH21,
            HcmSalaryComponent::SOURCE_MODULE_OVERTIME,
            HcmSalaryComponent::SOURCE_MODULE_THR,
            HcmSalaryComponent::SOURCE_MODULE_PKWT,
        ], true);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function integrationEntries(HcmSalaryComponent $c): array
    {
        $entries = [];

        if ((bool) $c->include_bpjs_health_wage_base || (bool) $c->include_bpjs_tk_wage_base) {
            $entries[] = ['key' => 'bpjs', 'label' => 'BPJS Governance'];
        }
        if (in_array((string) $c->code, [
            HcmSalaryComponent::CODE_BASIC_WAGE,
            HcmSalaryComponent::CODE_FIXED_ALLOWANCE,
        ], true)) {
            $entries[] = ['key' => 'employee_salary', 'label' => 'Employee Salary'];
        }
        if ((bool) $c->include_pph21_ter_gross || (bool) $c->include_pph21_annual_reconciliation) {
            $entries[] = ['key' => 'tax', 'label' => 'PPh 21 Governance'];
        }
        if ((bool) $c->include_thr_calculation_base || $c->code === HcmSalaryComponent::CODE_THR) {
            $entries[] = ['key' => 'thr', 'label' => 'Payroll THR'];
        }
        if ((bool) $c->subject_overtime_regulation || $c->code === HcmSalaryComponent::CODE_OVERTIME_PAY) {
            $entries[] = ['key' => 'overtime', 'label' => 'Overtime'];
        }
        if ($c->code === HcmSalaryComponent::CODE_PKWT_COMPENSATION) {
            $entries[] = ['key' => 'pkwt_compensation', 'label' => 'Payroll PKWT Compensation'];
        }

        switch ((string) $c->source_module) {
            case HcmSalaryComponent::SOURCE_MODULE_BPJS:
                $entries[] = ['key' => 'bpjs', 'label' => 'BPJS Governance'];
                break;
            case HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE:
                $entries[] = ['key' => 'allowance', 'label' => 'Allowance Governance'];
                break;
            case HcmSalaryComponent::SOURCE_MODULE_PPH21:
                $entries[] = ['key' => 'tax', 'label' => 'PPh 21 Governance'];
                break;
            case HcmSalaryComponent::SOURCE_MODULE_OVERTIME:
                $entries[] = ['key' => 'overtime', 'label' => 'Overtime'];
                break;
            case HcmSalaryComponent::SOURCE_MODULE_THR:
                $entries[] = ['key' => 'thr', 'label' => 'Payroll THR'];
                break;
            case HcmSalaryComponent::SOURCE_MODULE_PKWT:
                $entries[] = ['key' => 'pkwt_compensation', 'label' => 'Payroll PKWT Compensation'];
                break;
        }

        $unique = [];
        foreach ($entries as $entry) {
            $unique[$entry['key']] = $entry;
        }

        return array_values($unique);
    }

    private function uniqueCode(?string $requested, string $name): string
    {
        $base = $requested ?: Str::slug($name, '_');
        if ($base === '') {
            $base = 'salary_component';
        }
        $base = Str::limit($base, 60, '');
        $code = $base;
        $i = 0;
        while (HcmSalaryComponent::query()->where('code', $code)->exists()) {
            $i++;
            $suffix = '_'.$i;
            $code = Str::limit($base, 64 - strlen($suffix), '').$suffix;
        }

        return $code;
    }

    private function hasDuplicateNameInKindCategory(string $name, string $kind, string $category, ?int $ignoreId = null): bool
    {
        $query = HcmSalaryComponent::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->where('kind', $kind)
            ->where('category', $category);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    private function scopedComponentQuery(Request $request)
    {
        $query = HcmSalaryComponent::query();
        $companyId = $this->activeCompanyId($request);

        $query->where(function ($inner): void {
            $inner->whereNull('source_module')
                ->orWhere('source_module', '!=', HcmSalaryComponent::SOURCE_MODULE_SYSTEM);
        });

        if ($companyId !== null) {
            $query->where(function ($inner) use ($companyId): void {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');
            });

            return $query;
        }

        $query->whereNull('company_id');

        return $query;
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        return $query->where(function (Builder $inner) use ($companyId): void {
            if ($companyId !== null) {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');

                return;
            }

            $inner->whereNull('company_id');
        });
    }

    private function hasActivePph21Policy(?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        $asOf = now()->toDateString();

        return HcmTaxGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->where('status', HcmTaxGovernancePolicy::STATUS_PUBLISHED)
            ->whereDate('effective_start_date', '<=', $asOf)
            ->where(function (Builder $builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->exists();
    }

    private function hasCompleteBpjsPolicyCoverage(?int $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        $asOf = now()->toDateString();

        $pairs = HcmBpjsGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('effective_start_date', '<=', $asOf)
            ->where(function (Builder $builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->get(['program_code', 'contribution_party'])
            ->map(fn (HcmBpjsGovernancePolicy $row) => $row->program_code.'|'.$row->contribution_party)
            ->unique()
            ->values()
            ->all();

        $required = [
            'bpjs_kesehatan|employee',
            'bpjs_kesehatan|employer',
            'jht|employee',
            'jht|employer',
            'jp|employee',
            'jp|employer',
            'jkk|employer',
            'jkm|employer',
        ];

        foreach ($required as $pair) {
            if (! in_array($pair, $pairs, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{amount: float, source: string, category: string, rate: float, rateMode: string}
     */
    private function resolveMonthlyPph21Estimate(float $monthlyTaxableGross, string $taxStatus, ?HcmTaxGovernancePolicy $taxPolicy): array
    {
        $category = $this->resolveTerCategory($taxStatus);

        if ($monthlyTaxableGross <= 0) {
            return [
                'amount' => 0.0,
                'source' => 'pph21_ter_lookup',
                'category' => $category,
                'rate' => 0.0,
                'rateMode' => 'lookup',
            ];
        }

        $policyRate = $this->resolvePolicyScheduleRate($taxPolicy, $monthlyTaxableGross, $category);
        if ($policyRate !== null) {
            return [
                'amount' => round($monthlyTaxableGross * $policyRate['rate'], 2),
                'source' => 'tax_governance_policy_schedule',
                'category' => $category,
                'rate' => $policyRate['rate'],
                'rateMode' => $policyRate['mode'],
            ];
        }

        $table = self::TER_TABLES[$category] ?? self::TER_TABLES['A'];
        $rate = 0.0;

        foreach ($table as [$upperBound, $tableRate]) {
            $rate = $tableRate;
            if ($monthlyTaxableGross <= $upperBound) {
                break;
            }
        }

        return [
            'amount' => round($monthlyTaxableGross * $rate, 2),
            'source' => 'pph21_ter_lookup',
            'category' => $category,
            'rate' => $rate,
            'rateMode' => 'lookup',
        ];
    }

    /**
     * @return array{rate: float, mode: string}|null
     */
    private function resolvePolicyScheduleRate(?HcmTaxGovernancePolicy $taxPolicy, float $monthlyTaxableGross, string $category): ?array
    {
        if ($taxPolicy === null) {
            return null;
        }

        $rules = is_array($taxPolicy->rules) ? $taxPolicy->rules : [];
        $scheme = strtoupper((string) ($rules['scheme'] ?? 'TER'));
        if ($scheme !== 'TER') {
            return null;
        }

        $schedules = is_array($taxPolicy->rate_schedules) ? $taxPolicy->rate_schedules : [];
        if ($schedules === []) {
            return null;
        }

        $matched = [];
        foreach ($schedules as $schedule) {
            if (! is_array($schedule)) {
                continue;
            }

            $scheduleCategory = $this->normalizeScheduleCategory($schedule);
            if ($scheduleCategory !== null && $scheduleCategory !== $category) {
                continue;
            }

            $rate = $this->normalizeScheduleRate($schedule['rate'] ?? $schedule['value'] ?? $schedule['percent'] ?? $schedule['percentage'] ?? null);
            if ($rate === null) {
                continue;
            }

            $upperBound = $this->normalizeScheduleUpperBound($schedule);
            $matched[] = [
                'rate' => $rate,
                'upperBound' => $upperBound,
            ];
        }

        if ($matched === []) {
            return null;
        }

        $bounded = array_values(array_filter($matched, static fn (array $row): bool => $row['upperBound'] !== null));
        if ($bounded !== []) {
            usort($bounded, static fn (array $left, array $right): int => $left['upperBound'] <=> $right['upperBound']);

            $selectedRate = $bounded[array_key_last($bounded)]['rate'];
            foreach ($bounded as $row) {
                $selectedRate = $row['rate'];
                if ($monthlyTaxableGross <= $row['upperBound']) {
                    break;
                }
            }

            return [
                'rate' => $selectedRate,
                'mode' => 'policy_bounded',
            ];
        }

        return [
            'rate' => (float) $matched[0]['rate'],
            'mode' => 'policy_flat',
        ];
    }

    private function resolveTerCategory(string $taxStatus): string
    {
        $taxKey = $this->normalizeTaxStatus($taxStatus);

        return self::TER_STATUS_TO_CATEGORY[$taxKey] ?? 'A';
    }

    private function normalizeTaxStatus(string $taxStatus): string
    {
        $taxKey = strtoupper(str_replace(['/', ' '], '', trim($taxStatus)));

        return match ($taxKey) {
            'TK' => 'TK0',
            'K' => 'K0',
            default => $taxKey,
        };
    }

    private function normalizeScheduleCategory(array $schedule): ?string
    {
        $raw = $schedule['bracket']
            ?? $schedule['category']
            ?? $schedule['terCategory']
            ?? $schedule['taxCategory']
            ?? null;

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return strtoupper(trim($raw));
    }

    private function normalizeScheduleRate(mixed $rawRate): ?float
    {
        if (! is_numeric($rawRate)) {
            return null;
        }

        $rate = (float) $rawRate;
        if ($rate < 0) {
            return null;
        }

        return $rate > 1 ? ($rate / 100) : $rate;
    }

    private function normalizeScheduleUpperBound(array $schedule): ?float
    {
        foreach (['upperBound', 'maxGross', 'maxGrossMonthly', 'monthlyGrossUpTo', 'threshold'] as $key) {
            if (! array_key_exists($key, $schedule) || ! is_numeric($schedule[$key])) {
                continue;
            }

            return (float) $schedule[$key];
        }

        return null;
    }

    private function resolveTaxTreatmentCodeFromValidated(array $validated): string
    {
        if (! empty($validated['taxTreatmentCode'])) {
            return (string) $validated['taxTreatmentCode'];
        }

        return HcmSalaryComponent::inferTaxTreatmentCode(
            (bool) ($validated['includePph21TerGross'] ?? false),
            (bool) ($validated['includePph21AnnualReconciliation'] ?? false),
            (bool) ($validated['employerCostLine'] ?? false),
        );
    }
}
