<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-initiated subscription plan change requests (F4).
 *
 * Digunakan saat feature gate memblokir tenant dan tenant-owner memilih
 * untuk upgrade/downgrade/cancel paket. Request masuk sebagai pending;
 * global super-admin mengapprove/reject, lalu subscription aktif diperbarui
 * (apply). Catatan audit lengkap disimpan di kolom `preview` + timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_subscription_change_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_uuid')->index();
            $table->uuid('user_uuid')->index();
            $table->uuid('current_subscription_uuid')->nullable()->index();
            $table->uuid('from_package_uuid')->nullable()->index();
            $table->uuid('to_package_uuid')->nullable()->index();
            $table->string('action', 20); // upgrade | downgrade | cancel
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | applied | cancelled
            $table->json('preview')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->uuid('decided_by_user_uuid')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['company_uuid', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_subscription_change_requests');
    }
};
