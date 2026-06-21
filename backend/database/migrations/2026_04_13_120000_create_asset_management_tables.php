<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('code', 80);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'asset_categories_company_code_unique');
            $table->unique(['company_id', 'name'], 'asset_categories_company_name_unique');
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('asset_category_id')->nullable();
            $table->string('asset_code', 120);
            $table->string('name', 150);
            $table->string('brand', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 150)->nullable();
            $table->date('purchase_date');
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->enum('condition', ['good', 'damaged', 'lost'])->default('good');
            $table->enum('status', ['available', 'assigned', 'maintenance', 'retired'])->default('available');
            $table->string('location', 255)->nullable();
            $table->text('notes')->nullable();
            $table->date('warranty_start_date')->nullable();
            $table->date('warranty_end_date')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'asset_code'], 'assets_company_asset_code_unique');
            $table->unique(['company_id', 'serial_number'], 'assets_company_serial_unique');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'condition']);
            $table->index(['company_id', 'asset_category_id']);
        });

        Schema::create('asset_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->timestamp('assigned_date');
            $table->timestamp('returned_date')->nullable();
            $table->string('condition_at_assign', 30);
            $table->string('condition_at_return', 30)->nullable();
            $table->string('active_token', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'asset_id', 'active_token'], 'asset_assignments_one_active_unique');
            $table->index(['company_id', 'employee_id']);
            $table->index(['company_id', 'returned_date']);
        });

        Schema::create('asset_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->enum('action', ['created', 'assigned', 'returned', 'updated', 'maintenance', 'issue_reported', 'retired']);
            $table->string('reference_id', 120)->nullable();
            $table->text('description');
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'asset_id']);
            $table->index(['company_id', 'action']);
        });

        Schema::create('asset_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('file_path', 500);
            $table->string('file_type', 120);
            $table->string('disk', 40)->default('public');
            $table->string('original_name', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'asset_id']);
            $table->index(['company_id', 'file_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_attachments');
        Schema::dropIfExists('asset_logs');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
