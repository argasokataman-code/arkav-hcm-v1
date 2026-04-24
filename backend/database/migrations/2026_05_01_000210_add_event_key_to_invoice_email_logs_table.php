<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_email_logs')) {
            return;
        }

        Schema::table('invoice_email_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoice_email_logs', 'event_key')) {
                $table->string('event_key', 191)->nullable()->after('to_email')->index();
            }
        });

        DB::table('invoice_email_logs')
            ->whereNull('event_key')
            ->update([
                'event_key' => DB::raw("CASE WHEN status = 'sent' THEN 'billing.invoice.email_sent' ELSE 'billing.invoice.email_failed' END"),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_email_logs')) {
            return;
        }

        Schema::table('invoice_email_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('invoice_email_logs', 'event_key')) {
                $table->dropColumn('event_key');
            }
        });
    }
};
