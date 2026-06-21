<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('performance_reviews')) {
            return;
        }

        Schema::table('performance_reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('performance_reviews', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
            }
        });

        try {
            Schema::table('performance_reviews', function (Blueprint $table): void {
                $table->index(['company_id', 'status'], 'performance_reviews_company_id_status_idx');
            });
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }

        if (Schema::hasTable('companies')) {
            try {
                Schema::table('performance_reviews', function (Blueprint $table): void {
                    $table->foreign('company_id', 'performance_reviews_company_id_foreign')
                        ->references('id')
                        ->on('companies')
                        ->nullOnDelete();
                });
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('performance_reviews')) {
            return;
        }

        try {
            Schema::table('performance_reviews', function (Blueprint $table): void {
                $table->dropForeign('performance_reviews_company_id_foreign');
            });
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'check that column/key exists') === false && stripos($e->getMessage(), 'cannot drop') === false) {
                throw $e;
            }
        }

        try {
            Schema::table('performance_reviews', function (Blueprint $table): void {
                $table->dropIndex('performance_reviews_company_id_status_idx');
            });
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'check that column/key exists') === false && stripos($e->getMessage(), 'cannot drop') === false) {
                throw $e;
            }
        }

        if (Schema::hasColumn('performance_reviews', 'company_id')) {
            Schema::table('performance_reviews', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
    }
};
