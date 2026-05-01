<?php

namespace App\Models;
use App\Models\Concerns\AssignsUuid;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Master komponen penggajian (Indonesia-oriented flags for future payroll engine).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $kind addition|deduction
 * @property string $category
 * @property string|null $legal_basis
 * @property string|null $legal_notes
 * @property bool $include_bpjs_health_wage_base
 * @property bool $include_bpjs_tk_wage_base
 * @property bool $include_thr_calculation_base
 * @property bool $include_pph21_ter_gross
 * @property bool $include_pph21_annual_reconciliation
 * @property bool $subject_overtime_regulation
 * @property bool $affects_net_pay
 * @property bool $employer_cost_line
 * @property bool $is_system_locked
 * @property int $sort_order
 * @property bool $is_active
 * @property string|null $default_percent
 * @property string|null $percent_basis
 */
class HcmSalaryComponent extends Model
{
    use AssignsUuid;
    protected $table = 'hcm_salary_components';

    public const TAX_TREATMENT_PPH21_TAXABLE_FULL = 'pph21_taxable_full';

    public const TAX_TREATMENT_PPH21_TAXABLE_PARTIAL = 'pph21_taxable_partial';

    public const TAX_TREATMENT_NON_OBJECT = 'non_object';

    public const TAX_TREATMENT_DEDUCTIBLE = 'deductible';

    public const TAX_TREATMENT_PPH21_FINAL = 'pph21_final';

    public const TAX_TREATMENT_PPH21_SEPARATE = 'pph21_separate';

    public const TAX_TREATMENT_EMPLOYER_DISPLAY_ONLY = 'employer_display_only';


    /** @var list<string> */
    public const ADDITION_CATEGORIES = [
        'basic_wage',
        'fixed_allowance',
        'irregular_allowance',
        'overtime',
        'thr',
        'bonus',
        'natura_taxable',
        'natura_non_taxable',
        'special_allowance',
        'reimbursement',
        'termination_benefit',
        'employer_cost_display',
        'other_addition',
    ];

    /** @var list<string> */
    public const DEDUCTION_CATEGORIES = [
        'bpjs_health_employee',
        'bpjs_jht_employee',
        'bpjs_jp_employee',
        'pension_employee',
        'pph21_ter',
        'pph21_december_recon',
        'other_statutory',
        'internal_advance',
        'internal_loan',
        'internal_cooperative',
        'internal_other',
        'other_deduction',
    ];

    /**
     * Dasar perhitungan jika {@see $default_percent} terisi (mesin gaji mendatang).
     *
     * @var list<string>
     */
    public const PERCENT_BASES = [
        'basic_wage',
        'wage_bpjs_health',
        'wage_bpjs_tk',
        'gross_monthly_ter',
        'thr_calculation_base',
    ];

    /** @var list<string> */
    public const TAX_TREATMENT_CODES = [
        self::TAX_TREATMENT_PPH21_TAXABLE_FULL,
        self::TAX_TREATMENT_PPH21_TAXABLE_PARTIAL,
        self::TAX_TREATMENT_NON_OBJECT,
        self::TAX_TREATMENT_DEDUCTIBLE,
        self::TAX_TREATMENT_PPH21_FINAL,
        self::TAX_TREATMENT_PPH21_SEPARATE,
        self::TAX_TREATMENT_EMPLOYER_DISPLAY_ONLY,
    ];

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'kind',
        'category',
        'category_uuid',
        'legal_basis',
        'legal_notes',
        'default_percent',
        'percent_basis',
        'include_bpjs_health_wage_base',
        'include_bpjs_tk_wage_base',
        'include_thr_calculation_base',
        'include_pph21_ter_gross',
        'include_pph21_annual_reconciliation',
        'tax_treatment_code',
        'subject_overtime_regulation',
        'affects_net_pay',
        'employer_cost_line',
        'is_system_locked',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'category_uuid' => 'string',
            'default_percent' => 'decimal:4',
            'include_bpjs_health_wage_base' => 'boolean',
            'include_bpjs_tk_wage_base' => 'boolean',
            'include_thr_calculation_base' => 'boolean',
            'include_pph21_ter_gross' => 'boolean',
            'include_pph21_annual_reconciliation' => 'boolean',
            'tax_treatment_code' => 'string',
            'subject_overtime_regulation' => 'boolean',
            'affects_net_pay' => 'boolean',
            'employer_cost_line' => 'boolean',
            'is_system_locked' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function categoriesForKind(string $kind, bool $activeOnly = false): array
    {
        $fallback = match ($kind) {
            'addition' => self::ADDITION_CATEGORIES,
            'deduction' => self::DEDUCTION_CATEGORIES,
            default => [],
        };

        if (! Schema::hasTable('hcm_salary_component_categories')) {
            return $fallback;
        }

        $query = HcmSalaryComponentCategory::query()
            ->where('kind', $kind);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $custom = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->filter()
            ->values()
            ->all();

        return $custom;
    }

    public function categoryDefinition(): BelongsTo
    {
        return $this->belongsTo(HcmSalaryComponentCategory::class, 'category_uuid', 'uuid');
    }

    /** @return list<string> */
    public static function allCategoryCodes(bool $activeOnly = false): array
    {
        return array_values(array_unique(array_merge(
            self::categoriesForKind('addition', $activeOnly),
            self::categoriesForKind('deduction', $activeOnly),
        )));
    }

    public static function isValidCategoryForKind(string $kind, string $category): bool
    {
        return in_array($category, self::categoriesForKind($kind), true);
    }

    /**
     * @return array{include_pph21_ter_gross: bool, include_pph21_annual_reconciliation: bool}
     */
    public static function taxFlagsForTreatment(string $taxTreatmentCode): array
    {
        return match ($taxTreatmentCode) {
            self::TAX_TREATMENT_PPH21_TAXABLE_FULL => [
                'include_pph21_ter_gross' => true,
                'include_pph21_annual_reconciliation' => true,
            ],
            self::TAX_TREATMENT_PPH21_TAXABLE_PARTIAL => [
                'include_pph21_ter_gross' => true,
                'include_pph21_annual_reconciliation' => false,
            ],
            self::TAX_TREATMENT_DEDUCTIBLE => [
                'include_pph21_ter_gross' => false,
                'include_pph21_annual_reconciliation' => true,
            ],
            self::TAX_TREATMENT_NON_OBJECT,
            self::TAX_TREATMENT_PPH21_FINAL,
            self::TAX_TREATMENT_PPH21_SEPARATE,
            self::TAX_TREATMENT_EMPLOYER_DISPLAY_ONLY => [
                'include_pph21_ter_gross' => false,
                'include_pph21_annual_reconciliation' => false,
            ],
            default => [
                'include_pph21_ter_gross' => false,
                'include_pph21_annual_reconciliation' => false,
            ],
        };
    }

    public static function inferTaxTreatmentCode(
        bool $includePph21TerGross,
        bool $includePph21AnnualReconciliation,
        bool $employerCostLine = false
    ): string {
        if ($employerCostLine) {
            return self::TAX_TREATMENT_EMPLOYER_DISPLAY_ONLY;
        }

        if ($includePph21TerGross && $includePph21AnnualReconciliation) {
            return self::TAX_TREATMENT_PPH21_TAXABLE_FULL;
        }

        if ($includePph21TerGross) {
            return self::TAX_TREATMENT_PPH21_TAXABLE_PARTIAL;
        }

        if ($includePph21AnnualReconciliation) {
            return self::TAX_TREATMENT_DEDUCTIBLE;
        }

        return self::TAX_TREATMENT_NON_OBJECT;
    }

    /**
     * Komponen pendapatan untuk menautkan pengajuan lembur ke slip (seed bawaan: code `upah_lembur`).
     */
    public static function resolveForOvertimePay(): ?self
    {
        $byCode = static::query()
            ->where('code', 'upah_lembur')
            ->where('is_active', true)
            ->first();
        if ($byCode) {
            return $byCode;
        }

        return static::query()
            ->where('kind', 'addition')
            ->where('category', 'overtime')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }
}
