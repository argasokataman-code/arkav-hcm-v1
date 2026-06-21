<?php

namespace App\Console\Commands;

use App\Models\EmployeeAssignment;
use App\Models\EmployeeProfile;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HcmBackfillLegacyTeamAssignmentsCommand extends Command
{
    protected $signature = 'hcm:teams-backfill-legacy
        {--company-id= : Optional company id scope}
        {--team-name= : Optional exact legacy team name filter}
        {--create-missing : Auto-create missing team master rows per company}
        {--dry-run : Preview only, no database write}';

    protected $description = 'Backfill employee_profiles.team_id from legacy employee_profiles.team string values.';

    public function handle(): int
    {
        if (! Schema::hasTable('employee_profiles') || ! Schema::hasTable('teams')) {
            $this->warn('Required tables are missing. Run migrations first.');

            return self::SUCCESS;
        }

        $companyId = $this->option('company-id');
        $companyId = (is_numeric($companyId) && (int) $companyId > 0) ? (int) $companyId : null;
        $teamNameFilter = trim((string) ($this->option('team-name') ?? ''));
        $createMissing = (bool) $this->option('create-missing');
        $dryRun = (bool) $this->option('dry-run');

        $query = EmployeeProfile::query()
            ->whereNull('team_id')
            ->whereNotNull('team')
            ->whereRaw("TRIM(team) <> ''");

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($teamNameFilter !== '') {
            $query->whereRaw('LOWER(TRIM(team)) = ?', [mb_strtolower($teamNameFilter)]);
        }

        $profiles = $query->orderBy('company_id')->orderBy('id')->get();

        if ($profiles->isEmpty()) {
            $this->warn('No legacy team rows matched filters.');

            return self::SUCCESS;
        }

        $processed = 0;
        $reassigned = 0;
        $createdTeams = 0;
        $skippedNoMatch = 0;

        foreach ($profiles as $profile) {
            $processed++;

            $legacyName = $this->normalizeTeamName((string) ($profile->team ?? ''));
            if ($legacyName === '') {
                continue;
            }

            $team = Team::query()
                ->where('company_id', (int) $profile->company_id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($legacyName)])
                ->first();

            if (! $team && $createMissing) {
                if ($dryRun) {
                    $createdTeams++;
                } else {
                    $team = Team::query()->create([
                        'company_id' => (int) $profile->company_id,
                        'department_id' => $profile->department_id,
                        'name' => $legacyName,
                        'is_active' => true,
                    ]);
                    $createdTeams++;
                }
            }

            if (! $team) {
                $skippedNoMatch++;

                continue;
            }

            if ($dryRun) {
                $reassigned++;

                continue;
            }

            DB::transaction(function () use ($profile, $team): void {
                EmployeeProfile::query()
                    ->where('id', $profile->id)
                    ->update([
                        'team_id' => (int) $team->id,
                        'team' => (string) $team->name,
                        'updated_at' => now(),
                    ]);

                EmployeeAssignment::query()
                    ->where('employee_id', $profile->id)
                    ->where('is_primary', true)
                    ->where(function ($query): void {
                        $query->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', now()->toDateString());
                    })
                    ->update([
                        'team_id' => (int) $team->id,
                        'team_name' => (string) $team->name,
                        'updated_at' => now(),
                    ]);
            });

            $reassigned++;
        }

        $this->table(
            ['processed', 'reassigned', 'created_teams', 'skipped_no_match', 'dry_run'],
            [[(string) $processed, (string) $reassigned, (string) $createdTeams, (string) $skippedNoMatch, $dryRun ? 'yes' : 'no']]
        );

        if ($dryRun) {
            $this->info('Dry-run mode: no database changes were applied.');
        }

        return self::SUCCESS;
    }

    private function normalizeTeamName(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;
    }
}
