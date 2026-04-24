<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_preferences')) {
            return;
        }

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Keep this as plain indexed ID for compatibility with mixed UUID migration states.
            $table->unsignedBigInteger('user_id');
            $table->string('event_key', 191);
            $table->enum('channel', ['database', 'mail', 'sms', 'webhook'])->default('database');
            $table->boolean('enabled')->default(true);
            $table->enum('digest_mode', ['instant', 'daily', 'weekly'])->default('instant');
            $table->timestamps();

            $table->unique(['user_id', 'event_key', 'channel'], 'notification_preferences_user_event_channel_uq');
            $table->index(['user_id', 'enabled'], 'notification_preferences_user_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
