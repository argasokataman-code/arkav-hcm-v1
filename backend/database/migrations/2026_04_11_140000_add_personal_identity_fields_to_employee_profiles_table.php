<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        $hasNik = Schema::hasColumn('employee_profiles', 'nik');
        $hasPlaceOfBirth = Schema::hasColumn('employee_profiles', 'place_of_birth');
        $hasDateOfBirth = Schema::hasColumn('employee_profiles', 'date_of_birth');
        $hasGender = Schema::hasColumn('employee_profiles', 'gender');
        $hasMaritalStatus = Schema::hasColumn('employee_profiles', 'marital_status');
        $hasReligion = Schema::hasColumn('employee_profiles', 'religion');
        $hasNationality = Schema::hasColumn('employee_profiles', 'nationality');

        Schema::table('employee_profiles', function (Blueprint $table) use (
            $hasNik,
            $hasPlaceOfBirth,
            $hasDateOfBirth,
            $hasGender,
            $hasMaritalStatus,
            $hasReligion,
            $hasNationality
        ): void {
            if (! $hasNik) {
                $table->string('nik', 32)->nullable()->after('user_id');
            }
            if (! $hasPlaceOfBirth) {
                $table->string('place_of_birth', 150)->nullable()->after('address');
            }
            if (! $hasDateOfBirth) {
                $table->date('date_of_birth')->nullable()->after('place_of_birth');
            }
            if (! $hasGender) {
                $table->string('gender', 20)->nullable()->after('date_of_birth');
            }
            if (! $hasMaritalStatus) {
                $table->string('marital_status', 50)->nullable()->after('gender');
            }
            if (! $hasReligion) {
                $table->string('religion', 50)->nullable()->after('marital_status');
            }
            if (! $hasNationality) {
                $table->string('nationality', 100)->nullable()->after('religion');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        Schema::table('employee_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('employee_profiles', 'nationality')) {
                $table->dropColumn('nationality');
            }
            if (Schema::hasColumn('employee_profiles', 'religion')) {
                $table->dropColumn('religion');
            }
            if (Schema::hasColumn('employee_profiles', 'marital_status')) {
                $table->dropColumn('marital_status');
            }
            if (Schema::hasColumn('employee_profiles', 'gender')) {
                $table->dropColumn('gender');
            }
            if (Schema::hasColumn('employee_profiles', 'date_of_birth')) {
                $table->dropColumn('date_of_birth');
            }
            if (Schema::hasColumn('employee_profiles', 'place_of_birth')) {
                $table->dropColumn('place_of_birth');
            }
            if (Schema::hasColumn('employee_profiles', 'nik')) {
                $table->dropColumn('nik');
            }
        });
    }
};
