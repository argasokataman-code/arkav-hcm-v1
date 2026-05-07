<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix company timezone default dari 'UTC' ke 'Asia/Jakarta' untuk companies Indonesia.
 * Companies yang country_code = 'ID' dan timezone masih 'UTC' (nilai default lama) diupdate ke 'Asia/Jakarta'.
 * Ini aman karena country_code 'ID' secara eksplisit mengidentifikasi perusahaan Indonesia.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Update default column definition
        Schema::table('companies', function (Blueprint $table) {
            $table->string('timezone', 64)->default('Asia/Jakarta')->change();
        });

        // Update existing Indonesian companies yang masih pakai UTC default
        DB::table('companies')
            ->where('country_code', 'ID')
            ->where('timezone', 'UTC')
            ->update(['timezone' => 'Asia/Jakarta']);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC')->change();
        });

        // Revert: tidak dikembalikan ke UTC karena tidak ada cara membedakan
        // mana yang memang sengaja UTC vs yang diupdate oleh migration ini.
        // Operator harus manual jika perlu rollback data.
    }
};
