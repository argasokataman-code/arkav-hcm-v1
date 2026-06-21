<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_email_logs')) {
            return;
        }

        // Check if invoices table exists first
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoice_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->string('to_email', 255);
            $table->enum('status', ['sent', 'failed'])->index();
            $table->string('provider_message_id', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_email_logs');
    }
};
