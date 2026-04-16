<?php

namespace App\Console\Commands;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmScheduleTiming;
use App\Models\HcmShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HcmBackfillTenantTestingDataCommand extends Command
{
    protected $signature = 'hcm:backfill-tenant-testing-data
        {--companyId= : Target company id (defaults to first company)}
        {--dry-run : Show what would change without writing}
        {--days=30 : Backfill attendance work_date within last N days (0 = all)}
    ';

    protected $description = 'Backfill tenant/company data for HCM testing: ensure company_id set and nullable profile fields have non-empty defaults.';

    public function handle(): int
    {
        $companyId = $this->resolveCompanyId();
        if (! $companyId) {
            $this->error('No company found.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $days = max(0, (int) $this->option('days'));
        $since = $days > 0 ? Carbon::now(config('app.timezone'))->subDays($days)->toDateString() : null;

        $this->info('Target company_id='.$companyId.($dryRun ? ' (dry-run)' : ''));

        $this->backfillEmployeeProfiles($companyId, $dryRun);
        $this->backfillAttendanceRecords($companyId, $since, $dryRun);
        $this->backfillScheduleTables($companyId, $dryRun);

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function resolveCompanyId(): ?int
    {
        $opt = $this->option('companyId');
        if (is_numeric($opt)) {
            return (int) $opt;
        }

        return Company::query()->orderBy('id')->value('id');
    }

    private function userIdsForCompany(int $companyId): array
    {
        return CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }

    private function backfillEmployeeProfiles(int $companyId, bool $dryRun): void
    {
        $userIds = $this->userIdsForCompany($companyId);
        if ($userIds === []) {
            $this->warn('No active company members found in company_users for company_id='.$companyId);
            return;
        }

        $this->line('Backfilling employee_profiles for '.count($userIds).' users...');

        $defaultsJson = [
            'emergency_contacts' => [],
            'education_items' => [],
            'experience_items' => [],
        ];

        $count = 0;
        foreach ($userIds as $userId) {
            /** @var User|null $user */
            $user = User::query()->whereKey($userId)->first();
            if (! $user) {
                continue;
            }

            $payload = [
                // prefer stable non-empty defaults for testing
                'team' => 'General',
                'designation' => 'Staff',
                'phone' => $user->phone ?? '081234567890',
                'address' => '—',
                'bio' => '—',
                'bank_name' => 'Bank Demo',
                'bank_account_no' => '0000000000',
                'bank_ifsc_code' => 'DEMO0000',
                'bank_branch' => 'Demo Branch',
            ] + $defaultsJson;

            if ($dryRun) {
                $count++;
                continue;
            }

            // Ensure profile exists and fill only missing / empty values.
            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'team' => DB::raw("COALESCE(NULLIF(team, ''), '".addslashes($payload['team'])."')"),
                    'designation' => DB::raw("COALESCE(NULLIF(designation, ''), '".addslashes($payload['designation'])."')"),
                    'phone' => DB::raw("COALESCE(NULLIF(phone, ''), '".addslashes($payload['phone'])."')"),
                    'address' => DB::raw("COALESCE(NULLIF(address, ''), '—')"),
                    'bio' => DB::raw("COALESCE(NULLIF(bio, ''), '—')"),
                    'bank_name' => DB::raw("COALESCE(NULLIF(bank_name, ''), 'Bank Demo')"),
                    'bank_account_no' => DB::raw("COALESCE(NULLIF(bank_account_no, ''), '0000000000')"),
                    'bank_ifsc_code' => DB::raw("COALESCE(NULLIF(bank_ifsc_code, ''), 'DEMO0000')"),
                    'bank_branch' => DB::raw("COALESCE(NULLIF(bank_branch, ''), 'Demo Branch')"),
                    // For json columns, set [] when NULL (leave non-null as-is)
                    'emergency_contacts' => DB::raw('COALESCE(emergency_contacts, json_array())'),
                    'education_items' => DB::raw('COALESCE(education_items, json_array())'),
                    'experience_items' => DB::raw('COALESCE(experience_items, json_array())'),
                ]
            );

            $count++;
        }

        $this->info('Employee profiles processed: '.$count);
    }

    private function backfillAttendanceRecords(int $companyId, ?string $sinceYmd, bool $dryRun): void
    {
        $userIds = $this->userIdsForCompany($companyId);
        if ($userIds === []) {
            return;
        }

        $this->line('Backfilling attendance_records.company_id for members...');

        $q = AttendanceRecord::query()->whereIn('user_id', $userIds)->whereNull('company_id');
        if ($sinceYmd) {
            $q->whereDate('work_date', '>=', $sinceYmd);
        }

        $count = (clone $q)->count();
        $this->info('Attendance records with NULL company_id: '.$count);

        if ($dryRun || $count === 0) {
            return;
        }

        (clone $q)->update(['company_id' => $companyId]);

        // Also normalize null/empty status fields for testing consistency.
        AttendanceRecord::query()
            ->whereIn('user_id', $userIds)
            ->where(function (Builder $inner): void {
                $inner->whereNull('status')->orWhere('status', '');
            })
            ->update(['status' => 'present']);
    }

    private function backfillScheduleTables(int $companyId, bool $dryRun): void
    {
        $this->line('Backfilling hcm_shifts/hcm_schedule_timings company_id...');

        if ($dryRun) {
            return;
        }

        HcmShift::query()->whereNull('company_id')->update(['company_id' => $companyId]);
        HcmScheduleTiming::query()->whereNull('company_id')->update(['company_id' => $companyId]);
    }
}

