<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('is_active');
            $table->timestamp('last_synced_at')->nullable()->after('source');
        });

        DB::table('holidays')->update([
            'source' => 'manual',
            'last_synced_at' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropColumn(['source', 'last_synced_at']);
        });
    }
};
