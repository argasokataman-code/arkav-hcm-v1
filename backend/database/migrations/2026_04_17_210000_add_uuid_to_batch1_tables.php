<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom uuid baru (nullable) ke companies
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                if (! Schema::hasColumn('companies', 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }
            });
            // Generate UUID untuk data existing
            $companies = DB::table('companies')->whereNull('uuid')->get();
            foreach ($companies as $company) {
                DB::table('companies')->where('id', $company->id)->update(['uuid' => (string) Str::uuid()]);
            }
        }

        // 2. Tambah kolom uuid baru (nullable) ke employee_profiles
        if (Schema::hasTable('employee_profiles')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('employee_profiles', 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }
            });
            $profiles = DB::table('employee_profiles')->whereNull('uuid')->get();
            foreach ($profiles as $profile) {
                DB::table('employee_profiles')->where('id', $profile->id)->update(['uuid' => (string) Str::uuid()]);
            }
        }

        // 3. Tambah kolom uuid baru (nullable) ke hcm_user_roles
        if (Schema::hasTable('hcm_user_roles')) {
            Schema::table('hcm_user_roles', function (Blueprint $table) {
                if (! Schema::hasColumn('hcm_user_roles', 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }
            });
            $roles = DB::table('hcm_user_roles')->whereNull('uuid')->get();
            foreach ($roles as $role) {
                DB::table('hcm_user_roles')->where('id', $role->id)->update(['uuid' => (string) Str::uuid()]);
            }
        }

        // 4. Tambah kolom uuid baru (nullable) ke sessions
        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->uuid('user_uuid')->nullable()->after('user_id');
            });
            // FK update akan dilakukan setelah semua user FK migrate
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'uuid')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }
        if (Schema::hasTable('employee_profiles') && Schema::hasColumn('employee_profiles', 'uuid')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }
        if (Schema::hasTable('hcm_user_roles') && Schema::hasColumn('hcm_user_roles', 'uuid')) {
            Schema::table('hcm_user_roles', function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }
        if (Schema::hasTable('sessions')) {
            Schema::table('sessions', function (Blueprint $table) {
                if (Schema::hasColumn('sessions', 'user_uuid')) {
                    $table->dropColumn('user_uuid');
                }
            });
        }
    }
};
