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
        // Check if subscriptions table exists and doesn't have package_id yet
        if (Schema::hasTable('subscriptions') && !Schema::hasColumn('subscriptions', 'package_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->foreignId('package_id')->nullable()->after('company_id')->constrained('packages')->onDelete('restrict');
                $table->string('billing_cycle')->default('monthly')->after('auto_renew');
                $table->decimal('amount', 12, 2)->default(0)->after('billing_cycle');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'amount')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn(['package_id', 'billing_cycle', 'amount']);
            });
        }
    }
};
