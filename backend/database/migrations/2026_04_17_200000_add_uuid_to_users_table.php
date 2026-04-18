<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom uuid baru (nullable)
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        // 2. Generate UUID untuk semua row existing
        $users = DB::table('users')->whereNull('uuid')->get();
        foreach ($users as $user) {
            DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid()]);
        }

        // 3. Ubah semua FK child (manual, bertahap per tabel)
        // Contoh: sessions, employee_profiles, hcm_user_roles, dsb (lihat checklist)
        // (Langkah ini akan dibuat migration terpisah per tabel)

        // 4. Jadikan uuid sebagai PK, hapus id lama (setelah semua FK update)
        // (Langkah ini dilakukan setelah semua child FK sudah migrate ke UUID)
    }

    public function down(): void
    {
        // Rollback: hapus kolom uuid
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
