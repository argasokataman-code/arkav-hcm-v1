<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add selfie fields to attendance_records table
        if (Schema::hasTable('attendance_records')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                if (! Schema::hasColumn('attendance_records', 'selfie_path')) {
                    $table->string('selfie_path', 255)->nullable()->after('check_out_location_source');
                }
                if (! Schema::hasColumn('attendance_records', 'selfie_encrypted_hash')) {
                    $table->string('selfie_encrypted_hash', 255)->nullable()->after('selfie_path');
                }
            });
        }

        // Add encrypted NIK field to employee_profiles table
        if (Schema::hasTable('employee_profiles')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_profiles', 'nik_encrypted')) {
                    $table->text('nik_encrypted')->nullable()->after('nik');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendance_records')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                if (Schema::hasColumn('attendance_records', 'selfie_path')) {
                    $table->dropColumn('selfie_path');
                }
                if (Schema::hasColumn('attendance_records', 'selfie_encrypted_hash')) {
                    $table->dropColumn('selfie_encrypted_hash');
                }
            });
        }

        if (Schema::hasTable('employee_profiles')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('employee_profiles', 'nik_encrypted')) {
                    $table->dropColumn('nik_encrypted');
                }
            });
        }
    }
};

