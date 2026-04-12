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
        // Add unique constraint to prevent duplicate holidays on same date and title
        if (Schema::hasTable('holidays')) {
            $hasNamedIndex = Schema::hasIndex('holidays', 'holidays_holiday_date_title_unique');
            $hasUniqueColumns = Schema::hasIndex('holidays', ['holiday_date', 'title'], 'unique');

            if (! $hasNamedIndex && ! $hasUniqueColumns) {
                Schema::table('holidays', function (Blueprint $table): void {
                    $table->unique(['holiday_date', 'title'], 'holidays_holiday_date_title_unique');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('holidays')) {
            $hasNamedIndex = Schema::hasIndex('holidays', 'holidays_holiday_date_title_unique');
            if ($hasNamedIndex) {
                Schema::table('holidays', function (Blueprint $table): void {
                    $table->dropUnique('holidays_holiday_date_title_unique');
                });
            }
        }
    }
};
