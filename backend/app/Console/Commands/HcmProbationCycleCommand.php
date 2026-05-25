<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeEmploymentHistory;
use App\Notifications\ProbationCycleAdminNotification;
use App\Notifications\ProbationEndedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class HcmProbationCycleCommand extends Command
{
    protected $signature = 'hcm:probation-cycle
        {--date= : Reference date (Y-m-d), default today}';

    protected $description = 'Notify employees and admins when probation periods have ended.';

    public function handle(): int
    {
        if (! Schema::hasTable('employee_employment_history') || ! Schema::hasTable('employee_profiles')) {
            $this->warn('Required tables are not available yet. Run migrations first.');

            return self::SUCCESS;
        }

        $dateOption = $this->option('date');

        try {
            $asOf = $dateOption
                ? Carbon::parse((string) $dateOption)->startOfDay()
                : now()->startOfDay();
        } catch (\Throwable) {
            $this->error('Invalid --date format. Use Y-m-d.');

            return self::FAILURE;
        }

        $notified = 0;
        $errors = 0;

        EmployeeEmploymentHistory::query()
            ->where('employment_status', 'probation')
            ->whereDate('probation_end_date', $asOf->toDateString())
            ->with(['employee' => fn ($q) => $q->with('user')])
            ->chunkById(100, function ($records) use (&$notified, &$errors): void {
                foreach ($records as $record) {
                    try {
                        $this->processRecord($record);
                        $notified++;
                    } catch (\Throwable $e) {
                        $this->error("Error processing record #{$record->id}: {$e->getMessage()}");
                        $errors++;
                    }
                }
            });

        $this->info("Probation cycle done. Notified: {$notified}, Errors: {$errors}.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function processRecord(EmployeeEmploymentHistory $record): void
    {
        $profile = $record->employee;
        if (! $profile) {
            return;
        }

        $user = $profile->user;
        if (! $user) {
            return;
        }

        $company = Company::query()->find($profile->company_id);
        if (! $company) {
            return;
        }

        $employeeName = (string) ($user->name ?? '');
        $companyName  = (string) ($company->name ?? '');
        $companyUuid  = (string) ($profile->company_uuid ?? (string) $company->id);
        $contractType = (string) ($profile->contract_type ?? 'permanent');
        $entityUuid   = (string) ($record->uuid ?? (string) $record->id);

        // Notify the employee
        $user->notify(new ProbationEndedNotification(
            employeeName: $employeeName,
            companyName: $companyName,
            companyUuid: $companyUuid,
            contractType: $contractType,
            employmentHistoryUuid: $entityUuid,
        ));

        // Notify all active HCM admins of the company
        $adminUsers = CompanyUser::query()
            ->where('company_id', $profile->company_id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin', 'hcm_admin', 'super_admin'])
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        foreach ($adminUsers as $adminUser) {
            $adminUser->notify(new ProbationCycleAdminNotification(
                employeeName: $employeeName,
                companyName: $companyName,
                companyUuid: $companyUuid,
                contractType: $contractType,
                employmentHistoryUuid: $entityUuid,
            ));
        }
    }
}
