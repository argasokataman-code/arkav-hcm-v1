<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if companies table exists first
        if (!Schema::hasTable('companies')) {
            return;
        }

        // Check if payments table exists; if not, create it
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
                $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->onDelete('set null');
                $table->foreignId('purchase_transaction_id')->nullable()->constrained('purchase_transactions')->onDelete('set null');
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
                $table->decimal('amount', 12, 2);
                $table->string('currency')->default('IDR');
                $table->enum('status', ['pending', 'completed', 'failed', 'disputed'])->default('pending');
                $table->enum('payment_method', ['bank_transfer', 'credit_card', 'e_wallet', 'cash', 'check'])->nullable();
                $table->string('gateway')->nullable();
                $table->string('gateway_reference')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->dateTime('verified_at')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('company_id');
                $table->index('status');
                $table->index('payment_method');
            });
        } else {
            // If payments table exists, add missing columns
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'purchase_transaction_id')) {
                    $table->foreignId('purchase_transaction_id')->nullable()->default(null)->constrained('purchase_transactions')->onDelete('set null');
                }
                if (!Schema::hasColumn('payments', 'invoice_id')) {
                    $table->foreignId('invoice_id')->nullable()->default(null)->constrained('invoices')->onDelete('set null');
                }
                if (!Schema::hasColumn('payments', 'payment_method')) {
                    $table->enum('payment_method', ['bank_transfer', 'credit_card', 'e_wallet', 'cash', 'check'])->nullable();
                }
                if (!Schema::hasColumn('payments', 'verified_at')) {
                    $table->dateTime('verified_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
