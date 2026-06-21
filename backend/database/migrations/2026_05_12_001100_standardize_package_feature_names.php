<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Standardize feature_name per feature_code across all packages.
 *
 * Enterprise package was seeded with different feature_name values
 * (e.g. "Employee Directory" for employee_management, "Attendance Dashboard"
 * for attendance) compared to all other packages. This causes inconsistency
 * when the UI renders a unified feature catalog across packages.
 *
 * Canonical names per feature_code (sourced from saas_package_feature_catalog.php):
 *   attendance               → "Attendance"
 *   employee_management      → "Employee Management"
 *   employee_document_center → "Employee Document Center"
 *   leave_management         → "Leave Management"
 *   payroll                  → "Payroll"
 *   performance              → "Performance"
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $canonicalNames = [
        'attendance' => 'Attendance',
        'employee_management' => 'Employee Management',
        'employee_document_center' => 'Employee Document Center',
        'leave_management' => 'Leave Management',
        'payroll' => 'Payroll',
        'performance' => 'Performance',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('package_features')) {
            return;
        }

        $now = now();

        foreach ($this->canonicalNames as $code => $name) {
            DB::table('package_features')
                ->where('feature_code', $code)
                ->where('feature_name', '!=', $name)
                ->update(['feature_name' => $name, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Non-destructive: canonical names are source-of-truth going forward.
        // No rollback needed.
    }
};
