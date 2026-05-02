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
        if (Schema::hasTable('invoices')) return;

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('purchase_transaction_id')->nullable()->constrained('purchase_transactions')->onDelete('set null');
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('amount_due', 12, 2);
            $table->boolean('is_paid')->default(false);
            $table->dateTime('paid_date')->nullable();
            $table->string('pdf_path')->nullable();
            $table->enum('status', ['draft', 'issued', 'sent', 'viewed', 'paid', 'expired', 'overdue', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('status');
            $table->index('is_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
