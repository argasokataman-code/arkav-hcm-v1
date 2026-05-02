<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Document categories (e.g. KTP, Contract, Certificate, Payslip)
        Schema::create('hcm_employee_document_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->char('company_uuid', 36)->nullable()->index();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name'], 'doc_cat_company_name_unique');
        });

        // Employee documents (files attached to an employee)
        Schema::create('hcm_employee_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->char('company_uuid', 36)->nullable()->index();
            $table->unsignedBigInteger('employee_profile_id')->index();
            $table->char('employee_profile_uuid', 36)->nullable()->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->char('category_uuid', 36)->nullable()->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('disk', 50)->default('local');
            $table->enum('visibility', ['hr_only', 'employee_visible'])->default('hr_only');
            $table->date('expires_at')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->char('uploaded_by_uuid', 36)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_documents');
        Schema::dropIfExists('hcm_employee_document_categories');
    }
};
