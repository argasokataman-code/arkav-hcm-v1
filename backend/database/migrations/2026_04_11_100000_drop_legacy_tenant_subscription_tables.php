<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel tenants / subscription_* berasal dari scaffolding lama dan tidak dipakai modul HCM aktif.
 * Kolom users.tenant_id tidak direferensikan di kode aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }

        Schema::dropIfExists('subscription_plan_features');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('tenants');
    }

    public function down(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('feature_key')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
        });
    }
};
