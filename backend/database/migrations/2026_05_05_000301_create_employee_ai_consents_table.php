<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create table for tracking AI Chat consent per employee.
     * UU PDP compliance: Pasal 5 (hak informasi), H3 finding.
     */
    public function up(): void
    {
        Schema::create('employee_ai_consents', function (Blueprint $table) {
            $table->id();
            $table->uuid('employee_uuid')->index();
            $table->uuid('user_uuid')->nullable()->index(); // Karyawan yang memberikan consent
            $table->dateTime('consent_given_at');
            $table->string('consent_ip_address', 50)->nullable();
            $table->text('consent_text')->nullable(); // Snapshot of the consent notice shown to employee
            $table->dateTime('withdrawn_at')->nullable(); // NULL = active, set = withdrawn
            $table->timestamps();

            // Foreign key (optional, depending on FK strategy)
            // $table->foreign('employee_uuid')->references('uuid')->on('employee_profiles')->onDelete('cascade');
            // $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_ai_consents');
    }
};
