<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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
            $rows = DB::table('package_features')
                ->select(['id', 'package_id', 'package_uuid'])
                ->get();

            foreach ($rows as $row) {
                if (! empty($row->package_uuid) || empty($row->package_id)) {
                    continue;
                }

                $packageUuid = DB::table('packages')
                    ->where('id', $row->package_id)
                    ->value('uuid');

                if ($packageUuid) {
                    DB::table('package_features')
                        ->where('id', $row->id)
                        ->update(['package_uuid' => $packageUuid]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('package_features')) {
            return;
        }

        if (Schema::hasColumn('package_features', 'package_uuid')) {
            Schema::table('package_features', function (Blueprint $table): void {
                $table->dropColumn('package_uuid');
            });
        }
    }
};
