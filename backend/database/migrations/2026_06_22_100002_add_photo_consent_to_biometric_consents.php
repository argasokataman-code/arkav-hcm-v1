<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UU PDP M6: Profile photo is biometric data (Pasal 4 ayat 2).
     * Add explicit photo_consent field to employee_biometric_consents.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('employee_biometric_consents', 'photo_consent')) {
            Schema::table('employee_biometric_consents', function (Blueprint $table): void {
                $table->boolean('photo_consent')->default(false)->after('gps_consent');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_biometric_consents', 'photo_consent')) {
            Schema::table('employee_biometric_consents', function (Blueprint $table): void {
                $table->dropColumn('photo_consent');
            });
        }
    }
};
