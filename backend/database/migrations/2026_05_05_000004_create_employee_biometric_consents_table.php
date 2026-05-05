<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_biometric_consents', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_uuid', 36);
            $table->unsignedBigInteger('company_id')->index();
            $table->boolean('selfie_consent')->default(false);
            $table->boolean('gps_consent')->default(false);
            $table->timestamp('consent_given_at')->nullable();
            $table->timestamp('consent_withdrawn_at')->nullable();
            $table->string('consent_ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['employee_uuid', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_biometric_consents');
    }
};
