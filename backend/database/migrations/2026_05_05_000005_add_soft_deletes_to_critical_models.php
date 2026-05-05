<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // users
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // employee_profiles
        Schema::table('employee_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_profiles', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // employee_tax_profiles
        if (Schema::hasTable('employee_tax_profiles')) {
            Schema::table('employee_tax_profiles', function (Blueprint $table): void {
                if (! Schema::hasColumn('employee_tax_profiles', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // employee_benefits
        if (Schema::hasTable('employee_benefits')) {
            Schema::table('employee_benefits', function (Blueprint $table): void {
                if (! Schema::hasColumn('employee_benefits', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // attendance_records
        if (Schema::hasTable('attendance_records')) {
            Schema::table('attendance_records', function (Blueprint $table): void {
                if (! Schema::hasColumn('attendance_records', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        // ai_chat_logs
        if (Schema::hasTable('ai_chat_logs')) {
            Schema::table('ai_chat_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('ai_chat_logs', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'users',
            'employee_profiles',
            'employee_tax_profiles',
            'employee_benefits',
            'attendance_records',
            'ai_chat_logs',
        ];

        foreach ($tables as $tbl) {
            if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'deleted_at')) {
                Schema::table($tbl, function (Blueprint $table): void {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
