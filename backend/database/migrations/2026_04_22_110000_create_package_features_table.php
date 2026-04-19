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
        if (Schema::hasTable('package_features')) {
            return;
        }

        Schema::create('package_features', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('package_uuid')->constrained('packages', 'uuid')->onDelete('cascade');
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('feature_code'); // employee_management, payroll, attendance, etc
            $table->string('feature_name');
            $table->integer('limit')->nullable(); // null = unlimited, 0 = not included, > 0 = specific limit
            $table->uuid()->nullable();
            $table->timestamps();
            
            $table->unique(['package_uuid', 'feature_code']);
            $table->index('feature_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_features');
    }
};
