<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'is_super_admin')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_super_admin')
                    ->default(false)
                    ->after('password')
                    ->index('users_is_super_admin_idx');
            });
        }

        // Data backfill: promote the user matching the bootstrap admin email to
        // the persisted global super-admin flag. This is the single source of
        // truth going forward; email config stays only as bootstrap seed
        // fallback for dev/testing runtimes.
        $adminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
        if ($adminEmail !== '') {
            DB::table('users')
                ->whereRaw('LOWER(email) = ?', [$adminEmail])
                ->update(['is_super_admin' => true]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_super_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            try {
                $table->dropIndex('users_is_super_admin_idx');
            } catch (Throwable $e) {
                // Ignore if the index was already dropped or never created.
            }

            $table->dropColumn('is_super_admin');
        });
    }
};
