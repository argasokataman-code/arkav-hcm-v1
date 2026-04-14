<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->foreignId('province_id')->nullable()->after('address')->constrained('wilayah_provinces')->nullOnDelete();
            $table->foreignId('regency_id')->nullable()->after('province_id')->constrained('wilayah_regencies')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('regency_id')->constrained('wilayah_districts')->nullOnDelete();
            $table->foreignId('village_id')->nullable()->after('district_id')->constrained('wilayah_villages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('village_id');
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('regency_id');
            $table->dropConstrainedForeignId('province_id');
        });
    }
};