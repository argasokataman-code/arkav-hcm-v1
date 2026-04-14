<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('report_type', 80);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->unsignedBigInteger('generated_by_user_id')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'report_type'], 'report_snapshots_company_type_idx');
            $table->index(['company_id', 'status'], 'report_snapshots_company_status_idx');
            $table->index(['report_type', 'generated_at'], 'report_snapshots_type_generated_idx');
        });

        Schema::create('report_data_blocks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('snapshot_id')->nullable();
            $table->string('module', 80);
            $table->string('data_key', 120);
            $table->json('data_value');
            $table->timestamps();

            $table->index(['snapshot_id', 'module'], 'report_data_blocks_snapshot_module_idx');
            $table->index(['snapshot_id', 'data_key'], 'report_data_blocks_snapshot_key_idx');
        });

        Schema::create('report_filters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('snapshot_id')->nullable();
            $table->string('filter_key', 120);
            $table->json('filter_value');
            $table->timestamps();

            $table->index(['snapshot_id', 'filter_key'], 'report_filters_snapshot_key_idx');
        });

        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('snapshot_id')->nullable();
            $table->string('file_type', 30);
            $table->string('file_url', 500);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['snapshot_id', 'file_type'], 'report_exports_snapshot_type_idx');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('report_filters');
        Schema::dropIfExists('report_data_blocks');
        Schema::dropIfExists('report_snapshots');
        Schema::enableForeignKeyConstraints();
    }
};
