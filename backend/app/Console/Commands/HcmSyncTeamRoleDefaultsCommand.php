<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HcmSyncTeamRoleDefaultsCommand extends Command
{
    protected $signature = 'hcm:sync-team-role-defaults {--company_id=* : Optional company IDs to limit sync scope}';

    protected $description = 'Backfill TEAM_LEAD and MANAGER default baseline permissions for existing tenant roles';

    public function handle(): int
    {
        $targetCompanyIds = collect((array) $this->option('company_id'))
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $roleDefaults = [
            'TEAM_LEAD' => [
                'name' => 'Team Lead',
                'permissions' => ['employee.view', 'team.lead', 'report.view'],
            ],
            'MANAGER' => [
                'name' => 'Manager',
                'permissions' => ['employee.view', 'team.lead'],
            ],
        ];

        $requiredCodes = collect($roleDefaults)
            ->flatMap(static fn (array $item): array => $item['permissions'])
            ->unique()
            ->values();

        $permissionIdsByCode = HcmPermission::query()
            ->whereIn('code', $requiredCodes->all())
            ->pluck('id', 'code');

        $missingCodes = $requiredCodes
            ->reject(static fn (string $code): bool => isset($permissionIdsByCode[$code]))
            ->values();

        if ($missingCodes->isNotEmpty()) {
            $this->error('Missing permission codes: '.implode(', ', $missingCodes->all()));
            $this->error('Run HcmPermissionsSeeder/HcmUserManagementSeeder first.');

            return self::FAILURE;
        }

        $companies = Company::query()
            ->when($targetCompanyIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $targetCompanyIds->all()))
            ->orderBy('id')
            ->get(['id', 'name', 'uuid']);

        if ($companies->isEmpty()) {
            $this->warn('No companies found for the requested scope.');

            return self::SUCCESS;
        }

        $roleCreateCount = 0;
        $mappingCreateCount = 0;

        foreach ($companies as $company) {
            foreach ($roleDefaults as $roleCode => $defaults) {
                $role = HcmRole::query()->updateOrCreate(
                    [
                        'company_id' => (int) $company->id,
                        'code' => $roleCode,
                    ],
                    [
                        'name' => (string) $defaults['name'],
                        'status' => 'active',
                        'is_system' => false,
                    ]
                );

                if ($role->wasRecentlyCreated) {
                    $roleCreateCount++;
                }

                foreach ($defaults['permissions'] as $permissionCode) {
                    $permissionId = (int) $permissionIdsByCode[$permissionCode];

                    $inserted = DB::table('hcm_role_permissions')->insertOrIgnore([
                        'role_id' => (int) $role->id,
                        'permission_id' => $permissionId,
                        'company_id' => (int) $company->id,
                        'company_uuid' => (string) ($company->uuid ?? ''),
                        'created_at' => now(),
                    ]);

                    if ($inserted > 0) {
                        $mappingCreateCount += (int) $inserted;
                    }
                }
            }
        }

        $this->info('TEAM_LEAD/MANAGER baseline role sync completed.');
        $this->line('Companies processed: '.$companies->count());
        $this->line('Roles created: '.$roleCreateCount);
        $this->line('Permission mappings inserted: '.$mappingCreateCount);

        return self::SUCCESS;
    }
}
