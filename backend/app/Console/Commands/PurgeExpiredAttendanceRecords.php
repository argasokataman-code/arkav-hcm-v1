<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeExpiredAttendanceRecords extends Command
{
    protected $signature = 'pdp:purge-attendance-records {--years=} {--dry-run}';

    protected $description = 'Purge attendance records older than configured retention window (default 5 years).';

    public function handle(): int
    {
        $years = (int) ($this->option('years') ?: config('pdp.retention.attendance_years', 5));
        if ($years <= 0) {
            $this->error('Retention years must be greater than 0.');

            return self::FAILURE;
        }

        $cutoffDate = Carbon::now()->subYears($years)->toDateString();
        $dryRun = (bool) $this->option('dry-run');

        $query = AttendanceRecord::withTrashed()->whereDate('work_date', '<', $cutoffDate);
        $count = (clone $query)->count();

        $this->info('Attendance retention cutoff: '.$cutoffDate);
        $this->line('Matching rows: '.$count);

        if ($dryRun || $count === 0) {
            if ($dryRun) {
                $this->comment('Dry run enabled, no rows deleted.');
            }

            return self::SUCCESS;
        }

        $deleted = $query->forceDelete();
        $this->info('Purged rows: '.(int) $deleted);

        return self::SUCCESS;
    }
}
