<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmPayrollItem;
use App\Models\HcmSalaryComponent;
use App\Models\HcmSalaryComponentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HcmSalaryComponentController extends Controller
{
    use ChecksPermissions;

    public function categories(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $rows = HcmSalaryComponentCategory::query()
            ->orderBy('kind')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (HcmSalaryComponentCategory $c): array {
                return [
                    'id' => (int) $c->id,
                    'kind' => (string) $c->kind,
                    'code' => (string) $c->code,
                    'name' => (string) $c->name,
                    'description' => $c->description,
                    'isSystem' => (bool) $c->is_system,
                    'isActive' => (bool) $c->is_active,
                    'sortOrder' => (int) $c->sort_order,
                    'usageCount' => HcmSalaryComponent::query()
                        ->where('kind', $c->kind)
                        ->where('category', $c->code)
                        ->count(),
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(['addition', 'deduction'])],
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $code = (string) $validated['code'];
        if (HcmSalaryComponentCategory::query()->where('kind', $validated['kind'])->where('code', $code)->exists()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Kategori dengan kode tersebut sudah ada pada jenis yang sama.'],
            ], 422);
        }

        $row = HcmSalaryComponentCategory::query()->create([
            'kind' => (string) $validated['kind'],
            'code' => $code,
            'name' => trim((string) $validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'is_system' => false,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => (int) $row->id]], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $row = HcmSalaryComponentCategory::query()->findOrFail($id);

        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(['addition', 'deduction'])],
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if (HcmSalaryComponentCategory::query()
            ->where('kind', $validated['kind'])
            ->where('code', (string) $validated['code'])
            ->whereKeyNot($row->id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Kategori dengan kode tersebut sudah ada pada jenis yang sama.'],
            ], 422);
        }

        $oldKind = (string) $row->kind;
        $oldCode = (string) $row->code;

        $row->update([
            'kind' => (string) $validated['kind'],
            'code' => (string) $validated['code'],
            'name' => trim((string) $validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);

        // Keep existing components in sync when category code/kind is adjusted.
        if ($oldKind !== $row->kind || $oldCode !== $row->code) {
            HcmSalaryComponent::query()
                ->where('kind', $oldKind)
                ->where('category', $oldCode)
                ->update([
                    'kind' => $row->kind,
                    'category' => $row->code,
                    'updated_at' => now(),
                ]);
        }

        return response()->json(['success' => true]);
    }

    public function destroyCategory(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $row = HcmSalaryComponentCategory::query()->findOrFail($id);
        HcmSalaryComponent::query()
            ->where('kind', $row->kind)
            ->where('category', $row->code)
            ->delete();

        $row->delete();

        return response()->json(['success' => true]);
    }

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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', 'string', Rule::in(['addition', 'deduction'])],
            'category' => [
                'required',
                'string',
                Rule::in(HcmSalaryComponent::allCategoryCodes()),
            ],
            'legalBasis' => ['nullable', 'string', 'max:500'],
            'legalNotes' => ['nullable', 'string', 'max:5000'],
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
            'defaultPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'percentBasis' => ['nullable', 'string', Rule::in(HcmSalaryComponent::PERCENT_BASES)],
        ]);

        $pairErr = $this->percentPairError($validated['defaultPercent'] ?? null, $validated['percentBasis'] ?? null);
        if ($pairErr !== null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $pairErr],
            ], 422);
        }

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
            'description' => $validated['description'] ?? null,
            'kind' => $validated['kind'],
            'category' => $validated['category'],
            'legal_basis' => $validated['legalBasis'] ?? null,
            'legal_notes' => $validated['legalNotes'] ?? null,
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
            'default_percent' => $this->nullablePercent($validated['defaultPercent'] ?? null),
            'percent_basis' => $this->nullablePercentBasis($validated['defaultPercent'] ?? null, $validated['percentBasis'] ?? null),
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', 'string', Rule::in(['addition', 'deduction'])],
            'category' => [
                'required',
                'string',
                Rule::in(HcmSalaryComponent::allCategoryCodes()),
            ],
            'legalBasis' => ['nullable', 'string', 'max:500'],
            'legalNotes' => ['nullable', 'string', 'max:5000'],
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
            'defaultPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'percentBasis' => ['nullable', 'string', Rule::in(HcmSalaryComponent::PERCENT_BASES)],
        ]);

        $pairErr = $this->percentPairError($validated['defaultPercent'] ?? null, $validated['percentBasis'] ?? null);
        if ($pairErr !== null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $pairErr],
            ], 422);
        }

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
            'description' => $validated['description'] ?? null,
            'kind' => $validated['kind'],
            'category' => $validated['category'],
            'legal_basis' => $validated['legalBasis'] ?? null,
            'legal_notes' => $validated['legalNotes'] ?? null,
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
            'default_percent' => $this->nullablePercent($validated['defaultPercent'] ?? null),
            'percent_basis' => $this->nullablePercentBasis($validated['defaultPercent'] ?? null, $validated['percentBasis'] ?? null),
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
            'description' => $c->description ?? '',
            'kind' => $c->kind,
            'category' => $c->category,
            'categoryName' => $this->categoryName($c->kind, $c->category),
            'legalBasis' => $c->legal_basis ?? '',
            'legalNotes' => $c->legal_notes ?? '',
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
            'isActive' => (bool) $c->is_active,
            'sortOrder' => (int) $c->sort_order,
            'defaultPercent' => $c->default_percent !== null ? (string) $c->default_percent : null,
            'percentBasis' => $c->percent_basis,
        ];
    }

    private function resolveTaxTreatmentCodeFromValidated(array $validated): string
    {
        if (! empty($validated['taxTreatmentCode'])) {
            return (string) $validated['taxTreatmentCode'];
        }

        return HcmSalaryComponent::inferTaxTreatmentCode(
            (bool) ($validated['includePph21TerGross'] ?? true),
            (bool) ($validated['includePph21AnnualReconciliation'] ?? false),
            (bool) ($validated['employerCostLine'] ?? false),
        );
    }

    /**
     * @return list<array{key:string,label:string,kind:string,locked:bool}>
     */
    private function integrationEntries(HcmSalaryComponent $component): array
    {
        $entries = [];

        foreach ($this->managedIntegrations($component) as $entry) {
            $entries[] = [
                'key' => $entry['key'],
                'label' => $entry['label'],
                'kind' => 'managed',
                'locked' => true,
            ];
        }

        if (HcmPayrollItem::query()->where('hcm_salary_component_id', $component->id)->exists()) {
            $entries[] = [
                'key' => 'payroll_items',
                'label' => 'Payroll Items',
                'kind' => 'linked',
                'locked' => false,
            ];
        }

        return $entries;
    }

    private function isManagedBySourceModule(HcmSalaryComponent $component): bool
    {
        return $this->managedIntegrations($component) !== [];
    }

    private function managedModuleLabel(HcmSalaryComponent $component): string
    {
        return collect($this->managedIntegrations($component))->pluck('label')->implode(', ');
    }

    /**
     * @return list<array{key:string,label:string}>
     */
    private function managedIntegrations(HcmSalaryComponent $component): array
    {
        return match ($component->code) {
            'upah_pokok' => [
                ['key' => 'employee_salary_base', 'label' => 'Employee Salary'],
                ['key' => 'monthly_payroll_auto', 'label' => 'Monthly Payroll Auto'],
            ],
            'tunjangan_tetap' => [
                ['key' => 'employee_salary_fixed_allowance', 'label' => 'Employee Salary (fixed allowance bucket)'],
                ['key' => 'monthly_payroll_auto', 'label' => 'Monthly Payroll Auto'],
            ],
            'upah_lembur' => [
                ['key' => 'overtime', 'label' => 'Overtime'],
            ],
            'thr' => [
                ['key' => 'thr', 'label' => 'Payroll THR'],
            ],
            'kompensasi_pkwt' => [
                ['key' => 'pkwt_compensation', 'label' => 'Payroll PKWT Compensation'],
            ],
            'iuran_bpjs_kes_pekerja', 'iuran_jht_pekerja', 'iuran_jp_pekerja', 'pph21_ter' => [
                ['key' => 'monthly_payroll_formula', 'label' => 'Monthly Payroll Formula'],
            ],
            default => [],
        };
    }

    private function percentPairError(mixed $percent, mixed $basis): ?string
    {
        $pSet = $percent !== null && $percent !== '';
        $bSet = $basis !== null && $basis !== '';
        if ($pSet === $bSet) {
            return null;
        }

        return 'defaultPercent dan percentBasis harus keduanya diisi atau keduanya kosong (nominal tetap).';
    }

    private function nullablePercent(mixed $percent): ?string
    {
        if ($percent === null || $percent === '') {
            return null;
        }

        return number_format((float) $percent, 4, '.', '');
    }

    private function nullablePercentBasis(mixed $percent, mixed $basis): ?string
    {
        if ($percent === null || $percent === '' || $basis === null || $basis === '') {
            return null;
        }

        return (string) $basis;
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

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    private function categoryName(string $kind, string $code): string
    {
        return (string) (HcmSalaryComponentCategory::query()
            ->where('kind', $kind)
            ->where('code', $code)
            ->value('name') ?? $code);
    }
}
