<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreignId('category_id')
                ->nullable()
                ->after('category')
                ->constrained('ticket_categories')
                ->nullOnDelete();
        });

        $ticketCategoryMap = DB::table('ticket_categories')
            ->pluck('id', 'name')
            ->toArray();
        
        foreach ($ticketCategoryMap as $categoryName => $categoryId) {
            DB::table('tickets')
                ->where('category', $categoryName)
                ->whereNull('category_id')
                ->update(['category_id' => $categoryId]);
        }

        Schema::table('hcm_trainings', function (Blueprint $table): void {
            $table->foreignId('trainer_id')
                ->nullable()
                ->after('training_type_id')
                ->constrained('hcm_trainers')
                ->nullOnDelete();
        });

        $trainerNameMap = DB::table('hcm_trainers')
            ->pluck('id', 'name')
            ->toArray();
        
        foreach ($trainerNameMap as $trainerName => $trainerId) {
            DB::table('hcm_trainings')
                ->where('trainer_name', $trainerName)
                ->whereNull('trainer_id')
                ->update(['trainer_id' => $trainerId]);
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('hcm_trainings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('trainer_id');
        });
    }
};