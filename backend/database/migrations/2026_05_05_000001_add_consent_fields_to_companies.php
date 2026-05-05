<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('onboarding_consent_accepted')->default(false)->after('country_code');
            $table->timestamp('onboarding_consent_at')->nullable()->after('onboarding_consent_accepted');
            $table->string('onboarding_consent_ip', 45)->nullable()->after('onboarding_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['onboarding_consent_accepted', 'onboarding_consent_at', 'onboarding_consent_ip']);
        });
    }
};
