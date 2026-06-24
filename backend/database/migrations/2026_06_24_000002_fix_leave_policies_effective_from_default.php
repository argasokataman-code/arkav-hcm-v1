<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_policies') || ! Schema::hasColumn('leave_policies', 'effective_from')) {
            return;
        }

        Schema::table('leave_policies', function (Blueprint $table): void {
            $table->date('effective_from')->default(now()->startOfYear()->toDateString())->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_policies') || ! Schema::hasColumn('leave_policies', 'effective_from')) {
            return;
        }

        Schema::table('leave_policies', function (Blueprint $table): void {
            $table->date('effective_from')->change();
        });
    }
};
