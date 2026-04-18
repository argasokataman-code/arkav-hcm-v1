<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
    protected $table = 'hcm_salary_components';

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (empty($record->uuid)) {
                $record->uuid = (string) Str::uuid();
            }
        });
    }

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

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'kind',
        'category',
        'legal_basis',
        'legal_notes',
        'default_percent',
        'percent_basis',
        'include_bpjs_health_wage_base',
        'include_bpjs_tk_wage_base',
        'include_thr_calculation_base',
        'include_pph21_ter_gross',
        'include_pph21_annual_reconciliation',
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
            'default_percent' => 'decimal:4',
            'include_bpjs_health_wage_base' => 'boolean',
            'include_bpjs_tk_wage_base' => 'boolean',
            'include_thr_calculation_base' => 'boolean',
            'include_pph21_ter_gross' => 'boolean',
            'include_pph21_annual_reconciliation' => 'boolean',
            'subject_overtime_regulation' => 'boolean',
            'affects_net_pay' => 'boolean',
            'employer_cost_line' => 'boolean',
            'is_system_locked' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function categoriesForKind(string $kind): array
    {
        return match ($kind) {
            'addition' => self::ADDITION_CATEGORIES,
            'deduction' => self::DEDUCTION_CATEGORIES,
            default => [],
        };
    }

    public static function isValidCategoryForKind(string $kind, string $category): bool
    {
        return in_array($category, self::categoriesForKind($kind), true);
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
