<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            return;
        }

        if (! Schema::hasColumn('notification_preferences', 'user_uuid')) {
            Schema::table('notification_preferences', function (Blueprint $table): void {
                $table->uuid('user_uuid')->nullable()->after('user_id');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'UPDATE notification_preferences np '
                .'JOIN users u ON np.user_id = u.id '
                .'SET np.user_uuid = u.uuid '
                .'WHERE np.user_uuid IS NULL AND np.user_id IS NOT NULL'
            );
        }

        if (! $this->indexExists('notification_preferences', 'notification_preferences_user_uuid_event_channel_uq')) {
            Schema::table('notification_preferences', function (Blueprint $table): void {
                $table->unique(['user_uuid', 'event_key', 'channel'], 'notification_preferences_user_uuid_event_channel_uq');
            });
        }

        if (! $this->indexExists('notification_preferences', 'notification_preferences_user_uuid_enabled_idx')) {
            Schema::table('notification_preferences', function (Blueprint $table): void {
                $table->index(['user_uuid', 'enabled'], 'notification_preferences_user_uuid_enabled_idx');
            });
        }

        $this->safeForeign('notification_preferences', 'user_uuid', 'users', 'uuid', 'cascade');
    }

    public function down(): void
    {
        // Forward-only migration.
    }

    private function safeForeign(string $table, string $column, string $parentTable, string $parentColumn, string $onDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        $name = $this->fkName($table, $column);

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parentTable, $parentColumn, $name, $onDelete): void {
                $foreign = $blueprint->foreign($column, $name)->references($parentColumn)->on($parentTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } elseif ($onDelete === 'null') {
                    $foreign->nullOnDelete();
                } else {
                    $foreign->restrictOnDelete();
                }
            });
        } catch (Throwable $e) {
            $msg = strtolower($e->getMessage());
            if (
                str_contains($msg, 'duplicate')
                || str_contains($msg, 'exists')
                || str_contains($msg, 'already')
                || str_contains($msg, 'cannot add foreign key constraint')
                || str_contains($msg, 'foreign key constraint is incorrectly formed')
                || str_contains($msg, 'missing unique key')
            ) {
                return;
            }

            throw $e;
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return true;
        }

        $count = DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->count();

        return $count > 0;
    }

    private function fkName(string $table, string $column): string
    {
        $base = $table.'_'.$column.'_fk';
        if (strlen($base) <= 64) {
            return $base;
        }

        return substr($table, 0, 24).'_'.substr($column, 0, 24).'_'.substr(md5($base), 0, 10);
    }
};
