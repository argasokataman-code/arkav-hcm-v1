<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_features', function (Blueprint $table) {
            // 'core' = always included in base package price
            // 'addon' = optional feature available on top of this package
            $table->enum('tier', ['core', 'addon'])->default('core')->after('limit');
        });
    }

    public function down(): void
    {
        Schema::table('package_features', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
