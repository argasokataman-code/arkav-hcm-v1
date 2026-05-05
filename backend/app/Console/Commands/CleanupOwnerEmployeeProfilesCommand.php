<?php

namespace App\Console\Commands;

use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CleanupOwnerEmployeeProfilesCommand extends Command
{
    protected $signature = 'hcm:cleanup-owner-employee-profiles
        {--companyId= : Only process owners for one company id}
        {--userId= : Only process one owner user id}
        {--dry-run : Show what would change without writing data}';

    protected $description = 'Clean up legacy owner accounts that were incorrectly created as employees.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $protectedAdminEmails = $this->protectedAdminEmails();

        $query = CompanyUser::query()
            ->with(['company', 'user'])
            ->where('status', 'active')
            ->where('role', 'owner');

        if (is_numeric($this->option('companyId'))) {
            $query->where('company_id', (int) $this->option('companyId'));
        }

        if (is_numeric($this->option('userId'))) {
            $query->where('user_id', (int) $this->option('userId'));
        }

        $memberships = $query->orderBy('company_id')->orderBy('user_id')->get();
        if ($memberships->isEmpty()) {
            $this->warn('No active owner memberships matched the filters.');

            return self::SUCCESS;
        }

        $processed = 0;
        $cleaned = 0;
        $skipped = 0;

        foreach ($memberships as $membership) {
            $processed++;

            $company = $membership->company;
            $user = $membership->user;
            if (! $company || ! $user) {
                $skipped++;
                $this->warn(sprintf('Skipped membership #%d because company or user relation is missing.', (int) $membership->id));

                continue;
            }

            if ($company->code === 'default_company') {
                $skipped++;
                $this->warn(sprintf(
                    'Skipped owner user #%d (%s): default_company is protected from cleanup.',
                    $user->id,
                    $user->email,
                ));

                continue;
            }

            if ($protectedAdminEmails->contains(strtolower(trim((string) $user->email)))) {
                $skipped++;
                $this->warn(sprintf(
                    'Skipped owner user #%d (%s): configured global admin email is protected from cleanup.',
                    $user->id,
                    $user->email,
                ));

                continue;
            }

            if ((bool) ($user->is_super_admin ?? false)) {
                $skipped++;
                $this->warn(sprintf(
                    'Skipped owner user #%d (%s): is_super_admin account is protected from cleanup.',
                    $user->id,
                    $user->email,
                ));

                continue;
            }

            $profile = EmployeeProfile::query()->where('user_id', $user->id)->first();
            if (! $profile) {
                $this->line(sprintf('No employee profile for owner user #%d (%s) in company #%d.', $user->id, $user->email, $company->id));
                continue;
            }

            if ((int) $profile->company_id !== (int) $company->id) {
                $skipped++;
                $this->warn(sprintf(
                    'Skipped owner user #%d (%s): employee_profile.company_id=%d does not match owner company_id=%d.',
                    $user->id,
                    $user->email,
                    (int) $profile->company_id,
                    (int) $company->id,
                ));

                continue;
            }

            $dependencyCounts = $this->dependencyCounts($profile);
            $activeDependencies = array_filter($dependencyCounts, static fn (int $count): bool => $count > 0);
            if ($activeDependencies !== []) {
                $skipped++;
                $labels = collect($activeDependencies)
                    ->map(fn (int $count, string $relation): string => $relation.'='.$count)
                    ->implode(', ');

                $this->warn(sprintf(
                    'Skipped owner user #%d (%s): employee profile has dependent HR data (%s).',
                    $user->id,
                    $user->email,
                    $labels,
                ));

                continue;
            }

            $summary = sprintf(
                'Owner user #%d (%s) in company #%d (%s) is eligible for cleanup.',
                $user->id,
                $user->email,
                $company->id,
                $company->code,
            );

            if ($dryRun) {
                $this->info('[dry-run] '.$summary);
                continue;
            }

            DB::transaction(function () use ($company, $profile): void {
                $this->backfillOwnerSettingsFromProfile($company->id, $profile);
                 $profile->forceDelete();
            });

            $cleaned++;
            $this->info($summary.' Employee profile deleted after owner contact backfill.');
        }

        $this->table(
            ['processed', 'cleaned', 'skipped', 'dry_run'],
            [[(string) $processed, (string) $cleaned, (string) $skipped, $dryRun ? 'yes' : 'no']]
        );

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, string>
     */
    private function protectedAdminEmails(): Collection
    {
        return collect([
            config('hcm.admin_email'),
            config('hcm.secondary_admin_email'),
        ])
            ->filter(static fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(static fn (string $value): string => strtolower(trim($value)))
            ->values();
    }

    /**
     * @return array<string, int>
     */
    private function dependencyCounts(EmployeeProfile $profile): array
    {
        return [
            'employmentHistories' => $profile->employmentHistories()->count(),
            'assignments' => $profile->assignments()->count(),
            'compensations' => $profile->compensations()->count(),
            'contracts' => $profile->contracts()->count(),
            'bankAccounts' => $profile->bankAccounts()->count(),
            'taxProfiles' => $profile->taxProfiles()->count(),
            'benefits' => $profile->benefits()->count(),
            'assetAssignments' => $profile->assetAssignments()->count(),
            'emergencyContacts' => $profile->emergencyContacts()->count(),
            'educations' => $profile->educations()->count(),
            'experiences' => $profile->experiences()->count(),
        ];
    }

    private function backfillOwnerSettingsFromProfile(int $companyId, EmployeeProfile $profile): void
    {
        $settings = [
            'owner_phone' => $profile->phone,
            'owner_address' => $profile->address,
            'owner_address_detail' => $profile->address_detail,
        ];

        foreach ($settings as $key => $value) {
            $normalized = is_string($value) ? trim($value) : null;
            if ($normalized === null || $normalized === '') {
                continue;
            }

            $existing = CompanySetting::query()
                ->where('company_id', $companyId)
                ->where('key', $key)
                ->first();

            if ($existing && trim((string) $existing->value) !== '') {
                continue;
            }

            CompanySetting::query()->updateOrCreate(
                ['company_id' => $companyId, 'key' => $key],
                ['value' => $normalized, 'type' => 'string']
            );
        }
    }
}