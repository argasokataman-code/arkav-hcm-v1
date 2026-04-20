<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->migratePackageFeatures();
        $this->migrateSubscriptions();
        $this->dropPackagesLegacyId();
    }

    public function down(): void
    {
        // Forward-only migration.
    }

    private function migratePackageFeatures(): void
    {
        if (! Schema::hasTable('package_features')) {
            return;
        }

        if (! Schema::hasColumn('package_features', 'package_uuid')) {
            Schema::table('package_features', function (Blueprint $table): void {
                $table->uuid('package_uuid')->nullable()->after('package_id');
            });
        }

        if (Schema::hasColumn('package_features', 'package_id')) {
            // Backfill package_uuid from legacy package_id.
            DB::statement(
                'UPDATE package_features pf JOIN packages p ON pf.package_id = p.id SET pf.package_uuid = p.uuid WHERE pf.package_id IS NOT NULL AND pf.package_uuid IS NULL'
            );
        }

        $nullCount = (int) DB::table('package_features')->whereNull('package_uuid')->count();
        if ($nullCount > 0) {
            throw new RuntimeException("Cannot drop package_features.package_id: {$nullCount} rows still have NULL package_uuid");
        }

        if ($this->foreignKeyExists('package_features', 'package_features_package_id_foreign')) {
            DB::statement('ALTER TABLE `package_features` DROP FOREIGN KEY `package_features_package_id_foreign`');
        }

        if ($this->indexExists('package_features', 'package_features_package_id_feature_code_unique')) {
            DB::statement('ALTER TABLE `package_features` DROP INDEX `package_features_package_id_feature_code_unique`');
        }

        if (Schema::hasColumn('package_features', 'package_id')) {
            Schema::table('package_features', function (Blueprint $table): void {
                $table->dropColumn('package_id');
            });
        }

        if (! $this->indexExists('package_features', 'package_features_package_uuid_feature_code_unique')) {
            DB::statement('ALTER TABLE `package_features` ADD UNIQUE INDEX `package_features_package_uuid_feature_code_unique` (`package_uuid`, `feature_code`)');
        }

        if (! $this->foreignKeyExists('package_features', 'package_features_package_uuid_foreign')) {
            DB::statement('ALTER TABLE `package_features` ADD CONSTRAINT `package_features_package_uuid_foreign` FOREIGN KEY (`package_uuid`) REFERENCES `packages` (`uuid`) ON DELETE CASCADE');
        }

        DB::statement('ALTER TABLE `package_features` MODIFY `package_uuid` CHAR(36) NOT NULL');
    }

    private function migrateSubscriptions(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        if (! Schema::hasColumn('subscriptions', 'package_uuid')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->uuid('package_uuid')->nullable()->after('package_id');
            });
        }

        if (Schema::hasColumn('subscriptions', 'package_id')) {
            DB::statement(
                'UPDATE subscriptions s JOIN packages p ON s.package_id = p.id SET s.package_uuid = p.uuid WHERE s.package_id IS NOT NULL AND s.package_uuid IS NULL'
            );
        }

        if ($this->foreignKeyExists('subscriptions', 'subscriptions_package_id_foreign')) {
            DB::statement('ALTER TABLE `subscriptions` DROP FOREIGN KEY `subscriptions_package_id_foreign`');
        }

        if (Schema::hasColumn('subscriptions', 'package_id')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->dropColumn('package_id');
            });
        }

        if (! $this->indexExists('subscriptions', 'subscriptions_package_uuid_idx')) {
            DB::statement('ALTER TABLE `subscriptions` ADD INDEX `subscriptions_package_uuid_idx` (`package_uuid`)');
        }

        if (! $this->foreignKeyExists('subscriptions', 'subscriptions_package_uuid_fk')) {
            DB::statement('ALTER TABLE `subscriptions` ADD CONSTRAINT `subscriptions_package_uuid_fk` FOREIGN KEY (`package_uuid`) REFERENCES `packages` (`uuid`) ON DELETE RESTRICT');
        }
    }

    private function dropPackagesLegacyId(): void
    {
        if (! Schema::hasTable('packages') || ! Schema::hasColumn('packages', 'id')) {
            return;
        }

        // Ensure no remaining FK points to packages.id.
        $inboundLegacyFk = DB::selectOne(
            "SELECT COUNT(*) AS total
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = 'packages'
               AND REFERENCED_COLUMN_NAME = 'id'"
        );

        if (((int) ($inboundLegacyFk->total ?? 0)) > 0) {
            throw new RuntimeException('Cannot drop packages.id: inbound foreign keys to packages.id still exist');
        }

        if ($this->indexExists('packages', 'packages_legacy_id_unique')) {
            // MySQL requires AUTO_INCREMENT columns to stay indexed; remove AUTO_INCREMENT first.
            DB::statement('ALTER TABLE `packages` MODIFY `id` BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE `packages` DROP INDEX `packages_legacy_id_unique`');
        }

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn('id');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $indexName]
        );

        return ((int) ($result->total ?? 0)) > 0;
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = ? ',
            [$table, $constraintName, 'FOREIGN KEY']
        );

        return ((int) ($result->total ?? 0)) > 0;
    }
};
