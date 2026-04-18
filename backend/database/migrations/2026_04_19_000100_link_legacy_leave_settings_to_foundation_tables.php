<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (Schema::hasTable('hcm_leave_type_settings')) {
            Schema::table('hcm_leave_type_settings', function (Blueprint $table): void {
                if (! Schema::hasColumn('hcm_leave_type_settings', 'leave_type_id')) {
                    $table->unsignedBigInteger('leave_type_id')->nullable()->after('code');
                    $table->index('leave_type_id', 'hcm_leave_type_settings_leave_type_id_idx');
                }
            });

            $typeIdByCode = DB::table('leave_types')->pluck('id', 'code');
            DB::table('hcm_leave_type_settings')
                ->whereNull('leave_type_id')
                ->orderBy('id')
                ->get(['id', 'code'])
                ->each(function ($row) use ($typeIdByCode): void {
                    $typeId = $typeIdByCode[$row->code] ?? null;
                    if ($typeId) {
                        DB::table('hcm_leave_type_settings')
                            ->where('id', $row->id)
                            ->update(['leave_type_id' => $typeId]);
                    }
                });

            if (! $isSqlite) {
                Schema::table('hcm_leave_type_settings', function (Blueprint $table): void {
                    $table->foreign('leave_type_id', 'hcm_leave_type_settings_leave_type_id_fk')
                        ->references('id')
                        ->on('leave_types')
                        ->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('hcm_leave_custom_policies')) {
            Schema::table('hcm_leave_custom_policies', function (Blueprint $table) use ($isSqlite): void {
                if (! Schema::hasColumn('hcm_leave_custom_policies', 'leave_type_id')) {
                    $table->unsignedBigInteger('leave_type_id')->nullable()->after('leave_type_code');
                    $table->index('leave_type_id', 'hcm_leave_custom_policies_leave_type_id_idx');
                }
                if (! Schema::hasColumn('hcm_leave_custom_policies', 'leave_policy_id')) {
                    $table->unsignedBigInteger('leave_policy_id')->nullable()->after('leave_type_id');
                    $table->index('leave_policy_id', 'hcm_leave_custom_policies_leave_policy_id_idx');
                }
            });

            $typeIdByLegacyCode = DB::table('hcm_leave_type_settings')->pluck('leave_type_id', 'code');
            $typeIdByCode = DB::table('leave_types')->pluck('id', 'code');
            $policyIdByTypeAndName = DB::table('leave_policies')->get(['id', 'leave_type_id', 'name'])
                ->mapWithKeys(fn ($row) => [((int) $row->leave_type_id).'|'.$row->name => (int) $row->id]);

            DB::table('hcm_leave_custom_policies')
                ->orderBy('id')
                ->get(['id', 'leave_type_code', 'leave_type_id', 'leave_policy_id', 'name'])
                ->each(function ($row) use ($typeIdByLegacyCode, $typeIdByCode, $policyIdByTypeAndName): void {
                    $resolvedTypeId = $row->leave_type_id
                        ?: ($typeIdByLegacyCode[$row->leave_type_code] ?? null)
                        ?: ($typeIdByCode[$row->leave_type_code] ?? null);

                    $resolvedPolicyId = $row->leave_policy_id;
                    if (! $resolvedPolicyId && $resolvedTypeId) {
                        $resolvedPolicyId = $policyIdByTypeAndName[((int) $resolvedTypeId).'|'.('Legacy Custom: '.$row->name)] ?? null;
                    }

                    $updates = [];
                    if ($resolvedTypeId && (int) ($row->leave_type_id ?? 0) !== (int) $resolvedTypeId) {
                        $updates['leave_type_id'] = $resolvedTypeId;
                    }
                    if ($resolvedPolicyId && (int) ($row->leave_policy_id ?? 0) !== (int) $resolvedPolicyId) {
                        $updates['leave_policy_id'] = $resolvedPolicyId;
                    }

                    if ($updates !== []) {
                        DB::table('hcm_leave_custom_policies')->where('id', $row->id)->update($updates);
                    }
                });

            if (Schema::hasColumn('hcm_leave_custom_policies', 'leave_type_uuid') && Schema::hasTable('leave_types') && Schema::hasColumn('leave_types', 'uuid')) {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('UPDATE hcm_leave_custom_policies p JOIN leave_types t ON p.leave_type_id = t.id SET p.leave_type_uuid = t.uuid WHERE p.leave_type_id IS NOT NULL AND p.leave_type_uuid IS NULL');
                } else {
                    DB::table('hcm_leave_custom_policies')
                        ->whereNotNull('leave_type_id')
                        ->whereNull('leave_type_uuid')
                        ->orderBy('id')
                        ->get(['id', 'leave_type_id'])
                        ->each(function ($row): void {
                            $uuid = DB::table('leave_types')->where('id', $row->leave_type_id)->value('uuid');
                            if ($uuid) {
                                DB::table('hcm_leave_custom_policies')->where('id', $row->id)->update(['leave_type_uuid' => $uuid]);
                            }
                        });
                }
            }

            if (Schema::hasColumn('hcm_leave_custom_policies', 'leave_policy_uuid') && Schema::hasTable('leave_policies') && Schema::hasColumn('leave_policies', 'uuid')) {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement('UPDATE hcm_leave_custom_policies p JOIN leave_policies lp ON p.leave_policy_id = lp.id SET p.leave_policy_uuid = lp.uuid WHERE p.leave_policy_id IS NOT NULL AND p.leave_policy_uuid IS NULL');
                } else {
                    DB::table('hcm_leave_custom_policies')
                        ->whereNotNull('leave_policy_id')
                        ->whereNull('leave_policy_uuid')
                        ->orderBy('id')
                        ->get(['id', 'leave_policy_id'])
                        ->each(function ($row): void {
                            $uuid = DB::table('leave_policies')->where('id', $row->leave_policy_id)->value('uuid');
                            if ($uuid) {
                                DB::table('hcm_leave_custom_policies')->where('id', $row->id)->update(['leave_policy_uuid' => $uuid]);
                            }
                        });
                }
            }

            if (! $isSqlite) {
                Schema::table('hcm_leave_custom_policies', function (Blueprint $table): void {
                    $table->foreign('leave_type_id', 'hcm_leave_custom_policies_leave_type_id_fk')
                        ->references('id')
                        ->on('leave_types')
                        ->nullOnDelete();
                    $table->foreign('leave_policy_id', 'hcm_leave_custom_policies_leave_policy_id_fk')
                        ->references('id')
                        ->on('leave_policies')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (Schema::hasTable('hcm_leave_custom_policies')) {
            Schema::table('hcm_leave_custom_policies', function (Blueprint $table) use ($isSqlite): void {
                if (Schema::hasColumn('hcm_leave_custom_policies', 'leave_policy_id')) {
                    if (! $isSqlite) {
                        $table->dropForeign('hcm_leave_custom_policies_leave_policy_id_fk');
                    }
                    $table->dropIndex('hcm_leave_custom_policies_leave_policy_id_idx');
                    $table->dropColumn('leave_policy_id');
                }
                if (Schema::hasColumn('hcm_leave_custom_policies', 'leave_type_id')) {
                    if (! $isSqlite) {
                        $table->dropForeign('hcm_leave_custom_policies_leave_type_id_fk');
                    }
                    $table->dropIndex('hcm_leave_custom_policies_leave_type_id_idx');
                    $table->dropColumn('leave_type_id');
                }
            });
        }

        if (Schema::hasTable('hcm_leave_type_settings')) {
            Schema::table('hcm_leave_type_settings', function (Blueprint $table) use ($isSqlite): void {
                if (Schema::hasColumn('hcm_leave_type_settings', 'leave_type_id')) {
                    if (! $isSqlite) {
                        $table->dropForeign('hcm_leave_type_settings_leave_type_id_fk');
                    }
                    $table->dropIndex('hcm_leave_type_settings_leave_type_id_idx');
                    $table->dropColumn('leave_type_id');
                }
            });
        }
    }
};
