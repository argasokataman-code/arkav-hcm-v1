<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'renewal_period_key')) {
                if (Schema::hasColumn('invoices', 'subscription_uuid')) {
                    $table->string('renewal_period_key', 128)->nullable()->after('subscription_uuid');
                } elseif (Schema::hasColumn('invoices', 'subscription_id')) {
                    $table->string('renewal_period_key', 128)->nullable()->after('subscription_id');
                } else {
                    $table->string('renewal_period_key', 128)->nullable();
                }
            }

            if (! Schema::hasColumn('invoices', 'renewal_reason_code')) {
                $table->string('renewal_reason_code', 64)->nullable()->after('status');
            }

            if (! Schema::hasColumn('invoices', 'renewal_reason_message')) {
                $table->string('renewal_reason_message', 255)->nullable()->after('renewal_reason_code');
            }
        });

        // Backfill key for existing recurring renewal rows to keep monitoring queryable.
        DB::table('invoices')
            ->whereNull('renewal_period_key')
            ->whereNotNull('subscription_id')
            ->where('notes', 'like', '%"source":"recurring_subscription_renewal"%')
            ->orderBy('id')
            ->select(['id', 'subscription_id', 'issue_date', 'created_at'])
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $period = null;

                    if (! empty($row->issue_date)) {
                        $period = Carbon::parse((string) $row->issue_date)->format('Y_m');
                    } elseif (! empty($row->created_at)) {
                        $period = Carbon::parse((string) $row->created_at)->format('Y_m');
                    } else {
                        $period = now()->format('Y_m');
                    }

                    $baseKey = sprintf('sub_%d_%s', (int) $row->subscription_id, $period);
                    $candidateKey = $baseKey;

                    $exists = DB::table('invoices')
                        ->where('renewal_period_key', $candidateKey)
                        ->where('id', '!=', $row->id)
                        ->exists();

                    if ($exists) {
                        $candidateKey = sprintf('%s_dup_%d', $baseKey, (int) $row->id);
                    }

                    DB::table('invoices')
                        ->where('id', $row->id)
                        ->update(['renewal_period_key' => $candidateKey]);
                }
            }, 'id');

        Schema::table('invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('invoices', 'renewal_period_key')) {
                $table->index('renewal_period_key', 'invoices_renewal_period_key_idx');
            }

            if (Schema::hasColumn('invoices', 'subscription_uuid') && Schema::hasColumn('invoices', 'renewal_period_key')) {
                $table->unique(['subscription_uuid', 'renewal_period_key'], 'invoices_subscription_uuid_renewal_period_unique');
            } elseif (Schema::hasColumn('invoices', 'subscription_id') && Schema::hasColumn('invoices', 'renewal_period_key')) {
                $table->unique(['subscription_id', 'renewal_period_key'], 'invoices_subscription_id_renewal_period_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        // Drop columns if they exist (all indexes/constraints will be dropped with the columns)
        Schema::table('invoices', function (Blueprint $table): void {
            $dropColumns = [];
            foreach (['renewal_period_key', 'renewal_reason_code', 'renewal_reason_message'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (! empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
