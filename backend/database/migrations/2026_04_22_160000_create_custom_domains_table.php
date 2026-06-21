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
        if (Schema::hasTable('custom_domains')) {
            return;
        }

        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('domain')->unique();
            $table->enum('status', ['pending', 'verified', 'failed', 'inactive'])->default('pending');
            $table->string('verification_token')->unique();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('verification_failed_at')->nullable();
            $table->string('verification_method')->default('dns')->comment('dns or file');
            $table->string('verification_record')->nullable()->comment('DNS record or file path');
            $table->text('verification_response')->nullable();
            $table->integer('verification_attempts')->default(0);
            $table->dateTime('last_verification_attempt_at')->nullable();
            $table->dateTime('active_from')->nullable();
            $table->dateTime('active_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('status');
            $table->index('verified_at');
        });

        Schema::create('domain_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('custom_domains')->onDelete('cascade');
            $table->enum('status', ['pending', 'verified', 'failed'])->default('pending');
            $table->string('verification_method');
            $table->text('details')->nullable();
            $table->dateTime('attempted_at');
            $table->timestamps();

            $table->index('domain_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_verification_logs');
        Schema::dropIfExists('custom_domains');
    }
};
