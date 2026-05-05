<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('user_uuid', 36)->index();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('action', 100);           // export_employees, export_departments, dll
            $table->string('format', 20)->default('csv'); // csv, xlsx, pdf
            $table->integer('record_count')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('filters_applied')->nullable();
            $table->timestamp('exported_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_audit_logs');
    }
};
