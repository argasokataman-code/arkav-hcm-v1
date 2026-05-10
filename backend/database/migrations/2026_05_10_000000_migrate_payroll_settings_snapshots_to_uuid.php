<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('payroll_settings_snapshots')) {
            return;
        }

        Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
            // Add UUID columns
            $table->char('company_uuid', 36)->nullable()->after('company_id');
            $table->char('user_uuid', 36)->nullable()->after('user_id');
        });

        // Populate company_uuid from company id
        if (Schema::hasColumn('payroll_settings_snapshots', 'company_id')
            && DB::table('payroll_settings_snapshots')->count() > 0) {
            DB::statement(
                'UPDATE payroll_settings_snapshots s '
                .'INNER JOIN companies c ON c.id = s.company_id '
                .'SET s.company_uuid = c.uuid'
            );
        }

        // Populate user_uuid from user id
        if (Schema::hasColumn('payroll_settings_snapshots', 'user_id')
            && DB::table('payroll_settings_snapshots')->where('user_id', '!=', null)->count() > 0) {
            DB::statement(
                'UPDATE payroll_settings_snapshots s '
                .'INNER JOIN users u ON u.id = s.user_id '
                .'SET s.user_uuid = u.uuid '
                .'WHERE s.user_id IS NOT NULL'
            );
        }

        // Make company_uuid NOT NULL (all records should have it)
        Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
            $table->char('company_uuid', 36)->nullable(false)->change();
        });

        // Add FK constraints on UUID columns
        Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
            // Foreign key for company_uuid (CASCADE on delete)
            $table->foreign('company_uuid', 'payroll_settings_snapshots_company_uuid_fk')
                ->references('uuid')
                ->on('companies')
                ->cascadeOnDelete();

            // Foreign key for user_uuid (SET NULL on delete)
            $table->foreign('user_uuid', 'payroll_settings_snapshots_user_uuid_fk')
                ->references('uuid')
                ->on('users')
                ->nullOnDelete();
        });

        // Drop old numeric foreign keys if they exist
        Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
            if ($this->hasConstraint('payroll_settings_snapshots', 'payroll_settings_snapshots_company_id_fk')) {
                $table->dropForeign('payroll_settings_snapshots_company_id_fk');
            }
            if ($this->hasConstraint('payroll_settings_snapshots', 'payroll_settings_snapshots_user_id_fk')) {
                $table->dropForeign('payroll_settings_snapshots_user_id_fk');
            }
        });

        // Drop old numeric columns
        Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
            $table->dropColumn('company_id');
            $table->dropColumn('user_id');
        });

        // Add composite index on company_uuid + snapshot_version
        if (! $this->hasIndex('payroll_settings_snapshots', 'payroll_settings_snapshots_company_uuid_snapshot_version_idx')) {
            Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
                $table->index(['company_uuid', 'snapshot_version'], 'payroll_settings_snapshots_company_uuid_snapshot_version_idx');
            });
        }

        // Add index on user_uuid
        if (! $this->hasIndex('payroll_settings_snapshots', 'payroll_settings_snapshots_user_uuid_idx')) {
            Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
                $table->index('user_uuid', 'payroll_settings_snapshots_user_uuid_idx');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('payroll_settings_snapshots')) {
            return;
        }

        // Re-add numeric columns
        Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
            $table->bigInteger('company_id')->after('uuid');
            $table->bigInteger('user_id')->nullable()->after('snapshot_version');
        });

        // Populate from UUID columns
        if (Schema::hasColumn('payroll_settings_snapshots', 'company_uuid')) {
            DB::statement(
                'UPDATE payroll_settings_snapshots s '
                .'INNER JOIN companies c ON c.uuid = s.company_uuid '
                .'SET s.company_id = c.id'
            );
        }

        if (Schema::hasColumn('payroll_settings_snapshots', 'user_uuid')) {
            DB::statement(
                'UPDATE payroll_settings_snapshots s '
                .'INNER JOIN users u ON u.uuid = s.user_uuid '
                .'SET s.user_id = u.id '
                .'WHERE s.user_uuid IS NOT NULL'
            );
        }

        // Drop UUID FK constraints
        Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
            if ($this->hasConstraint('payroll_settings_snapshots', 'payroll_settings_snapshots_company_uuid_fk')) {
                $table->dropForeign('payroll_settings_snapshots_company_uuid_fk');
            }
            if ($this->hasConstraint('payroll_settings_snapshots', 'payroll_settings_snapshots_user_uuid_fk')) {
                $table->dropForeign('payroll_settings_snapshots_user_uuid_fk');
            }
        });

        // Re-add numeric FK constraints
        Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
            $table->foreign('company_id', 'payroll_settings_snapshots_company_id_fk')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'payroll_settings_snapshots_user_id_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // Drop UUID columns
        Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
            $table->dropColumn('company_uuid', 'user_uuid');
        });

        // Drop new indexes
        Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
            if ($this->hasIndex('payroll_settings_snapshots', 'payroll_settings_snapshots_company_uuid_snapshot_version_idx')) {
                $table->dropIndex('payroll_settings_snapshots_company_uuid_snapshot_version_idx');
            }
            if ($this->hasIndex('payroll_settings_snapshots', 'payroll_settings_snapshots_user_uuid_idx')) {
                $table->dropIndex('payroll_settings_snapshots_user_uuid_idx');
            }
        });
    }

    private function hasConstraint(string $table, string $constraintName): bool
    {
        $row = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('CONSTRAINT_NAME')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->first();

        return $row !== null;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $row = DB::table('information_schema.STATISTICS')
            ->select('INDEX_NAME')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->first();

        return $row !== null;
    }
};
