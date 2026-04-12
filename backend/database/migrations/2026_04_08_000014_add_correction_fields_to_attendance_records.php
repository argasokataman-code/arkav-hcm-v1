<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('correction_status', 20)->default('none')->after('status');
            $table->text('correction_reason')->nullable()->after('late_minutes');
            $table->timestamp('correction_requested_at')->nullable()->after('correction_reason');
            $table->foreignId('corrected_by_user_id')->nullable()->after('correction_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable()->after('corrected_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrected_by_user_id');
            $table->dropColumn([
                'correction_status',
                'correction_reason',
                'correction_requested_at',
                'corrected_at',
            ]);
        });
    }
};
