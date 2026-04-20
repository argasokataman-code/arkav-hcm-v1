<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (! Schema::hasTable('password_reset_tokens') || ! Schema::hasColumn('password_reset_tokens', 'uuid')) {
            return;
        }

        DB::statement("UPDATE `password_reset_tokens` SET `uuid` = UUID() WHERE `uuid` IS NULL OR `uuid` = ''");
        DB::statement("ALTER TABLE `password_reset_tokens` MODIFY `uuid` CHAR(36) NOT NULL DEFAULT (UUID())");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (! Schema::hasTable('password_reset_tokens') || ! Schema::hasColumn('password_reset_tokens', 'uuid')) {
            return;
        }

        DB::statement("ALTER TABLE `password_reset_tokens` MODIFY `uuid` CHAR(36) NOT NULL");
    }
};