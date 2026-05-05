<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_spt_masa_headers')) {
            Schema::create('hcm_spt_masa_headers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('company_id');
                $table->uuid('company_uuid')->nullable();
                $table->char('periode', 7)->comment('Format YYYY-MM');
                $table->string('status', 32)->default('draft')->comment('draft|ready|submitted');
                $table->decimal('total_bruto', 18, 2)->default(0);
                $table->decimal('total_pph21', 18, 2)->default(0);
                $table->unsignedInteger('total_karyawan')->default(0);
                $table->unsignedInteger('version')->default(1)->comment('Optimistic lock');
                $table->string('generation_key', 128)->nullable()->comment('Client idempotency key');
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->uuid('created_by_user_uuid')->nullable();
                $table->unsignedBigInteger('submitted_by_user_id')->nullable();
                $table->uuid('submitted_by_user_uuid')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('company_id', 'hcm_spt_header_company_idx');
                $table->index(['company_id', 'periode'], 'hcm_spt_header_company_periode_idx');
                $table->index(['company_id', 'status'], 'hcm_spt_header_company_status_idx');
                $table->index('generation_key', 'hcm_spt_header_generation_key_idx');

                $table->foreign('company_uuid', 'hcm_spt_header_company_uuid_fk')
                    ->references('uuid')->on('companies')->nullOnDelete();
                $table->foreign('created_by_user_uuid', 'hcm_spt_header_created_by_uuid_fk')
                    ->references('uuid')->on('users')->nullOnDelete();
                $table->foreign('submitted_by_user_uuid', 'hcm_spt_header_submitted_by_uuid_fk')
                    ->references('uuid')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('hcm_spt_masa_details')) {
            Schema::create('hcm_spt_masa_details', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('hcm_spt_masa_header_id');
                $table->uuid('hcm_spt_masa_header_uuid')->nullable()->comment('Denormalized for audit');
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger('user_id')->nullable()->comment('NULL for non-employee entries');
                $table->uuid('user_uuid')->nullable()->comment('Denormalized');
                $table->string('nama', 255);
                $table->string('npwp', 32)->nullable();
                $table->string('nik', 32)->nullable();
                $table->string('employment_type', 32)->nullable()->comment('permanent|contract|intern|non_employee');
                $table->string('kategori_spt', 32)->nullable()->comment('pegawai_tetap|tidak_tetap|non_pegawai');
                $table->decimal('bruto', 18, 2)->default(0);
                $table->decimal('pph21', 18, 2)->default(0);
                $table->string('bukti_potong_type', 40)->nullable()->comment('A1|non_pegawai');
                $table->timestamps();

                $table->index('hcm_spt_masa_header_id', 'hcm_spt_detail_header_id_idx');
                $table->index(['company_id', 'user_id'], 'hcm_spt_detail_company_user_idx');
                $table->index('hcm_spt_masa_header_uuid', 'hcm_spt_detail_header_uuid_idx');

                $table->foreign('hcm_spt_masa_header_id', 'hcm_spt_detail_header_fk')
                    ->references('id')->on('hcm_spt_masa_headers')->cascadeOnDelete();
                $table->foreign('hcm_spt_masa_header_uuid', 'hcm_spt_detail_header_uuid_fk')
                    ->references('uuid')->on('hcm_spt_masa_headers')->nullOnDelete();
                $table->foreign('user_uuid', 'hcm_spt_detail_user_uuid_fk')
                    ->references('uuid')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_spt_masa_details');
        Schema::dropIfExists('hcm_spt_masa_headers');
    }
};
