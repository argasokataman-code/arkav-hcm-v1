<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (! Schema::hasTable('holiday_calendars') || ! Schema::hasTable('holidays')) {
            return;
        }

        Schema::table('holiday_calendars', function (Blueprint $table): void {
            if (! Schema::hasColumn('holiday_calendars', 'holiday_id')) {
                $table->unsignedBigInteger('holiday_id')->nullable()->after('company_id');
                $table->index('holiday_id', 'holiday_calendars_holiday_id_idx');
            }
        });

        $holidayMap = DB::table('holidays')
            ->orderBy('id')
            ->get(['id', 'holiday_date', 'title'])
            ->mapWithKeys(function ($row) {
                $date = Carbon::parse((string) $row->holiday_date)->toDateString();
                $key = $date.'|'.mb_strtolower((string) $row->title);

                return [$key => (int) $row->id];
            });

        DB::table('holiday_calendars')
            ->orderBy('id')
            ->get(['id', 'date', 'name', 'holiday_id'])
            ->each(function ($row) use ($holidayMap): void {
                if ((int) ($row->holiday_id ?? 0) > 0) {
                    return;
                }
                $date = Carbon::parse((string) $row->date)->toDateString();
                $key = $date.'|'.mb_strtolower((string) $row->name);
                $holidayId = $holidayMap[$key] ?? null;
                if ($holidayId) {
                    DB::table('holiday_calendars')->where('id', $row->id)->update(['holiday_id' => $holidayId]);
                }
            });

        if (! $isSqlite) {
            Schema::table('holiday_calendars', function (Blueprint $table): void {
                $table->foreign('holiday_id', 'holiday_calendars_holiday_id_fk')
                    ->references('id')
                    ->on('holidays')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (! Schema::hasTable('holiday_calendars')) {
            return;
        }

        Schema::table('holiday_calendars', function (Blueprint $table) use ($isSqlite): void {
            if (Schema::hasColumn('holiday_calendars', 'holiday_id')) {
                if (! $isSqlite) {
                    $table->dropForeign('holiday_calendars_holiday_id_fk');
                }
                $table->dropIndex('holiday_calendars_holiday_id_idx');
                $table->dropColumn('holiday_id');
            }
        });
    }
};
