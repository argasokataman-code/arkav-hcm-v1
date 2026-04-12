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
        // Note: hcm_salary_components.code already has unique constraint
        // Note: policies table doesn't have a code column (only name, description)  
        
        // Add check constraints for status enums to enforce valid states
        // These ensure only valid statuses are allowed at database level
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql') {
            // Check constraints on MySQL
            if (!$this->constraintExists('hcm_resignations', 'check_resignation_status')) {
                try {
                    DB::statement('ALTER TABLE hcm_resignations ADD CONSTRAINT check_resignation_status CHECK (status IN (\'pending\', \'approved\', \'cancelled\'))');
                } catch (\Exception $e) {
                    // Constraint already exists, silently continue
                }
            }
            
            if (!$this->constraintExists('hcm_terminations', 'check_termination_status')) {
                try {
                    DB::statement('ALTER TABLE hcm_terminations ADD CONSTRAINT check_termination_status CHECK (status IN (\'pending\', \'approved\', \'finalized\', \'cancelled\'))');
                } catch (\Exception $e) {
                    // Constraint already exists, silently continue
                }
            }
        }
        // SQLite handles check constraints via column definitions, not ALTER TABLE
        // Skip for SQLite as it's a test database
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE hcm_resignations DROP CONSTRAINT IF EXISTS check_resignation_status');
            } catch (\Exception $e) {
                // Constraint doesn't exist, silently continue
            }
            
            try {
                DB::statement('ALTER TABLE hcm_terminations DROP CONSTRAINT IF EXISTS check_termination_status');
            } catch (\Exception $e) {
                // Constraint doesn't exist, silently continue
            }
        }
        // SQLite doesn't require cleanup for check constraints
    }

    /**
     * Check if a constraint exists on a table
     */
    private function constraintExists(string $table, string $constraint): bool
    {
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'sqlite') {
            // SQLite doesn't have an easy way to check for constraints
            // so we'll try to add it and catch the exception
            return false;
        }
        
        if ($driver === 'mysql') {
            return collect(DB::select("
                SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
            ", [$table, $constraint]))->isNotEmpty();
        }
        
        return false;
    }
};
