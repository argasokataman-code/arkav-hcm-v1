<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmEmployeeAllowancePolicy;
use App\Models\HcmPayrollItem;
use App\Models\HcmSalaryComponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Halaman web `/payroll`: katalog payroll item (kustom atau taut ke baris `hcm_salary_components`).
 */
class HcmPayrollItemController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'kind' => ['nullable', 'string', Rule::in(['addition', 'deduction'])],
        ]);

        $companyId = $this->activeCompanyId($request);

        $this->ensureAllowanceLinkedRows($companyId);
        $this->syncLinkedRowsWithMaster($companyId);

        $query = HcmPayrollItem::query()
            ->with('salaryComponent')
            ->orderBy('sort_order')
            ->orderBy('id');
        $this->applyTenantScope($query, $companyId);
        $allowanceAmountByCode = $this->activeAllowanceAmountByCode($companyId);

        if (! empty($validated['kind'] ?? null)) {
            $query->where('kind', $validated['kind']);
        }

        $payrollItems = $query->get()->map(fn (HcmPayrollItem $item) => $this->serializePayrollItem($item, $allowanceAmountByCode));

        $linkedSalaryComponentIds = HcmPayrollItem::query()
            ->whereNotNull('hcm_salary_component_id')
            ->where(function (Builder $inner) use ($companyId): void {
                if ($companyId !== null) {
                    $inner->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $inner->whereNull('company_id');
            })
            ->pluck('hcm_salary_component_id')
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'payrollItems' => $payrollItems,
            ],
            'meta' => [
                'linkedSalaryComponentIds' => $linkedSalaryComponentIds,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $forbidden = $this->ensurePermission($request, 'payroll.view');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'kind' => ['nullable', 'string', Rule::in(['addition', 'deduction'])],
            'format' => ['nullable', 'string', Rule::in(['csv', 'xlsx'])],
        ]);

        $companyId = $this->activeCompanyId($request);

        $this->ensureAllowanceLinkedRows($companyId);
        $this->syncLinkedRowsWithMaster($companyId);

        $query = HcmPayrollItem::query()->with('salaryComponent')->orderBy('sort_order')->orderBy('id');
        $this->applyTenantScope($query, $companyId);
        if (! empty($validated['kind'] ?? null)) {
            $query->where('kind', $validated['kind']);
        }

        $allowanceAmountByCode = $this->activeAllowanceAmountByCode($companyId);
        $rows = $query->get()->map(fn (HcmPayrollItem $item) => $this->serializePayrollItem($item, $allowanceAmountByCode))->values();
        $format = strtolower((string) ($validated['format'] ?? 'xlsx'));
        $kindPart = $validated['kind'] ?? 'all';
        $fileBase = 'payroll-items-'.$kindPart.'-'.now()->format('YmdHis');

        if ($format === 'xlsx') {
            return $this->exportXlsx($rows->all(), $fileBase.'.xlsx');
        }

        return $this->exportCsv($rows->all(), $fileBase.'.csv');
    }

    public function store(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        if ($request->filled('salaryComponentId')) {
            $validated = $request->validate([
                'salaryComponentId' => [
                    'required',
                    'integer',
                    Rule::exists('hcm_salary_components', 'id')->where(function ($query) use ($companyId): void {
                        if ($companyId !== null) {
                            $query->where(function ($inner) use ($companyId): void {
                                $inner->where('company_id', $companyId)->orWhereNull('company_id');
                            });

                            return;
                        }

                        $query->whereNull('company_id');
                    }),
                ],
                'notes' => ['nullable', 'string', 'max:5000'],
                'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'isActive' => ['nullable', 'boolean'],
            ]);
            $takenQuery = HcmPayrollItem::query()->where('hcm_salary_component_id', $validated['salaryComponentId']);
            $this->applyTenantScope($takenQuery, $companyId);
            if ($takenQuery->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PAYROLL_ITEM_LINK_TAKEN',
                        'message' => 'Komponen gaji ini sudah tertaut ke payroll item lain.',
                    ],
                ], 422);
            }
            $componentQuery = HcmSalaryComponent::query()->whereKey($validated['salaryComponentId']);
            $this->applyTenantScope($componentQuery, $companyId);
            $comp = $componentQuery->firstOrFail();
            if (! $comp->is_active) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PAYROLL_ITEM_MASTER_INACTIVE',
                        'message' => 'Komponen gaji tidak aktif dan tidak dapat ditautkan.',
                    ],
                ], 422);
            }
            $item = HcmPayrollItem::query()->create([
                'company_id' => $companyId,
                'hcm_salary_component_id' => $comp->id,
                'code' => $comp->code,
                'name' => $comp->name,
                'kind' => $comp->kind,
                'category' => $comp->category,
                'notes' => $validated['notes'] ?? null,
                'sort_order' => $validated['sortOrder'] ?? $comp->sort_order,
                'is_active' => array_key_exists('isActive', $validated) ? (bool) $validated['isActive'] : (bool) $comp->is_active,
            ]);

            return response()->json(['success' => true, 'data' => ['id' => $item->id]], 201);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('hcm_payroll_items', 'code')->where(function ($query) use ($companyId): void {
                    if ($companyId !== null) {
                        $query->where(function ($inner) use ($companyId): void {
                            $inner->where('company_id', $companyId)->orWhereNull('company_id');
                        });

                        return;
                    }

                    $query->whereNull('company_id');
                }),
            ],
            'kind' => ['required', 'string', Rule::in(['addition', 'deduction'])],
            'category' => [
                'required',
                'string',
                Rule::in(HcmSalaryComponent::allCategoryCodes()),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'isActive' => ['nullable', 'boolean'],
        ]);
        if (! HcmSalaryComponent::isValidCategoryForKind($validated['kind'], $validated['category'])) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'category does not match kind.'],
            ], 422);
        }

        $item = HcmPayrollItem::query()->create([
            'company_id' => $companyId,
            'hcm_salary_component_id' => null,
            'code' => $validated['code'] ?? null,
            'name' => $validated['name'],
            'kind' => $validated['kind'],
            'category' => $validated['category'],
            'notes' => $validated['notes'] ?? null,
            'sort_order' => $validated['sortOrder'] ?? 0,
            'is_active' => (bool) ($validated['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $item->id]], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        $itemQuery = HcmPayrollItem::query();
        $this->applyIdentifierScope($itemQuery, $id, true);
        $this->applyTenantScope($itemQuery, $companyId);
        $item = $itemQuery->firstOrFail();

        if ($item->hcm_salary_component_id !== null) {
            if ($request->has('salaryComponentId') && $request->input('salaryComponentId') === null) {
                $validated = $request->validate([
                    'name' => ['required', 'string', 'max:200'],
                    'code' => [
                        'nullable',
                        'string',
                        'max:64',
                        'regex:/^[a-z0-9_\-]+$/',
                        Rule::unique('hcm_payroll_items', 'code')
                            ->ignore($item->id)
                            ->where(function ($query) use ($companyId): void {
                                if ($companyId !== null) {
                                    $query->where(function ($inner) use ($companyId): void {
                                        $inner->where('company_id', $companyId)->orWhereNull('company_id');
                                    });

                                    return;
                                }

                                $query->whereNull('company_id');
                            }),
                    ],
                    'kind' => ['required', 'string', Rule::in(['addition', 'deduction'])],
                    'category' => [
                        'required',
                        'string',
                        Rule::in(HcmSalaryComponent::allCategoryCodes()),
                    ],
                    'notes' => ['nullable', 'string', 'max:5000'],
                    'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
                    'isActive' => ['nullable', 'boolean'],
                ]);
                if (! HcmSalaryComponent::isValidCategoryForKind($validated['kind'], $validated['category'])) {
                    return response()->json([
                        'success' => false,
                        'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'category does not match kind.'],
                    ], 422);
                }
                $item->update([
                    'hcm_salary_component_id' => null,
                    'code' => $validated['code'] ?? null,
                    'name' => $validated['name'],
                    'kind' => $validated['kind'],
                    'category' => $validated['category'],
                    'notes' => $validated['notes'] ?? null,
                    'sort_order' => $validated['sortOrder'] ?? $item->sort_order,
                    'is_active' => array_key_exists('isActive', $validated) ? (bool) $validated['isActive'] : $item->is_active,
                ]);

                return response()->json(['success' => true]);
            }

            $validated = $request->validate([
                'notes' => ['nullable', 'string', 'max:5000'],
                'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'isActive' => ['nullable', 'boolean'],
            ]);
            $payload = [];
            if (array_key_exists('notes', $validated)) {
                $payload['notes'] = $validated['notes'];
            }
            if (array_key_exists('sortOrder', $validated)) {
                $payload['sort_order'] = $validated['sortOrder'];
            }
            if (array_key_exists('isActive', $validated)) {
                $payload['is_active'] = (bool) $validated['isActive'];
            }
            if ($payload !== []) {
                $item->update($payload);
            }

            return response()->json(['success' => true]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'code' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('hcm_payroll_items', 'code')
                    ->ignore($item->id)
                    ->where(function ($query) use ($companyId): void {
                        if ($companyId !== null) {
                            $query->where(function ($inner) use ($companyId): void {
                                $inner->where('company_id', $companyId)->orWhereNull('company_id');
                            });

                            return;
                        }

                        $query->whereNull('company_id');
                    }),
            ],
            'kind' => ['sometimes', 'required', 'string', Rule::in(['addition', 'deduction'])],
            'category' => [
                'sometimes',
                'required',
                'string',
                Rule::in(HcmSalaryComponent::allCategoryCodes()),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'isActive' => ['nullable', 'boolean'],
            'salaryComponentId' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('hcm_salary_components', 'id')->where(function ($query) use ($companyId): void {
                    if ($companyId !== null) {
                        $query->where(function ($inner) use ($companyId): void {
                            $inner->where('company_id', $companyId)->orWhereNull('company_id');
                        });

                        return;
                    }

                    $query->whereNull('company_id');
                }),
            ],
        ]);

        $kind = $validated['kind'] ?? $item->kind;
        $category = $validated['category'] ?? $item->category;
        if (isset($validated['kind']) || isset($validated['category'])) {
            if (! HcmSalaryComponent::isValidCategoryForKind($kind, $category)) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'category does not match kind.'],
                ], 422);
            }
        }

        if (array_key_exists('salaryComponentId', $validated) && $validated['salaryComponentId'] !== null) {
            $cid = (int) $validated['salaryComponentId'];
            $takenQuery = HcmPayrollItem::query()
                ->where('hcm_salary_component_id', $cid)
                ->where('id', '!=', $item->id);
            $this->applyTenantScope($takenQuery, $companyId);
            if ($takenQuery->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PAYROLL_ITEM_LINK_TAKEN',
                        'message' => 'Komponen gaji ini sudah tertaut ke payroll item lain.',
                    ],
                ], 422);
            }
            $componentQuery = HcmSalaryComponent::query()->whereKey($cid);
            $this->applyTenantScope($componentQuery, $companyId);
            $comp = $componentQuery->firstOrFail();
            if (! $comp->is_active) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PAYROLL_ITEM_MASTER_INACTIVE',
                        'message' => 'Komponen gaji tidak aktif dan tidak dapat ditautkan.',
                    ],
                ], 422);
            }
            $item->update([
                'hcm_salary_component_id' => $comp->id,
                'code' => $comp->code,
                'name' => $comp->name,
                'kind' => $comp->kind,
                'category' => $comp->category,
                'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $item->notes,
                'sort_order' => $validated['sortOrder'] ?? $item->sort_order,
                'is_active' => array_key_exists('isActive', $validated) ? (bool) $validated['isActive'] : $item->is_active,
            ]);

            return response()->json(['success' => true]);
        }

        $payload = [];
        if (array_key_exists('name', $validated)) {
            $payload['name'] = $validated['name'];
        }
        if (array_key_exists('code', $validated)) {
            $payload['code'] = $validated['code'];
        }
        if (array_key_exists('kind', $validated)) {
            $payload['kind'] = $validated['kind'];
        }
        if (array_key_exists('category', $validated)) {
            $payload['category'] = $validated['category'];
        }
        if (array_key_exists('notes', $validated)) {
            $payload['notes'] = $validated['notes'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $payload['sort_order'] = $validated['sortOrder'];
        }
        if (array_key_exists('isActive', $validated)) {
            $payload['is_active'] = (bool) $validated['isActive'];
        }
        if ($payload !== []) {
            $item->update($payload);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'payroll.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $itemQuery = HcmPayrollItem::query();
        $this->applyIdentifierScope($itemQuery, $id, true);
        $this->applyTenantScope($itemQuery, $this->activeCompanyId($request));
        $item = $itemQuery->firstOrFail();
        $item->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayrollItem(HcmPayrollItem $item, array $allowanceAmountByCode = []): array
    {
        $linked = $item->hcm_salary_component_id !== null;
        $master = $linked ? $item->salaryComponent : null;
        $code = $linked && $master ? $master->code : $item->code;
        $name = $linked && $master ? $master->name : $item->name;
        $kind = $linked && $master ? $master->kind : $item->kind;
        $category = $linked && $master ? $master->category : $item->category;
        $sourceModule = $linked && $master ? $master->source_module : null;
        $allowanceDefaultAmount = null;
        $inAllowanceGovernance = false;
        if ($sourceModule === HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE && is_string($code) && $code !== '') {
            if (array_key_exists($code, $allowanceAmountByCode)) {
                $inAllowanceGovernance = true;
                $allowanceDefaultAmount = $allowanceAmountByCode[$code];
            }
        }

        return [
            'id' => $item->id,
            'salaryComponentId' => $item->hcm_salary_component_id,
            'linkedToMaster' => $linked,
            'code' => $code,
            'name' => $name,
            'kind' => $kind,
            'category' => $category,
            'notes' => $item->notes,
            'sortOrder' => (int) $item->sort_order,
            'isActive' => (bool) $item->is_active,
            'sourceModule' => $sourceModule,
            'isSystemLocked' => $linked && $master ? (bool) $master->is_system_locked : false,
            'allowanceDefaultAmount' => $allowanceDefaultAmount,
            'inAllowanceGovernance' => $inAllowanceGovernance,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function activeAllowanceAmountByCode(?int $companyId): array
    {
        if ($companyId === null) {
            return [];
        }

        $asOf = now()->toDateString();

        return HcmEmployeeAllowancePolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('effective_start_date', '<=', $asOf)
            ->where(function (Builder $builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->get(['code', 'default_amount'])
            ->mapWithKeys(function (HcmEmployeeAllowancePolicy $policy): array {
                return [(string) $policy->code => round((float) $policy->default_amount, 2)];
            })
            ->all();
    }

    private function syncLinkedRowsWithMaster(?int $companyId): void
    {
        $items = HcmPayrollItem::query()
            ->with('salaryComponent:id,code,name,kind,category')
            ->whereNotNull('hcm_salary_component_id')
            ->where(function (Builder $inner) use ($companyId): void {
                if ($companyId !== null) {
                    $inner->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $inner->whereNull('company_id');
            })
            ->get();

        foreach ($items as $item) {
            $master = $item->salaryComponent;
            if (! $master) {
                continue;
            }

            $dirty = [];
            if ($item->code !== $master->code) {
                $dirty['code'] = $master->code;
            }
            if ($item->name !== $master->name) {
                $dirty['name'] = $master->name;
            }
            if ($item->kind !== $master->kind) {
                $dirty['kind'] = $master->kind;
            }
            if ($item->category !== $master->category) {
                $dirty['category'] = $master->category;
            }

            if ($dirty !== []) {
                $item->update($dirty);
            }
        }
    }

    private function ensureAllowanceLinkedRows(?int $companyId): void
    {
        $this->ensureAllowanceComponentsFromPolicies($companyId);

        $componentsQuery = HcmSalaryComponent::query()
            ->where('source_module', HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($companyId !== null) {
            $componentsQuery->where(function (Builder $inner) use ($companyId): void {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');
            });
        } else {
            $componentsQuery->whereNull('company_id');
        }

        $components = $componentsQuery->get();
        if ($components->isEmpty()) {
            return;
        }

        $existingComponentIds = HcmPayrollItem::query()
            ->whereIn('hcm_salary_component_id', $components->pluck('id')->all())
            ->pluck('hcm_salary_component_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($components as $component) {
            if (in_array((int) $component->id, $existingComponentIds, true)) {
                continue;
            }

            HcmPayrollItem::query()->create([
                'company_id' => $component->company_id,
                'hcm_salary_component_id' => $component->id,
                'code' => $component->code,
                'name' => $component->name,
                'kind' => $component->kind,
                'category' => $component->category,
                'notes' => 'Auto-linked from allowance governance.',
                'sort_order' => (int) $component->sort_order,
                'is_active' => (bool) $component->is_active,
            ]);
        }
    }

    private function ensureAllowanceComponentsFromPolicies(?int $companyId): void
    {
        if ($companyId === null) {
            return;
        }

        $policies = HcmEmployeeAllowancePolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['code', 'name', 'is_taxable']);

        foreach ($policies as $policy) {
            $code = (string) $policy->code;
            $name = (string) $policy->name;
            $isTaxable = (bool) $policy->is_taxable;

            if ($code === '' || $name === '') {
                continue;
            }

            $isIrregular = str_contains($code, 'insentif')
                || str_contains($code, 'irregular')
                || str_contains($code, 'tidak_tetap');
            $category = $isIrregular ? 'irregular_allowance' : 'fixed_allowance';
            $taxTreatmentCode = $isTaxable
                ? HcmSalaryComponent::TAX_TREATMENT_PPH21_TAXABLE_FULL
                : HcmSalaryComponent::TAX_TREATMENT_NON_OBJECT;

            HcmSalaryComponent::ensureComponent(
                $companyId,
                $code,
                $name,
                'addition',
                $category,
                HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE,
                [
                    'tax_treatment_code' => $taxTreatmentCode,
                    'include_pph21_ter_gross' => $isTaxable,
                    'include_pph21_annual_reconciliation' => $isTaxable,
                    'include_thr_calculation_base' => true,
                    'include_bpjs_health_wage_base' => false,
                    'include_bpjs_tk_wage_base' => false,
                    'affects_net_pay' => true,
                    'employer_cost_line' => false,
                ]
            );
        }
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        $supportsSalaryComponentRelation = $query->getModel() instanceof HcmPayrollItem;

        return $query->where(function (Builder $inner) use ($companyId, $supportsSalaryComponentRelation): void {
            if ($companyId !== null) {
                $inner->where('company_id', $companyId)
                    ->orWhereNull('company_id');

                if ($supportsSalaryComponentRelation) {
                    // Governance items seeded under a specific company are still
                    // visible to all tenants when linked to a global salary component.
                    $inner->orWhereHas('salaryComponent', fn (Builder $scQ) => $scQ->whereNull('company_id'));
                }

                return;
            }

            $inner->whereNull('company_id');
        });
    }

    private function applyIdentifierScope(Builder $query, string $identifier, bool $hasUuidColumn): Builder
    {
        if ($hasUuidColumn && Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier);
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function exportCsv(array $rows, string $filename): StreamedResponse
    {
        $headers = [
            'ID',
            'Salary Component ID',
            'Linked To Master',
            'Code',
            'Name',
            'Kind',
            'Category',
            'Notes',
            'Sort Order',
            'Is Active',
            'Master Default Percent',
            'Master Percent Basis',
        ];

        return response()->streamDownload(function () use ($rows, $headers): void {
            $fh = fopen('php://output', 'w');
            if ($fh === false) {
                return;
            }
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, $headers);
            foreach ($rows as $row) {
                fputcsv($fh, [
                    $row['id'] ?? null,
                    $row['salaryComponentId'] ?? null,
                    ! empty($row['linkedToMaster']) ? 'yes' : 'no',
                    $row['code'] ?? null,
                    $row['name'] ?? null,
                    $row['kind'] ?? null,
                    $row['category'] ?? null,
                    $row['notes'] ?? null,
                    $row['sortOrder'] ?? null,
                    ! empty($row['isActive']) ? 'yes' : 'no',
                    $row['masterDefaultPercent'] ?? null,
                    $row['masterPercentBasis'] ?? null,
                ]);
            }
            fclose($fh);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function exportXlsx(array $rows, string $filename)
    {
        $sheetRows = [[
            'ID',
            'Salary Component ID',
            'Linked To Master',
            'Code',
            'Name',
            'Kind',
            'Category',
            'Notes',
            'Sort Order',
            'Is Active',
            'Master Default Percent',
            'Master Percent Basis',
        ]];

        foreach ($rows as $row) {
            $sheetRows[] = [
                $row['id'] ?? null,
                $row['salaryComponentId'] ?? null,
                ! empty($row['linkedToMaster']) ? 'yes' : 'no',
                $row['code'] ?? null,
                $row['name'] ?? null,
                $row['kind'] ?? null,
                $row['category'] ?? null,
                $row['notes'] ?? null,
                $row['sortOrder'] ?? null,
                ! empty($row['isActive']) ? 'yes' : 'no',
                $row['masterDefaultPercent'] ?? null,
                $row['masterPercentBasis'] ?? null,
            ];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payroll Items');
        $sheet->fromArray($sheetRows, null, 'A1');
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
