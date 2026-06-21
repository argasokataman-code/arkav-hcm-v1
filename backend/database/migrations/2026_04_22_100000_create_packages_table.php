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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // basic, pro, enterprise
            $table->string('name'); // display name
            $table->string('description')->nullable();
            $table->decimal('monthly_price', 12, 2)->default(0);
            $table->decimal('yearly_price', 12, 2)->default(0);
            $table->enum('billing_unit', ['user', 'company', 'flat'])->default('flat');
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->string('color', 7)->default('#007bff'); // hex color
            $table->integer('sort_order')->default(0);
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
        Schema::dropIfExists('packages');
    }
};
