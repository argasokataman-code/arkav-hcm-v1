<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                if (! Schema::hasColumn('subscriptions', 'grace_started_at')) {
                    $table->timestamp('grace_started_at')->nullable()->after('suspension_reason');
                }

                if (! Schema::hasColumn('subscriptions', 'grace_ends_at')) {
                    $table->timestamp('grace_ends_at')->nullable()->after('grace_started_at');
                }
            });
        }

        if (! Schema::hasTable('subscription_events')) {
            Schema::create('subscription_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                // Keep id+uuid references without hard FK to stay compatible with mixed legacy schemas.
                $table->unsignedBigInteger('company_id')->nullable()->index();
                if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'uuid')) {
                    $table->uuid('company_uuid')->nullable()->index();
                }

                $table->unsignedBigInteger('subscription_id')->nullable()->index();
                if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'uuid')) {
                    $table->uuid('subscription_uuid')->nullable()->index();
                }

                $table->unsignedBigInteger('invoice_id')->nullable()->index();
                if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'uuid')) {
                    $table->uuid('invoice_uuid')->nullable()->index();
                }

                $table->unsignedBigInteger('payment_id')->nullable()->index();
                if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'uuid')) {
                    $table->uuid('payment_uuid')->nullable()->index();
                }

                $table->string('renewal_period_key', 128)->nullable()->index();
                $table->string('event_type', 64)->index();
                $table->string('reason_code', 64)->nullable()->index();
                $table->string('reason_message', 255)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at')->useCurrent()->index();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscription_events')) {
            Schema::drop('subscription_events');
        }

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $drops = [];

                if (Schema::hasColumn('subscriptions', 'grace_started_at')) {
                    $drops[] = 'grace_started_at';
                }

                if (Schema::hasColumn('subscriptions', 'grace_ends_at')) {
                    $drops[] = 'grace_ends_at';
                }

                if ($drops !== []) {
                    $table->dropColumn($drops);
                }
            });
        }
    }
};
