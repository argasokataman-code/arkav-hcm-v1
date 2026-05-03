<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions table di-cutover UUID PK oleh migration sebelumnya, tapi
 * Laravel DatabaseSessionHandler tidak menulis kolom uuid sehingga semua
 * session write gagal silently (uuid NOT NULL tanpa default = INSERT error).
 * Akibatnya sessions tidak pernah tersimpan → CSRF token selalu mismatch → 419.
 *
 * Fix: kembalikan id sebagai PK, buat uuid nullable (sessions bersifat ephemeral
 * sehingga tidak membutuhkan UUID sebagai identifier keamanan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (! Schema::hasTable('sessions')) {
            return;
        }

        // Kosongkan sessions lama yang mungkin corrupt karena UUID issue
        DB::table('sessions')->truncate();

        // Drop UUID primary key, restore id sebagai PK, buat uuid nullable
        DB::statement('ALTER TABLE `sessions` DROP PRIMARY KEY');
        DB::statement('ALTER TABLE `sessions` MODIFY `uuid` CHAR(36) NULL');
        DB::statement('ALTER TABLE `sessions` ADD PRIMARY KEY (`id`(255))');
    }

    public function down(): void
    {
        // Forward-only — sengaja tidak di-reverse karena ini bugfix.
    }
};
