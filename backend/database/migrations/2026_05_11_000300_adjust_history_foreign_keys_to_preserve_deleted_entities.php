<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $drops = [
            ['table' => 'hcm_bpjs_governance_policy_histories', 'constraint' => 'hcm_bpjs_hist_policy_uuid_fk'],
            ['table' => 'hcm_employee_allowance_policy_histories', 'constraint' => 'hcm_allow_policy_hist_policy_uuid_fk'],
            ['table' => 'hcm_employee_allowance_assignment_histories', 'constraint' => 'hcm_allow_assign_hist_assign_uuid_fk'],
        ];

        foreach ($drops as $drop) {
            if (! Schema::hasTable($drop['table']) || ! $this->foreignConstraintExists($drop['constraint'])) {
                continue;
            }

            Schema::table($drop['table'], function (Blueprint $table) use ($drop): void {
                $table->dropForeign($drop['constraint']);
            });
        }
    }

    public function down(): void
    {
        $historyUuidEdges = [
            [
                'table' => 'hcm_bpjs_governance_policy_histories',
                'column' => 'policy_uuid',
                'parent' => 'hcm_bpjs_governance_policies',
                'constraint' => 'hcm_bpjs_hist_policy_uuid_fk',
            ],
            [
                'table' => 'hcm_employee_allowance_policy_histories',
                'column' => 'policy_uuid',
                'parent' => 'hcm_employee_allowance_policies',
                'constraint' => 'hcm_allow_policy_hist_policy_uuid_fk',
            ],
            [
                'table' => 'hcm_employee_allowance_assignment_histories',
                'column' => 'assignment_uuid',
                'parent' => 'hcm_employee_allowance_assignments',
                'constraint' => 'hcm_allow_assign_hist_assign_uuid_fk',
            ],
        ];

        foreach ($historyUuidEdges as $edge) {
            if (! Schema::hasTable($edge['table']) || $this->foreignConstraintExists($edge['constraint'])) {
                continue;
            }

            Schema::table($edge['table'], function (Blueprint $table) use ($edge): void {
                $table->foreign($edge['column'], $edge['constraint'])
                    ->references('uuid')
                    ->on($edge['parent'])
                    ->nullOnDelete();
            });
        }
    }

    private function foreignConstraintExists(string $constraintName): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE()
               AND constraint_type = ?
               AND constraint_name = ?',
            ['FOREIGN KEY', $constraintName]
        );

        return ((int) ($row->aggregate ?? 0)) > 0;
    }

};
