<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_reconciliation_evidences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('feature_key', 80);
            $table->string('action_key', 80);
            $table->string('scope_ref', 120);
            $table->unsignedBigInteger('exported_by_user_id')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->string('file_format', 10);
            $table->string('file_path', 500);
            $table->unsignedInteger('row_count')->default(0);
            $table->json('filter_payload')->nullable();
            $table->string('dataset_checksum', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(
                ['company_id', 'feature_key', 'action_key', 'scope_ref', 'exported_at'],
                'exp_recon_scope_exported_idx'
            );
            $table->index(['exported_by_user_id', 'exported_at'], 'exp_recon_user_exported_idx');
            $table->index(['company_id', 'expires_at'], 'exp_recon_company_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('export_reconciliation_evidences');
        Schema::enableForeignKeyConstraints();
    }
};