<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_profiles', 'address_detail')) {
                $table->text('address_detail')->nullable()->after('address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('employee_profiles', 'address_detail')) {
                $table->dropColumn('address_detail');
            }
        });
    }
};
