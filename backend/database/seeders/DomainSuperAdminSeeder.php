<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Domain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DomainSuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::query()
            ->orderBy('id')
            ->limit(8)
            ->get(['id', 'name', 'code']);

        if ($companies->isEmpty()) {
            $this->command?->warn('DomainSuperAdminSeeder skipped: no companies found. Seed companies first.');

            return;
        }

        $rows = [
            ['sub' => 'portal', 'type' => 'dns', 'status' => 'verified'],
            ['sub' => 'app', 'type' => 'file', 'status' => 'pending'],
            ['sub' => 'hris', 'type' => 'dns', 'status' => 'failed'],
            ['sub' => 'payroll', 'type' => 'file', 'status' => 'verified'],
            ['sub' => 'employee', 'type' => 'dns', 'status' => 'pending'],
            ['sub' => 'attendance', 'type' => 'file', 'status' => 'failed'],
            ['sub' => 'recruitment', 'type' => 'dns', 'status' => 'verified'],
            ['sub' => 'benefits', 'type' => 'file', 'status' => 'pending'],
            ['sub' => 'performance', 'type' => 'dns', 'status' => 'failed'],
            ['sub' => 'training', 'type' => 'file', 'status' => 'verified'],
            ['sub' => 'analytics', 'type' => 'dns', 'status' => 'pending'],
            ['sub' => 'selfservice', 'type' => 'file', 'status' => 'verified'],
        ];

        DB::transaction(function () use ($companies, $rows): void {
            foreach ($rows as $idx => $row) {
                $company = $companies[$idx % $companies->count()];
                $domainName = sprintf('%s.%s.arcav-demo.test', $row['sub'], $company->code ?: 'company');
                $token = 'verify_' . Str::lower(Str::random(24));

                $verifiedAt = null;
                if ($row['status'] === 'verified') {
                    $verifiedAt = Carbon::now()->subDays(($idx % 10) + 1);
                }

                $verificationData = $row['type'] === 'dns'
                    ? [
                        'host' => '@',
                        'record_type' => 'TXT',
                        'record_value' => 'arcav-verification=' . $token,
                        'last_check_at' => Carbon::now()->subHours(($idx % 12) + 1)->toIso8601String(),
                        'last_check_result' => $row['status'],
                    ]
                    : [
                        'path' => '/.well-known/arcav-verification.txt',
                        'expected_content' => $token,
                        'last_check_at' => Carbon::now()->subHours(($idx % 8) + 2)->toIso8601String(),
                        'last_check_result' => $row['status'],
                    ];

                Domain::query()->updateOrCreate(
                    ['domain_name' => $domainName],
                    [
                        'company_id' => $company->id,
                        'verification_type' => $row['type'],
                        'status' => $row['status'],
                        'verification_token' => $token,
                        'verification_data' => $verificationData,
                        'verified_at' => $verifiedAt,
                        'notes' => sprintf(
                            'Super admin seed dataset (%s / %s) for company %s',
                            $row['status'],
                            $row['type'],
                            $company->name
                        ),
                    ]
                );
            }
        });

        $this->command?->info('DomainSuperAdminSeeder completed: detailed domain dataset ready for super admin checks.');
    }
}
