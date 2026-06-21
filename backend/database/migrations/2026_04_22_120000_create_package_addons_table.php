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
        Schema::create('package_addons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // extra_users, extra_companies, api_calls, storage
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('price_per_unit', 12, 2);
            $table->string('unit_name'); // users, companies, 1M API calls, GB
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->uuid()->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_addons');
    }
};
