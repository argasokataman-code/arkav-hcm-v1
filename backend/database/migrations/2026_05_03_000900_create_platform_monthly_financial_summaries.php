<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_monthly_financial_summaries')) {
            return;
        }

        Schema::create('platform_monthly_financial_summaries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('report_year');
            $table->unsignedSmallInteger('report_month');
            $table->decimal('gross_revenue', 18, 2)->default(0);
            $table->decimal('cleared_revenue', 18, 2)->default(0);
            $table->decimal('uncleared_revenue', 18, 2)->default(0);
            $table->decimal('disputed_revenue', 18, 2)->default(0);
            $table->decimal('reversed_revenue', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('net_revenue', 18, 2)->default(0);
            // locked = report finalized; draft = still accumulating; open = accepting transactions
            $table->string('report_status', 16)->default('open');
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by_user_id')->nullable();
            // Idempotency: prevents double-lock from concurrent jobs
            $table->string('lock_token', 64)->nullable();
            $table->json('missing_tax_codes')->nullable();
            $table->timestamps();

            $table->unique(['report_year', 'report_month'], 'platform_monthly_summary_year_month_uq');
            $table->index(['report_status', 'report_year', 'report_month'], 'platform_monthly_summary_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_monthly_financial_summaries');
    }
};
