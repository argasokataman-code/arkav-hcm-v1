<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->ensurePackagesUuid();

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

    private function ensurePackagesUuid(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }

        if (! Schema::hasColumn('packages', 'uuid')) {
            Schema::table('packages', function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            });
        }

        DB::table('packages')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('packages')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });
    }
};
