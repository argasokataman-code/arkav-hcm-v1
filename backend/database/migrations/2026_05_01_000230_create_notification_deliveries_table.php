<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_deliveries')) {
            return;
        }

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_key', 191);
            $table->enum('channel', ['database', 'mail', 'sms', 'webhook'])->default('database');
            $table->string('status', 32)->default('queued');
            $table->string('notification_uuid', 64)->nullable()->index();
            $table->string('recipient', 191)->nullable();
            $table->uuid('company_uuid')->nullable()->index();
            $table->unsignedInteger('attempt_count')->default(1);
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['event_key', 'status'], 'notification_deliveries_event_status_idx');
            $table->index(['channel', 'status'], 'notification_deliveries_channel_status_idx');
            $table->index(['created_at', 'status'], 'notification_deliveries_created_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
