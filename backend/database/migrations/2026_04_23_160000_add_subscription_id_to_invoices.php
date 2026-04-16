<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        if (! Schema::hasColumn('invoices', 'subscription_id')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->foreignId('subscription_id')
                    ->nullable()
                    ->after('purchase_transaction_id')
                    ->constrained('subscriptions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'subscription_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['subscription_id']);
            $table->dropColumn('subscription_id');
        });
    }
};
