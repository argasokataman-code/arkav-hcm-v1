<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Disable foreign key constraints during data cleanup and migration
        Schema::disableForeignKeyConstraints();

        try {
            // ============================================================================
            // PHASE 1: CLEANUP ORPHAN RECORDS
            // ============================================================================
            
            // Remove orphan records from child tables before adding constraints
            $this->cleanupOrphanRecords();

            // ============================================================================
            // PHASE 2: ADD MISSING FOREIGN KEY CONSTRAINTS
            // ============================================================================

            // 1. Designations -> Departments
            // NOTE: This FK already exists from table creation (foreignId()->constrained())
            // Skip to avoid duplicate constraint error

            // 2. Domains -> Company (if company_id column exists)
            if (Schema::hasColumn('domains', 'company_id') && 
                !$this->constraintExists('domains', 'domains_company_id_foreign')) {
                Schema::table('domains', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                });
            }

            // 3. Employee Bank Accounts -> Employee Profiles
            if (Schema::hasColumn('employee_bank_accounts', 'employee_id') &&
                !$this->constraintExists('employee_bank_accounts', 'employee_bank_accounts_employee_id_foreign')) {
                Schema::table('employee_bank_accounts', function (Blueprint $table) {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employee_profiles')
                        ->cascadeOnDelete();
                });
            }

            // 4. Employee Benefits -> Employee Profiles
            if (Schema::hasColumn('employee_benefits', 'employee_id') &&
                !$this->constraintExists('employee_benefits', 'employee_benefits_employee_id_foreign')) {
                Schema::table('employee_benefits', function (Blueprint $table) {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employee_profiles')
                        ->cascadeOnDelete();
                });
            }

            // 5. Employee Compensations -> Employee Profiles
            if (Schema::hasColumn('employee_compensations', 'employee_id') &&
                !$this->constraintExists('employee_compensations', 'employee_compensations_employee_id_foreign')) {
                Schema::table('employee_compensations', function (Blueprint $table) {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employee_profiles')
                        ->cascadeOnDelete();
                });
            }

            // 6. Employee Contracts -> Employee Profiles
            if (Schema::hasColumn('employee_contracts', 'employee_id') &&
                !$this->constraintExists('employee_contracts', 'employee_contracts_employee_id_foreign')) {
                Schema::table('employee_contracts', function (Blueprint $table) {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employee_profiles')
                        ->cascadeOnDelete();
                });
            }

            // 7. Employee Educations -> Employee Profiles
            if (Schema::hasColumn('employee_educations', 'employee_id') &&
                !$this->constraintExists('employee_educations', 'employee_educations_employee_id_foreign')) {
                Schema::table('employee_educations', function (Blueprint $table) {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employee_profiles')
                        ->cascadeOnDelete();
                });
            }

            // 8. Employee Emergency Contacts -> Employee Profiles
            if (Schema::hasColumn('employee_emergency_contacts', 'employee_id') &&
                !$this->constraintExists('employee_emergency_contacts', 'employee_emergency_contacts_employee_id_foreign')) {
                Schema::table('employee_emergency_contacts', function (Blueprint $table) {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employee_profiles')
                        ->cascadeOnDelete();
                });
            }

            // 9. Employee Employment History -> Employee Profiles
            if (Schema::hasColumn('employee_employment_history', 'employee_id') &&
                !$this->constraintExists('employee_employment_history', 'employee_employment_history_employee_id_foreign')) {
                Schema::table('employee_employment_history', function (Blueprint $table) {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employee_profiles')
                        ->cascadeOnDelete();
                });
            }

            // 10. Employee Experiences -> Employee Profiles
            if (Schema::hasColumn('employee_experiences', 'employee_id') &&
                !$this->constraintExists('employee_experiences', 'employee_experiences_employee_id_foreign')) {
                Schema::table('employee_experiences', function (Blueprint $table) {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employee_profiles')
                        ->cascadeOnDelete();
                });
            }

            // 11. Employee Tax Profiles -> Employee Profiles
            if (Schema::hasColumn('employee_tax_profiles', 'employee_id') &&
                !$this->constraintExists('employee_tax_profiles', 'employee_tax_profiles_employee_id_foreign')) {
                Schema::table('employee_tax_profiles', function (Blueprint $table) {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employee_profiles')
                        ->cascadeOnDelete();
                });
            }

            // 12. Employee Assignments -> Employee Profiles (for employee_id)
            if (Schema::hasColumn('employee_assignments', 'employee_id') &&
                !$this->constraintExists('employee_assignments', 'employee_assignments_employee_id_foreign')) {
                Schema::table('employee_assignments', function (Blueprint $table) {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employee_profiles')
                        ->cascadeOnDelete();
                });
            }

            // 13. Employee Assignments -> Departments
            if (Schema::hasColumn('employee_assignments', 'department_id') &&
                !$this->constraintExists('employee_assignments', 'employee_assignments_department_id_foreign')) {
                Schema::table('employee_assignments', function (Blueprint $table) {
                    $table->foreign('department_id')
                        ->references('id')
                        ->on('departments')
                        ->nullOnDelete();
                });
            }

            // 14. Employee Assignments -> Designations
            if (Schema::hasColumn('employee_assignments', 'designation_id') &&
                !$this->constraintExists('employee_assignments', 'employee_assignments_designation_id_foreign')) {
                Schema::table('employee_assignments', function (Blueprint $table) {
                    $table->foreign('designation_id')
                        ->references('id')
                        ->on('designations')
                        ->nullOnDelete();
                });
            }

            // 15. Employee Assignments -> Teams
            if (Schema::hasColumn('employee_assignments', 'team_id') &&
                !$this->constraintExists('employee_assignments', 'employee_assignments_team_id_foreign')) {
                Schema::table('employee_assignments', function (Blueprint $table) {
                    $table->foreign('team_id')
                        ->references('id')
                        ->on('teams')
                        ->nullOnDelete();
                });
            }

            // 16. Employee Profiles -> Departments
            if (Schema::hasColumn('employee_profiles', 'department_id') &&
                !$this->constraintExists('employee_profiles', 'employee_profiles_department_id_foreign')) {
                Schema::table('employee_profiles', function (Blueprint $table) {
                    $table->foreign('department_id')
                        ->references('id')
                        ->on('departments')
                        ->nullOnDelete();
                });
            }

            // 17. Employee Profiles -> Designations
            if (Schema::hasColumn('employee_profiles', 'designation_id') &&
                !$this->constraintExists('employee_profiles', 'employee_profiles_designation_id_foreign')) {
                Schema::table('employee_profiles', function (Blueprint $table) {
                    $table->foreign('designation_id')
                        ->references('id')
                        ->on('designations')
                        ->nullOnDelete();
                });
            }

            // 18. HCM Training Participants -> HCM Trainings
            if (Schema::hasColumn('hcm_training_participants', 'training_id') &&
                !$this->constraintExists('hcm_training_participants', 'hcm_training_participants_training_id_foreign')) {
                Schema::table('hcm_training_participants', function (Blueprint $table) {
                    $table->foreign('training_id')
                        ->references('id')
                        ->on('hcm_trainings')
                        ->cascadeOnDelete();
                });
            }

            // 19. HCM Training Participants -> Users
            if (Schema::hasColumn('hcm_training_participants', 'user_id') &&
                !$this->constraintExists('hcm_training_participants', 'hcm_training_participants_user_id_foreign')) {
                Schema::table('hcm_training_participants', function (Blueprint $table) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                });
            }

            // 20. Ticket Attachments -> Tickets
            if (Schema::hasColumn('ticket_attachments', 'ticket_id') &&
                !$this->constraintExists('ticket_attachments', 'ticket_attachments_ticket_id_foreign')) {
                Schema::table('ticket_attachments', function (Blueprint $table) {
                    $table->foreign('ticket_id')
                        ->references('id')
                        ->on('tickets')
                        ->cascadeOnDelete();
                });
            }

            // 21. Ticket Attachments -> Users
            if (Schema::hasColumn('ticket_attachments', 'user_id') &&
                !$this->constraintExists('ticket_attachments', 'ticket_attachments_user_id_foreign')) {
                Schema::table('ticket_attachments', function (Blueprint $table) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                });
            }

            // 22. Ticket Comments -> Tickets
            if (Schema::hasColumn('ticket_comments', 'ticket_id') &&
                !$this->constraintExists('ticket_comments', 'ticket_comments_ticket_id_foreign')) {
                Schema::table('ticket_comments', function (Blueprint $table) {
                    $table->foreign('ticket_id')
                        ->references('id')
                        ->on('tickets')
                        ->cascadeOnDelete();
                });
            }

            // 23. Ticket Comments -> Users
            if (Schema::hasColumn('ticket_comments', 'user_id') &&
                !$this->constraintExists('ticket_comments', 'ticket_comments_user_id_foreign')) {
                Schema::table('ticket_comments', function (Blueprint $table) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                });
            }

            // 24. Ticket Assignment Histories (complex - check existing constraints first)
            if (Schema::hasColumn('ticket_assignment_histories', 'ticket_id') &&
                !$this->constraintExists('ticket_assignment_histories', 'ticket_assignment_histories_ticket_id_foreign')) {
                Schema::table('ticket_assignment_histories', function (Blueprint $table) {
                    $table->foreign('ticket_id')
                        ->references('id')
                        ->on('tickets')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('ticket_assignment_histories', 'actor_user_id') &&
                !$this->constraintExists('ticket_assignment_histories', 'ticket_assignment_histories_actor_user_id_foreign')) {
                Schema::table('ticket_assignment_histories', function (Blueprint $table) {
                    $table->foreign('actor_user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                });
            }

            // ============================================================================
            // PHASE 3: VERIFY DATA INTEGRITY
            // ============================================================================
            
            $this->verifyIntegrity();

        } finally {
            // Always re-enable foreign key constraints
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Note: This migration only adds constraints, so down() would remove them
        // But we keep constraints intact as they enforce data integrity
        // If you need to rollback, manually drop constraints or use a dedicated migration
    }

    /**
     * Cleanup orphan records that don't have valid parent records
     */
    private function cleanupOrphanRecords(): void
    {
        // Designations with invalid department_id
        DB::table('designations')
            ->whereNotNull('department_id')
            ->whereNotIn('department_id', DB::table('departments')->pluck('id'))
            ->update(['department_id' => null]);

        // Employee Bank Accounts with invalid employee_id
        DB::table('employee_bank_accounts')
            ->whereNotIn('employee_id', DB::table('employee_profiles')->pluck('id'))
            ->delete();

        // Employee Benefits with invalid employee_id
        DB::table('employee_benefits')
            ->whereNotIn('employee_id', DB::table('employee_profiles')->pluck('id'))
            ->delete();

        // Employee Compensations with invalid employee_id
        DB::table('employee_compensations')
            ->whereNotIn('employee_id', DB::table('employee_profiles')->pluck('id'))
            ->delete();

        // Employee Contracts with invalid employee_id
        DB::table('employee_contracts')
            ->whereNotIn('employee_id', DB::table('employee_profiles')->pluck('id'))
            ->delete();

        // Employee Educations with invalid employee_id
        DB::table('employee_educations')
            ->whereNotIn('employee_id', DB::table('employee_profiles')->pluck('id'))
            ->delete();

        // Employee Emergency Contacts with invalid employee_id
        DB::table('employee_emergency_contacts')
            ->whereNotIn('employee_id', DB::table('employee_profiles')->pluck('id'))
            ->delete();

        // Employee Employment History with invalid employee_id
        DB::table('employee_employment_history')
            ->whereNotIn('employee_id', DB::table('employee_profiles')->pluck('id'))
            ->delete();

        // Employee Experiences with invalid employee_id
        DB::table('employee_experiences')
            ->whereNotIn('employee_id', DB::table('employee_profiles')->pluck('id'))
            ->delete();

        // Employee Tax Profiles with invalid employee_id
        DB::table('employee_tax_profiles')
            ->whereNotIn('employee_id', DB::table('employee_profiles')->pluck('id'))
            ->delete();

        // Employee Assignments with invalid references
        DB::table('employee_assignments')
            ->whereNotIn('employee_id', DB::table('employee_profiles')->pluck('id'))
            ->delete();

        DB::table('employee_assignments')
            ->whereNotNull('department_id')
            ->whereNotIn('department_id', DB::table('departments')->pluck('id'))
            ->update(['department_id' => null]);

        DB::table('employee_assignments')
            ->whereNotNull('designation_id')
            ->whereNotIn('designation_id', DB::table('designations')->pluck('id'))
            ->update(['designation_id' => null]);

        DB::table('employee_assignments')
            ->whereNotNull('team_id')
            ->whereNotIn('team_id', DB::table('teams')->pluck('id'))
            ->update(['team_id' => null]);

        // Employee Profiles with invalid department_id or designation_id
        DB::table('employee_profiles')
            ->whereNotNull('department_id')
            ->whereNotIn('department_id', DB::table('departments')->pluck('id'))
            ->update(['department_id' => null]);

        DB::table('employee_profiles')
            ->whereNotNull('designation_id')
            ->whereNotIn('designation_id', DB::table('designations')->pluck('id'))
            ->update(['designation_id' => null]);

        // HCM Training Participants with invalid references
        if (Schema::hasTable('hcm_training_participants')) {
            DB::table('hcm_training_participants')
                ->whereNotIn('training_id', DB::table('hcm_trainings')->pluck('id'))
                ->delete();

            DB::table('hcm_training_participants')
                ->whereNotIn('user_id', DB::table('users')->pluck('id'))
                ->delete();
        }

        // Ticket Attachments with invalid references
        if (Schema::hasTable('ticket_attachments')) {
            DB::table('ticket_attachments')
                ->whereNotIn('ticket_id', DB::table('tickets')->pluck('id'))
                ->delete();

            DB::table('ticket_attachments')
                ->whereNotIn('user_id', DB::table('users')->pluck('id'))
                ->delete();
        }

        // Ticket Comments with invalid references
        if (Schema::hasTable('ticket_comments')) {
            DB::table('ticket_comments')
                ->whereNotIn('ticket_id', DB::table('tickets')->pluck('id'))
                ->delete();

            DB::table('ticket_comments')
                ->whereNotIn('user_id', DB::table('users')->pluck('id'))
                ->delete();
        }

        // Ticket Assignment Histories with invalid references
        if (Schema::hasTable('ticket_assignment_histories')) {
            DB::table('ticket_assignment_histories')
                ->whereNotIn('ticket_id', DB::table('tickets')->pluck('id'))
                ->delete();

            DB::table('ticket_assignment_histories')
                ->whereNotIn('actor_user_id', DB::table('users')->pluck('id'))
                ->delete();
        }
    }

    /**
     * Check if a constraint already exists
     */
    private function constraintExists(string $table, string $constraintName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ? AND CONSTRAINT_NAME = ?",
            [$table, env('DB_DATABASE'), $constraintName]
        );

        return count($constraints) > 0;
    }

    /**
     * Verify that all foreign keys are properly set up
     */
    private function verifyIntegrity(): void
    {
        // Log integrity check status
        $issues = [];

        // Check for orphan designations
        $orphanDesignations = DB::table('designations')
            ->whereNotNull('department_id')
            ->whereNotIn('department_id', DB::table('departments')->pluck('id'))
            ->count();
        if ($orphanDesignations > 0) {
            $issues[] = "Found $orphanDesignations designations with invalid department_id";
        }

        // Check for orphan employee records
        $orphanBankAccounts = DB::table('employee_bank_accounts')
            ->whereNotIn('employee_id', DB::table('employee_profiles')->pluck('id'))
            ->count();
        if ($orphanBankAccounts > 0) {
            $issues[] = "Found $orphanBankAccounts employee_bank_accounts with invalid employee_id";
        }

        if (count($issues) > 0) {
            \Log::warning('Foreign Key Integrity Issues Found:', $issues);
        }

        \Log::info('Foreign key constraints migration completed successfully', [
            'timestamp' => now(),
            'issues_found' => count($issues),
        ]);
    }
};
