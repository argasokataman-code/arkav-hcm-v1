<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if (! Schema::hasColumn('notifications', 'user_uuid')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->uuid('user_uuid')->nullable()->after('notifiable_id');
            });
        }

        if (! Schema::hasColumn('notifications', 'company_uuid')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->uuid('company_uuid')->nullable()->after('user_uuid');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'UPDATE notifications n '
                .'JOIN users u ON CAST(n.notifiable_id AS CHAR(36)) = CAST(u.id AS CHAR(36)) '
                .'SET n.user_uuid = u.uuid '
                ."WHERE n.user_uuid IS NULL AND n.notifiable_type = '".addslashes(User::class)."'"
            );

            DB::statement(
                'UPDATE notifications n '
                ."SET n.company_uuid = JSON_UNQUOTE(JSON_EXTRACT(n.data, '$.companyUuid')) "
                .'WHERE n.company_uuid IS NULL '
                ."AND JSON_EXTRACT(n.data, '$.companyUuid') IS NOT NULL"
            );

            DB::statement(
                'UPDATE notifications n '
                ."JOIN company_users cu ON cu.user_uuid = n.user_uuid AND cu.status = 'active' "
                .'SET n.company_uuid = cu.company_uuid '
                .'WHERE n.user_uuid IS NOT NULL AND n.company_uuid IS NULL'
            );
        }

        if (! $this->indexExists('notifications', 'notifications_user_uuid_idx')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->index('user_uuid', 'notifications_user_uuid_idx');
            });
        }

        if (! $this->indexExists('notifications', 'notifications_company_uuid_idx')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->index('company_uuid', 'notifications_company_uuid_idx');
            });
        }

        $this->safeForeign('notifications', 'user_uuid', 'users', 'uuid', 'cascade');
        $this->safeForeign('notifications', 'company_uuid', 'companies', 'uuid', 'null');
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
