<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_thr_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hcm_thr_batch_id')->constrained('hcm_thr_batches')->cascadeOnDelete();
            $table->string('status', 24)->default('processing');
            $table->string('driver', 32)->default('stub');
            $table->json('meta')->nullable();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['hcm_thr_batch_id', 'status']);
        });

        Schema::table('hcm_thr_batch_lines', function (Blueprint $table) {
            $table->string('payment_status', 24)->default('unpaid')->after('eligible');
            $table->text('payment_failure_reason')->nullable()->after('payment_status');
            $table->string('payment_gateway_ref', 128)->nullable()->after('payment_failure_reason');
            $table->timestamp('paid_at')->nullable()->after('payment_gateway_ref');
            $table->string('slip_storage_path', 512)->nullable()->after('paid_at');
            $table->timestamp('slip_generated_at')->nullable()->after('slip_storage_path');
            $table->timestamp('slip_notify_sent_at')->nullable()->after('slip_generated_at');
            $table->foreignId('last_disbursement_id')->nullable()->after('slip_notify_sent_at')
                ->constrained('hcm_thr_disbursements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hcm_thr_batch_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_disbursement_id');
            $table->dropColumn([
                'payment_status',
                'payment_failure_reason',
                'payment_gateway_ref',
                'paid_at',
                'slip_storage_path',
                'slip_generated_at',
                'slip_notify_sent_at',
            ]);
        });

        Schema::dropIfExists('hcm_thr_disbursements');
    }
};
