<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->timestamp('data_disclosed_at')->nullable()->after('profile_photo_path');
            $table->string('data_disclosed_by_uuid', 36)->nullable()->after('data_disclosed_at');
            $table->string('data_disclosed_ip', 45)->nullable()->after('data_disclosed_by_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            $table->dropColumn(['data_disclosed_at', 'data_disclosed_by_uuid', 'data_disclosed_ip']);
        });
    }
};
