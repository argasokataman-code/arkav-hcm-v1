<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add subscription termination and suspension tracking columns.
     * Used for auto-termination on expired end_date and auto-suspension on overdue payments/violations.
     */
    public function up(): void
    {
        if (Schema::hasTable('subscriptions') && ! Schema::hasColumn('subscriptions', 'terminated_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->timestamp('terminated_at')->nullable()->after('ends_at');
                $table->text('termination_reason')->nullable()->after('terminated_at');
                $table->timestamp('suspended_at')->nullable()->after('termination_reason');
                $table->text('suspension_reason')->nullable()->after('suspended_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn([
                    'terminated_at',
                    'termination_reason',
                    'suspended_at',
                    'suspension_reason',
                ]);
            });
        }
    }
};
