<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index('work_date', 'attendance_records_work_date_index');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->fullText(['name', 'email'], 'users_name_email_fulltext');
            });
        }
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('attendance_records_work_date_index');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropFullText(['name', 'email']);
            });
        }
    }
};
