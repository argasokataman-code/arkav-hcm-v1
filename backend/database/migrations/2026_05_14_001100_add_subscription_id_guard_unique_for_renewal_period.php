<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        if (! Schema::hasColumn('invoices', 'subscription_id') || ! Schema::hasColumn('invoices', 'renewal_period_key')) {
            return;
        }

        // Normalize historical duplicates so we can enforce a strict runtime guard index.
        DB::table('invoices')
            ->select(['subscription_id', 'renewal_period_key'])
            ->whereNotNull('subscription_id')
            ->whereNotNull('renewal_period_key')
            ->groupBy('subscription_id', 'renewal_period_key')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('subscription_id')
            ->chunk(200, function ($groups): void {
                foreach ($groups as $group) {
                    $rows = DB::table('invoices')
                        ->where('subscription_id', $group->subscription_id)
                        ->where('renewal_period_key', $group->renewal_period_key)
                        ->orderBy('id')
                        ->get(['id']);

                    $keepFirst = true;
                    foreach ($rows as $row) {
                        if ($keepFirst) {
                            $keepFirst = false;
                            continue;
                        }

                        DB::table('invoices')
                            ->where('id', $row->id)
                            ->update([
                                'renewal_period_key' => sprintf('%s_dup_%d', (string) $group->renewal_period_key, (int) $row->id),
                            ]);
                    }
                }
            });

        Schema::table('invoices', function (Blueprint $table): void {
            try {
                $table->unique(['subscription_id', 'renewal_period_key'], 'invoices_subscription_id_renewal_period_guard_unique');
            } catch (\Throwable $e) {
                // Ignore if index already exists in current environment.
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            try {
                // Drop the foreign key constraint first (if any) before dropping the unique index
                try {
                    $table->dropForeign('invoices_subscription_id_foreign');
                } catch (\Throwable $e) {
                    // Foreign key might not exist
                }
                $table->dropUnique('invoices_subscription_id_renewal_period_guard_unique');
            } catch (\Throwable $e) {
                // No-op when index does not exist.
            }
        });
    }
};
