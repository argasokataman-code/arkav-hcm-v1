<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cleanup legacy salary-component rows that were mistakenly tagged as
 * source_module='allowance' (they are generic system templates, not rows from
 * Allowance Governance module). Also removes auto-generated bridge payroll
 * items that were created by the allowance auto-link self-healing logic
 * because of that wrong tag.
 *
 * Affected legacy codes:
 *   - tunjangan_tetap
 *   - tunjangan_tetap_jabatan
 *   - tunjangan_tetap_transport
 *   - tunjangan_tidak_tetap
 *   - uang_makan_tetap
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacyCodes = [
            'tunjangan_tetap',
            'tunjangan_tetap_jabatan',
            'tunjangan_tetap_transport',
            'tunjangan_tidak_tetap',
            'uang_makan_tetap',
        ];

        $componentIds = DB::table('hcm_salary_components')
            ->whereIn('code', $legacyCodes)
            ->pluck('id')
            ->all();

        if ($componentIds !== []) {
            // Drop only auto-generated bridge payroll items (notes signature).
            // Manually-curated rows are preserved.
            DB::table('hcm_payroll_items')
                ->whereIn('hcm_salary_component_id', $componentIds)
                ->where('notes', 'Auto-linked from allowance governance.')
                ->delete();

            DB::table('hcm_salary_components')
                ->whereIn('id', $componentIds)
                ->update(['source_module' => null]);
        }
    }

    public function down(): void
    {
        // No-op: re-tagging legacy templates as allowance would reintroduce
        // the dropdown-leak bug, so the rollback is intentionally inert.
    }
};
