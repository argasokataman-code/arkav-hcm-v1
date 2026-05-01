<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->nullable();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('title', 300);
            $table->text('content')->nullable();
            $table->enum('tag', ['personal', 'social', 'work', 'others'])->default('personal');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_important')->default(false);
            $table->boolean('is_trashed')->default(false);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
