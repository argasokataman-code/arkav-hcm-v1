<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, array{table:string,column:string,parentTable:string,parentColumn:string,constraint:string,onDelete:string}>
     */
    private array $constraints = [
        [
            'table' => 'hcm_bpjs_governance_rate_baselines',
            'column' => 'updated_by_user_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_bpjs_rate_base_upd_user_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_bpjs_governance_policy_histories',
            'column' => 'policy_uuid',
            'parentTable' => 'hcm_bpjs_governance_policies',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_bpjs_hist_policy_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_bpjs_governance_policy_histories',
            'column' => 'changed_by_user_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_bpjs_hist_changed_user_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_policies',
            'column' => 'company_uuid',
            'parentTable' => 'companies',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_policy_company_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_policies',
            'column' => 'created_by_user_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_policy_created_user_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_policies',
            'column' => 'updated_by_user_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_policy_updated_user_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_policy_histories',
            'column' => 'policy_uuid',
            'parentTable' => 'hcm_employee_allowance_policies',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_policy_hist_policy_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_policy_histories',
            'column' => 'changed_by_user_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_policy_hist_changed_user_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_assignments',
            'column' => 'company_uuid',
            'parentTable' => 'companies',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_assign_company_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_assignments',
            'column' => 'policy_uuid',
            'parentTable' => 'hcm_employee_allowance_policies',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_assign_policy_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_assignments',
            'column' => 'user_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_assign_user_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_assignments',
            'column' => 'created_by_user_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_assign_created_user_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_assignments',
            'column' => 'updated_by_user_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_assign_updated_user_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_assignment_histories',
            'column' => 'assignment_uuid',
            'parentTable' => 'hcm_employee_allowance_assignments',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_assign_hist_assign_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_allowance_assignment_histories',
            'column' => 'changed_by_user_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_allow_assign_hist_changed_user_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_document_categories',
            'column' => 'company_uuid',
            'parentTable' => 'companies',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_doc_cat_company_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_documents',
            'column' => 'company_uuid',
            'parentTable' => 'companies',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_doc_company_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_documents',
            'column' => 'employee_profile_uuid',
            'parentTable' => 'employee_profiles',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_doc_emp_profile_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_documents',
            'column' => 'category_uuid',
            'parentTable' => 'hcm_employee_document_categories',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_doc_category_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_employee_documents',
            'column' => 'uploaded_by_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_doc_uploaded_user_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_tax_governance_break_glass_requests',
            'column' => 'target_company_uuid',
            'parentTable' => 'companies',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_tax_bg_target_company_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_tax_governance_break_glass_requests',
            'column' => 'requested_by_user_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_tax_bg_req_user_uuid_fk',
            'onDelete' => 'set null',
        ],
        [
            'table' => 'hcm_tax_governance_break_glass_requests',
            'column' => 'approved_by_user_uuid',
            'parentTable' => 'users',
            'parentColumn' => 'uuid',
            'constraint' => 'hcm_tax_bg_app_user_uuid_fk',
            'onDelete' => 'set null',
        ],
    ];

    public function up(): void
    {
        foreach ($this->constraints as $edge) {
            if (! $this->hasTableAndColumn($edge['table'], $edge['column'])) {
                continue;
            }

            if ($this->foreignConstraintExists($edge['constraint'])) {
                continue;
            }

            $this->assertNoOrphans(
                $edge['table'],
                $edge['column'],
                $edge['parentTable'],
                $edge['parentColumn']
            );

            Schema::table($edge['table'], function (Blueprint $table) use ($edge): void {
                $foreign = $table->foreign($edge['column'], $edge['constraint'])
                    ->references($edge['parentColumn'])
                    ->on($edge['parentTable']);

                if ($edge['onDelete'] === 'set null') {
                    $foreign->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->constraints as $edge) {
            if (! Schema::hasTable($edge['table'])) {
                continue;
            }

            if (! $this->foreignConstraintExists($edge['constraint'])) {
                continue;
            }

            Schema::table($edge['table'], function (Blueprint $table) use ($edge): void {
                $table->dropForeign($edge['constraint']);
            });
        }
    }

    private function hasTableAndColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
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

    private function assertNoOrphans(
        string $childTable,
        string $childColumn,
        string $parentTable,
        string $parentColumn
    ): void {
        $count = DB::table($childTable.' as child')
            ->leftJoin($parentTable.' as parent', 'child.'.$childColumn, '=', 'parent.'.$parentColumn)
            ->whereNotNull('child.'.$childColumn)
            ->where('child.'.$childColumn, '!=', '')
            ->whereNull('parent.'.$parentColumn)
            ->count();

        if ($count > 0) {
            throw new RuntimeException(sprintf(
                'Cannot add FK %s: found %d orphan row(s) in %s.%s referencing %s.%s',
                $childTable.'_'.$childColumn,
                $count,
                $childTable,
                $childColumn,
                $parentTable,
                $parentColumn
            ));
        }
    }
};
