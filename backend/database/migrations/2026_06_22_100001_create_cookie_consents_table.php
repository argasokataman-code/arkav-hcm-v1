<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UU PDP H7: Cookie consent preferences per user.
     */
    public function up(): void
    {
        Schema::create('cookie_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->onDelete('cascade');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->boolean('essential')->default(true);
            $table->boolean('analytics')->default(false);
            $table->boolean('marketing')->default(false);
            $table->string('consent_ip', 45)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();

            $table->unique(['user_uuid', 'company_id'], 'cookie_consents_user_company_unique');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_consents');
    }
};
