<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_breach_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('title');
            $table->text('description');
            $table->json('affected_data_types')->nullable();
            $table->unsignedInteger('affected_subjects_count')->default(0);
            $table->json('affected_user_uuids')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('reported_to_bssn_at')->nullable();
            $table->timestamp('notifications_sent_at')->nullable();
            $table->string('status', 32)->default('detected')->index();
            $table->string('created_by_uuid', 64)->nullable()->index();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_breach_incidents');
    }
};
