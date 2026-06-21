<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
                $table->index(['company_id', 'status'], 'tickets_company_status_idx');
            }
        });

        DB::table('tickets')
            ->select('id', 'user_id')
            ->whereNull('company_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $ticket): void {
                $companyIds = DB::table('company_users')
                    ->where('user_id', $ticket->user_id)
                    ->where('status', 'active')
                    ->distinct()
                    ->pluck('company_id')
                    ->filter()
                    ->values();

                if ($companyIds->count() !== 1) {
                    return;
                }

                DB::table('tickets')
                    ->where('id', $ticket->id)
                    ->update(['company_id' => (int) $companyIds->first()]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tickets') || ! Schema::hasColumn('tickets', 'company_id')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex('tickets_company_status_idx');
            $table->dropColumn('company_id');
        });
    }
};
