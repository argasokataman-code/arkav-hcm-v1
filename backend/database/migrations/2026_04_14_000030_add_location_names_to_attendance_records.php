<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Add location name and address fields for check-in
            $table->string('check_in_location_name')->nullable()->after('check_in_longitude');
            $table->text('check_in_location_address')->nullable()->after('check_in_location_name');
            
            // Add location name and address fields for check-out
            $table->string('check_out_location_name')->nullable()->after('check_out_longitude');
            $table->text('check_out_location_address')->nullable()->after('check_out_location_name');
            
            // Add location source to distinguish between auto-detected and manual
            $table->enum('check_in_location_source', ['gps', 'manual', 'pending'])->default('gps')->after('check_in_location_address');
            $table->enum('check_out_location_source', ['gps', 'manual', 'pending'])->default('gps')->after('check_out_location_address');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_location_name',
                'check_in_location_address',
                'check_out_location_name',
                'check_out_location_address',
                'check_in_location_source',
                'check_out_location_source',
            ]);
        });
    }
};
