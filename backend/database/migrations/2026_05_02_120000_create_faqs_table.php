<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasIndex(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function hasForeign(string $table, string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_type', 'FOREIGN KEY')
            ->where('constraint_name', $constraintName)
            ->exists();
    }

    public function up(): void
    {
        if (! Schema::hasTable('faqs')) {
            Schema::create('faqs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->nullable();
                $table->unsignedBigInteger('company_id')->index();
                $table->uuid('company_uuid')->nullable();
                $table->string('category', 100);
                $table->string('question', 500);
                $table->text('answer');
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->uuid('created_by_uuid')->nullable();
                $table->uuid('updated_by_uuid')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('faqs', function (Blueprint $table): void {
            if (! Schema::hasColumn('faqs', 'company_uuid')) {
                $table->uuid('company_uuid')->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('faqs', 'created_by_uuid')) {
                $table->uuid('created_by_uuid')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('faqs', 'updated_by_uuid')) {
                $table->uuid('updated_by_uuid')->nullable()->after('updated_by');
            }
        });

        if (! $this->hasIndex('faqs', 'faqs_uuid_unique')) {
            Schema::table('faqs', function (Blueprint $table): void {
                $table->unique('uuid', 'faqs_uuid_unique');
            });
        }
        if (! $this->hasIndex('faqs', 'faqs_company_uuid_idx')) {
            Schema::table('faqs', function (Blueprint $table): void {
                $table->index('company_uuid', 'faqs_company_uuid_idx');
            });
        }
        if (! $this->hasIndex('faqs', 'faqs_created_by_uuid_idx')) {
            Schema::table('faqs', function (Blueprint $table): void {
                $table->index('created_by_uuid', 'faqs_created_by_uuid_idx');
            });
        }
        if (! $this->hasIndex('faqs', 'faqs_updated_by_uuid_idx')) {
            Schema::table('faqs', function (Blueprint $table): void {
                $table->index('updated_by_uuid', 'faqs_updated_by_uuid_idx');
            });
        }

        // UUID-first FK compatibility (companies/users use uuid as primary key).
        if (! $this->hasForeign('faqs', 'faqs_company_uuid_foreign')) {
            Schema::table('faqs', function (Blueprint $table): void {
                $table->foreign('company_uuid')->references('uuid')->on('companies')->nullOnDelete();
            });
        }
        if (! $this->hasForeign('faqs', 'faqs_created_by_uuid_foreign')) {
            Schema::table('faqs', function (Blueprint $table): void {
                $table->foreign('created_by_uuid')->references('uuid')->on('users')->nullOnDelete();
            });
        }
        if (! $this->hasForeign('faqs', 'faqs_updated_by_uuid_foreign')) {
            Schema::table('faqs', function (Blueprint $table): void {
                $table->foreign('updated_by_uuid')->references('uuid')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
