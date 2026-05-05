<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erasure_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('subject_uuid', 36)->index();      // user requesting erasure
            $table->unsignedBigInteger('company_id')->index();
            $table->string('status', 30)->default('pending'); // pending|approved|rejected|completed
            $table->text('reason')->nullable();
            $table->string('reviewed_by_uuid', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erasure_requests');
    }
};
