<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_revenue_transactions')) {
            return;
        }

        Schema::create('platform_revenue_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id');
            $table->string('source_event_type', 64);
            $table->string('source_entity_type', 64)->nullable();
            $table->unsignedBigInteger('source_entity_id')->nullable();
            $table->uuid('source_entity_uuid')->nullable();
            $table->string('transaction_type', 32);
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->char('currency', 3)->default('IDR');
            $table->string('status', 24)->default('posted');
            $table->string('clearing_status', 24)->default('uncleared');
            $table->date('clearing_date')->nullable();
            $table->string('dispute_reason', 255)->nullable();
            $table->string('idempotency_key', 191)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'occurred_at'], 'platform_rev_tx_company_occurred_idx');
            $table->index(['company_id', 'transaction_type'], 'platform_rev_tx_company_type_idx');
            $table->index(['source_event_type', 'occurred_at'], 'platform_rev_tx_event_occurred_idx');
            $table->index(['status', 'clearing_status'], 'platform_rev_tx_status_clearing_idx');
            $table->unique(['idempotency_key'], 'platform_rev_tx_idempotency_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_revenue_transactions');
    }
};
