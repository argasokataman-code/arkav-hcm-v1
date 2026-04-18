<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_email_logs') || ! Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoice_email_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->uuid('invoice_uuid')->nullable();
            $table->string('to_email', 255);
            $table->enum('status', ['sent', 'failed'])->index();
            $table->string('provider_message_id', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'created_at']);
            $table->index('invoice_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_email_logs');
    }
};
