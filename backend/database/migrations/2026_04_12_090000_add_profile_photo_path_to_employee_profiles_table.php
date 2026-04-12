<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_profiles', 'profile_photo_path')) {
                $table->string('profile_photo_path', 255)->nullable()->after('experience_items');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('employee_profiles', 'profile_photo_path')) {
                $table->dropColumn('profile_photo_path');
            }
        });
    }
};
