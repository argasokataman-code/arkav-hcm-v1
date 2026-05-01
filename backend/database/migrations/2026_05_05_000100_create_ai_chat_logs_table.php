<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('user_uuid', 36);
            $table->unsignedBigInteger('user_legacy_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->string('session_id', 36);
            $table->string('intent', 100)->default('unknown');
            $table->boolean('allowed')->default(false);
            $table->string('deny_reason', 100)->nullable();
            $table->json('source_endpoints')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_uuid');
            $table->index(['user_uuid', 'session_id']);
            $table->index('created_at');

            $table->foreign('user_uuid')
                ->references('uuid')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_logs');
    }
};
