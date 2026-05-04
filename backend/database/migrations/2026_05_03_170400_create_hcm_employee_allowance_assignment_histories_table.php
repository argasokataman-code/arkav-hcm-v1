<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hcm_employee_allowance_assignment_histories')) {
            return;
        }

        Schema::create('hcm_employee_allowance_assignment_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->uuid('assignment_uuid')->nullable();
            $table->string('action_type', 32);
            $table->json('snapshot')->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->uuid('changed_by_user_uuid')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at'], 'hcm_allowance_assignment_hist_company_idx');
            $table->index('assignment_uuid', 'hcm_allowance_assignment_hist_uuid_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_allowance_assignment_histories');
    }
};
