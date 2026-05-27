<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill all known feature codes into feature_classifications.
     *
     * Tier values mirror what the runtime service currently derives from
     * docs/features/RUNTIME-FEATURE-CLASSIFICATION.md (Kategori 2 = mvp,
     * everything else = addon). After this runs, the DB becomes the de-facto
     * primary source for tier classification (DB overrides take precedence in
     * PackageFeatureCatalogRuntimeService::build()).
     *
     * Safe to run multiple times — uses INSERT IGNORE (updateOrIgnore pattern).
     */
    public function up(): void
    {
        // Codes in Kategori 2 of the runtime classification doc (mvp/core)
        $mvpCodes = [
            'max_employees',
            'employee_management',
            'employee_document_center',
            'employee_lifecycle',
            'attendance',
            'attendance_shift_scheduling',
            'leave_management',
            'holiday_calendar',
            'leave_approval_flow',
            'payroll',
            'payroll_components',
            'payroll_thr',
            'notifications',
            'trial_billing_dashboard',
            'tax_governance',
            'bpjs_governance',
        ];

        // All remaining known codes are addon tier
        $addonCodes = [
            'allowance_governance',
            'spt_masa_pph21',
            'attendance_correction',
            'overtime',
            'calendar_events',
            'promotion',
            'resignation',
            'termination',
            'goal_tracking',
            'performance_goal_tracking',
            'performance',
            'training',
            'ai_assistant',
            'asset_management',
            'tickets',
            'data_privacy',
            'notes',
            'faq',
        ];

        $now = now();

        $rows = [];

        foreach ($mvpCodes as $code) {
            $rows[] = [
                'feature_code' => $code,
                'tier'         => 'mvp',
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        foreach ($addonCodes as $code) {
            $rows[] = [
                'feature_code' => $code,
                'tier'         => 'addon',
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        foreach ($rows as $row) {
            DB::table('feature_classifications')
                ->where('feature_code', $row['feature_code'])
                ->delete();

            DB::table('feature_classifications')->insert($row);
        }
    }

    public function down(): void
    {
        $allCodes = [
            'max_employees', 'employee_management', 'employee_document_center',
            'employee_lifecycle', 'attendance', 'attendance_shift_scheduling',
            'leave_management', 'holiday_calendar', 'leave_approval_flow',
            'payroll', 'payroll_components', 'payroll_thr', 'notifications',
            'trial_billing_dashboard', 'tax_governance', 'bpjs_governance',
            'allowance_governance', 'spt_masa_pph21', 'attendance_correction',
            'overtime', 'calendar_events', 'promotion', 'resignation',
            'termination', 'goal_tracking', 'performance_goal_tracking',
            'performance', 'training', 'ai_assistant', 'asset_management',
            'tickets', 'data_privacy', 'notes', 'faq',
        ];

        DB::table('feature_classifications')
            ->whereIn('feature_code', $allCodes)
            ->delete();
    }
};
