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
 * @property string $kind addition|deduction
 * @property string $category
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

    /** Valid values for source_module column */
    public const SOURCE_MODULE_BPJS      = 'bpjs';
    public const SOURCE_MODULE_ALLOWANCE = 'allowance';
    public const SOURCE_MODULE_PPH21     = 'pph21';
    public const SOURCE_MODULE_OVERTIME  = 'overtime';
    public const SOURCE_MODULE_THR       = 'thr';
    public const SOURCE_MODULE_PKWT      = 'pkwt';
    public const SOURCE_MODULE_SYSTEM    = 'system';

    public const CODE_BASIC_WAGE = 'upah_pokok';
    public const CODE_FIXED_ALLOWANCE = 'tunjangan_tetap';
    public const CODE_OVERTIME_PAY = 'upah_lembur';
    public const CODE_THR = 'thr';
    public const CODE_PKWT_COMPENSATION = 'kompensasi_pkwt';
    public const CODE_BPJS_HEALTH_EMPLOYEE = 'iuran_bpjs_kes_pekerja';
    public const CODE_BPJS_JHT_EMPLOYEE = 'iuran_jht_pekerja';
    public const CODE_BPJS_JP_EMPLOYEE = 'iuran_jp_pekerja';
    public const CODE_BPJS_HEALTH_EMPLOYER = 'iuran_bpjs_kes_pk';
    public const CODE_BPJS_JHT_EMPLOYER = 'iuran_jht_pk';
    public const CODE_BPJS_JP_EMPLOYER = 'iuran_jp_pk';
    public const CODE_BPJS_JKK_EMPLOYER = 'premi_jkk_pk';
    public const CODE_BPJS_JKM_EMPLOYER = 'premi_jkm_pk';
    public const CODE_PPH21_TER = 'pph21_ter';
    public const CODE_PPH21_RECONCILIATION = 'pph21_rekonsiliasi';


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
        'kind',
        'category',
        'category_uuid',
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
        'source_module',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'category_uuid' => 'string',
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
                'source_module' => 'string',
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
    * Komponen pendapatan untuk menautkan pengajuan lembur ke slip.
     */
    public static function resolveForOvertimePay(): ?self
    {
        $byCode = static::query()
            ->where('code', self::CODE_OVERTIME_PAY)
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

    public static function ensurePph21Components(int $companyId): void
    {
        static::ensureComponent(
            $companyId,
            self::CODE_PPH21_TER,
            'PPh Pasal 21 - pemotongan TER (bulanan)',
            'deduction',
            'pph21_ter',
            self::SOURCE_MODULE_PPH21,
            [
                'include_pph21_annual_reconciliation' => true,
                'tax_treatment_code' => self::TAX_TREATMENT_DEDUCTIBLE,
                'sort_order' => 330,
            ]
        );

        static::ensureComponent(
            $companyId,
            self::CODE_PPH21_RECONCILIATION,
            'PPh 21 - penyesuaian / rekonsiliasi (mis. Desember)',
            'deduction',
            'pph21_december_recon',
            self::SOURCE_MODULE_PPH21,
            [
                'include_pph21_annual_reconciliation' => true,
                'tax_treatment_code' => self::TAX_TREATMENT_DEDUCTIBLE,
                'sort_order' => 340,
            ]
        );
    }

    public static function ensureOvertimePayComponent(int $companyId): self
    {
        return static::ensureComponent(
            $companyId,
            self::CODE_OVERTIME_PAY,
            'Upah Kerja Lembur',
            'addition',
            'overtime',
            self::SOURCE_MODULE_OVERTIME,
            [
                'include_pph21_ter_gross' => true,
                'include_pph21_annual_reconciliation' => true,
                'subject_overtime_regulation' => true,
                'sort_order' => 60,
            ]
        );
    }

    public static function ensureThrComponent(int $companyId): self
    {
        return static::ensureComponent(
            $companyId,
            self::CODE_THR,
            'Tunjangan Hari Raya (THR)',
            'addition',
            'thr',
            self::SOURCE_MODULE_THR,
            [
                'include_pph21_ter_gross' => true,
                'include_pph21_annual_reconciliation' => true,
                'sort_order' => 70,
            ]
        );
    }

    public static function ensurePkwtCompensationComponent(int $companyId): self
    {
        return static::ensureComponent(
            $companyId,
            self::CODE_PKWT_COMPENSATION,
            'Kompensasi PKWT',
            'addition',
            'other_addition',
            self::SOURCE_MODULE_PKWT,
            [
                'include_pph21_ter_gross' => true,
                'include_pph21_annual_reconciliation' => true,
                'sort_order' => 135,
            ]
        );
    }

    /**
     * Daftarkan komponen gaji dari modul governance secara idempoten.
     * Jika komponen dengan `code` yang sama sudah ada, perbarui source_module dan is_system_locked saja.
     *
     * @param  int  $companyId
     * @param  string  $code   Kode unik komponen (snake_case)
     * @param  string  $name   Nama tampilan komponen
     * @param  string  $kind   'addition' | 'deduction'
     * @param  string  $category  Harus valid sesuai categoriesForKind($kind)
     * @param  string  $sourceModule  Salah satu SOURCE_MODULE_* constant
     * @param  array<string, mixed>  $extra  Override field lain (tax flags, legal_basis, dll)
     */
    public static function ensureComponent(
        int $companyId,
        string $code,
        string $name,
        string $kind,
        string $category,
        string $sourceModule,
        array $extra = []
    ): self {
        $targetCompanyId = $sourceModule === self::SOURCE_MODULE_ALLOWANCE
            ? $companyId
            : null;

        $existingQuery = static::query()->where('code', $code);
        if ($targetCompanyId !== null) {
            $existingQuery->where('company_id', $targetCompanyId);
        } else {
            $existingQuery->whereNull('company_id');
        }
        $existing = $existingQuery->first();

        if ($existing !== null) {
            // Update governance metadata only; never overwrite tenant customizations to name/flags
            $existing->source_module = $sourceModule;
            $existing->is_system_locked = true;

            $existingCompanyId = $existing->company_id !== null
                ? (int) $existing->company_id
                : null;
            if ($existingCompanyId !== $targetCompanyId) {
                $existing->company_id = $targetCompanyId;
            }

            $existing->save();

            return $existing;
        }

        $taxTreatmentCode = $extra['tax_treatment_code']
            ?? (isset($extra['include_pph21_ter_gross'])
                ? self::inferTaxTreatmentCode(
                    (bool) ($extra['include_pph21_ter_gross'] ?? false),
                    (bool) ($extra['include_pph21_annual_reconciliation'] ?? false),
                    (bool) ($extra['employer_cost_line'] ?? false)
                )
                : self::TAX_TREATMENT_NON_OBJECT);

        $taxFlags = self::taxFlagsForTreatment($taxTreatmentCode);

        return static::query()->create(array_merge([
            'company_id'                      => $targetCompanyId,
            'code'                            => $code,
            'name'                            => $name,
            'kind'                            => $kind,
            'category'                        => $category,
            'source_module'                   => $sourceModule,
            'is_system_locked'                => true,
            'is_active'                       => true,
            'sort_order'                      => 0,
            'include_bpjs_health_wage_base'   => false,
            'include_bpjs_tk_wage_base'       => false,
            'include_thr_calculation_base'    => false,
            'include_pph21_ter_gross'         => $taxFlags['include_pph21_ter_gross'],
            'include_pph21_annual_reconciliation' => $taxFlags['include_pph21_annual_reconciliation'],
            'tax_treatment_code'              => $taxTreatmentCode,
            'subject_overtime_regulation'     => false,
            'affects_net_pay'                 => true,
            'employer_cost_line'              => false,
        ], $extra, ['tax_treatment_code' => $taxTreatmentCode]));
    }
}
