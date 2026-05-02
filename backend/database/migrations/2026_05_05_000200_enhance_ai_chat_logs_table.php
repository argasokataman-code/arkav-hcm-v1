<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI Chat Logs enhancements:
 * - raw_intent: classifier output before any fallback swap (observability)
 * - user_message: original user question for session context replay
 * - ai_reply: LLM reply stored for session context replay
 * - Indexes on intent, deny_reason, (created_at, intent) for analytics
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chat_logs', function (Blueprint $table) {
            // Point 3: raw_intent — classifier output BEFORE fallback swap
            $table->string('raw_intent', 100)->nullable()->after('intent');

            // Point 5: conversation turns for session-aware context
            $table->text('user_message')->nullable()->after('source_endpoints');
            $table->text('ai_reply')->nullable()->after('user_message');

            // Point 8: analytics indexes
            $table->index('intent');
            $table->index('deny_reason');
            $table->index(['created_at', 'intent']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_logs', function (Blueprint $table) {
            $table->dropIndex(['intent']);
            $table->dropIndex(['deny_reason']);
            $table->dropIndex(['created_at', 'intent']);
            $table->dropColumn(['raw_intent', 'user_message', 'ai_reply']);
        });
    }
};
