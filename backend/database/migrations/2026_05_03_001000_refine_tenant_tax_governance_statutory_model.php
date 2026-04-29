<?php

use App\Models\HcmSalaryComponent;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hcm_tax_governance_policies')) {
            Schema::table('hcm_tax_governance_policies', function (Blueprint $table): void {
                if (! Schema::hasColumn('hcm_tax_governance_policies', 'draft_fingerprint')) {
                    $table->string('draft_fingerprint', 120)->nullable()->after('status');
                }
            });

            Schema::table('hcm_tax_governance_policies', function (Blueprint $table): void {
                $table->index(['company_id', 'status', 'effective_start_date'], 'hcm_tax_policy_company_status_effective_idx');
                $table->index(['company_id', 'effective_start_date', 'effective_end_date'], 'hcm_tax_policy_company_effective_window_idx');
                $table->unique(['company_id', 'draft_fingerprint'], 'hcm_tax_policy_company_draft_fp_uq');
            });

            DB::table('hcm_tax_governance_policies')
                ->select(['id', 'effective_start_date', 'effective_end_date', 'rules', 'rate_schedules'])
                ->orderBy('id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        $rules = json_decode((string) $row->rules, true);
                        if (! is_array($rules)) {
                            $rules = [];
                        }

                        $regulationReference = (string) ($rules['regulationReference'] ?? $rules['regulation_reference'] ?? 'PP 58/2023 & PMK 168/PMK.03/2023');
                        $regulationSourceType = (string) ($rules['regulationSourceType'] ?? 'ministry_regulation');
                        $normalizedRules = array_merge($rules, [
                            'scheme' => 'STATUTORY_PPH21',
                            'currency' => 'IDR',
                            'country' => 'ID',
                            'calculationMethod' => $rules['calculationMethod'] ?? 'monthly_ter_lookup',
                            'regulationReference' => $regulationReference,
                            'regulationSourceType' => $regulationSourceType,
                            'source' => $rules['source'] ?? 'tenant_statutory_policy',
                        ]);

                        $rateSchedules = json_decode((string) $row->rate_schedules, true);
                        if (! is_array($rateSchedules)) {
                            $rateSchedules = [];
                        }

                        $normalizedSchedules = [];
                        foreach ($rateSchedules as $schedule) {
                            if (! is_array($schedule)) {
                                continue;
                            }

                            if (isset($schedule['category']) && isset($schedule['calculationMode'])) {
                                $normalizedSchedules[] = $schedule;
                                continue;
                            }

                            $category = (string) ($schedule['category'] ?? $schedule['bracket'] ?? $schedule['lookupTableCode'] ?? 'A');
                            $normalizedSchedules[] = [
                                'category' => $category,
                                'lookupTableCode' => $category,
                                'calculationMode' => 'ter_lookup',
                                'effectiveStartDate' => $schedule['effectiveStartDate'] ?? $row->effective_start_date,
                                'effectiveEndDate' => $schedule['effectiveEndDate'] ?? $row->effective_end_date,
                                'regulationReference' => $schedule['regulationReference'] ?? $regulationReference,
                                'regulationSourceType' => $schedule['regulationSourceType'] ?? $regulationSourceType,
                                'legacyRatePercent' => $schedule['rate'] ?? $schedule['percent'] ?? $schedule['percentage'] ?? null,
                                'upperBound' => $schedule['upperBound'] ?? null,
                            ];
                        }

                        if ($normalizedSchedules === []) {
                            foreach (['A', 'B', 'C'] as $category) {
                                $normalizedSchedules[] = [
                                    'category' => $category,
                                    'lookupTableCode' => $category,
                                    'calculationMode' => 'ter_lookup',
                                    'effectiveStartDate' => $row->effective_start_date,
                                    'effectiveEndDate' => $row->effective_end_date,
                                    'regulationReference' => $regulationReference,
                                    'regulationSourceType' => $regulationSourceType,
                                ];
                            }
                        }

                        DB::table('hcm_tax_governance_policies')
                            ->where('id', $row->id)
                            ->update([
                                'rules' => json_encode($normalizedRules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'rate_schedules' => json_encode($normalizedSchedules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            ]);
                    }
                });
        }

        if (Schema::hasTable('hcm_salary_components')) {
            Schema::table('hcm_salary_components', function (Blueprint $table): void {
                if (! Schema::hasColumn('hcm_salary_components', 'tax_treatment_code')) {
                    $table->string('tax_treatment_code', 50)->nullable()->after('include_pph21_annual_reconciliation');
                    $table->index('tax_treatment_code', 'hcm_salary_components_tax_treatment_idx');
                }
            });

            DB::table('hcm_salary_components')
                ->select(['id', 'include_pph21_ter_gross', 'include_pph21_annual_reconciliation', 'employer_cost_line'])
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $code = HcmSalaryComponent::inferTaxTreatmentCode(
                            (bool) $row->include_pph21_ter_gross,
                            (bool) $row->include_pph21_annual_reconciliation,
                            (bool) $row->employer_cost_line,
                        );

                        DB::table('hcm_salary_components')
                            ->where('id', $row->id)
                            ->update(['tax_treatment_code' => $code]);
                    }
                });
        }

        $this->backfillMissingEmployeeTaxProfiles();
        $this->backfillMissingPayrollPolicyLinks();
    }

    public function down(): void
    {
        if (Schema::hasTable('hcm_tax_governance_policies')) {
            Schema::table('hcm_tax_governance_policies', function (Blueprint $table): void {
                $table->dropUnique('hcm_tax_policy_company_draft_fp_uq');
                $table->dropIndex('hcm_tax_policy_company_status_effective_idx');
                $table->dropIndex('hcm_tax_policy_company_effective_window_idx');
                if (Schema::hasColumn('hcm_tax_governance_policies', 'draft_fingerprint')) {
                    $table->dropColumn('draft_fingerprint');
                }
            });
        }

        if (Schema::hasTable('hcm_salary_components') && Schema::hasColumn('hcm_salary_components', 'tax_treatment_code')) {
            Schema::table('hcm_salary_components', function (Blueprint $table): void {
                $table->dropIndex('hcm_salary_components_tax_treatment_idx');
                $table->dropColumn('tax_treatment_code');
            });
        }
    }

    private function backfillMissingEmployeeTaxProfiles(): void
    {
        if (! Schema::hasTable('employee_profiles') || ! Schema::hasTable('employee_tax_profiles')) {
            return;
        }

        $employeeRows = DB::table('employee_profiles as ep')
            ->leftJoin('employee_tax_profiles as etp', 'etp.employee_id', '=', 'ep.id')
            ->whereNull('etp.id')
            ->select(['ep.id', 'ep.hire_date', 'ep.created_at'])
            ->orderBy('ep.id')
            ->get();

        foreach ($employeeRows as $employee) {
            $effectiveDate = $employee->hire_date
                ? Carbon::parse((string) $employee->hire_date)->toDateString()
                : ($employee->created_at
                    ? Carbon::parse((string) $employee->created_at)->toDateString()
                    : now()->startOfMonth()->toDateString());

            DB::table('employee_tax_profiles')->insert([
                'employee_id' => $employee->id,
                'npwp' => null,
                'tax_status' => null,
                'ptkp_status' => null,
                'effective_date' => $effectiveDate,
                'end_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function backfillMissingPayrollPolicyLinks(): void
    {
        if (! Schema::hasTable('hcm_payroll_runs') || ! Schema::hasTable('hcm_tax_governance_policies') || ! Schema::hasTable('hcm_payroll_periods')) {
            return;
        }

        $runs = DB::table('hcm_payroll_runs as pr')
            ->join('hcm_payroll_periods as pp', 'pp.id', '=', 'pr.hcm_payroll_period_id')
            ->whereNull('pr.hcm_tax_governance_policy_id')
            ->select(['pr.id', 'pr.company_id', 'pp.period_year', 'pp.period_month'])
            ->orderBy('pr.id')
            ->get();

        foreach ($runs as $run) {
            $asOf = Carbon::create((int) $run->period_year, (int) $run->period_month, 1)->endOfMonth()->toDateString();
            $policy = DB::table('hcm_tax_governance_policies')
                ->where('company_id', $run->company_id)
                ->whereDate('effective_start_date', '<=', $asOf)
                ->where(function ($query) use ($asOf): void {
                    $query->whereNull('effective_end_date')
                        ->orWhereDate('effective_end_date', '>=', $asOf);
                })
                ->orderByRaw("case status when 'published' then 1 when 'approved' then 2 when 'submitted' then 3 when 'draft' then 4 else 5 end")
                ->orderByDesc('effective_start_date')
                ->orderByDesc('version')
                ->first(['id', 'version']);

            if (! $policy) {
                continue;
            }

            DB::table('hcm_payroll_runs')
                ->where('id', $run->id)
                ->update([
                    'hcm_tax_governance_policy_id' => $policy->id,
                    'hcm_tax_governance_policy_version' => $policy->version,
                ]);
        }
    }
};