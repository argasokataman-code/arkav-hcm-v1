<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Karyawan fiktif untuk uji THR mass-calculate (Generate list).
 *
 * Asumsi tanggal cut-off perhitungan = 2026-04-09 (samakan dengan
 * `calculationCutoffDate` di pengaturan THR tahun 2026 di UI).
 */
class ThrDemoEmployeesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'email' => 'thr.demo.full@example.com',
                'name' => 'THR Demo — Full (≥12 bln)',
                'hire_date' => '2019-01-15',
                'base_salary' => 8_000_000,
                'fixed_allowance' => 1_200_000,
                'employment_status' => 'active',
            ],
            [
                'email' => 'thr.demo.prorata11@example.com',
                'name' => 'THR Demo — Pro rata 11 bln',
                'hire_date' => '2025-05-09',
                'base_salary' => 7_000_000,
                'fixed_allowance' => 500_000,
                'employment_status' => 'active',
            ],
            [
                'email' => 'thr.demo.prorata6@example.com',
                'name' => 'THR Demo — Pro rata 6 bln',
                'hire_date' => '2025-10-09',
                'base_salary' => 6_000_000,
                'fixed_allowance' => 0,
                'employment_status' => 'active',
            ],
            [
                'email' => 'thr.demo.prorata3@example.com',
                'name' => 'THR Demo — Pro rata 3 bln',
                'hire_date' => '2026-01-09',
                'base_salary' => 5_500_000,
                'fixed_allowance' => 300_000,
                'employment_status' => 'active',
            ],
            [
                'email' => 'thr.demo.prorata1@example.com',
                'name' => 'THR Demo — Pro rata 1 bln',
                'hire_date' => '2026-03-09',
                'base_salary' => 4_800_000,
                'fixed_allowance' => 200_000,
                'employment_status' => 'active',
            ],
            [
                'email' => 'thr.demo.nihil@example.com',
                'name' => 'THR Demo — NIHIL (<1 bln penuh)',
                'hire_date' => '2026-03-25',
                'base_salary' => 10_000_000,
                'fixed_allowance' => 0,
                'employment_status' => 'active',
            ],
            [
                'email' => 'thr.demo.probation@example.com',
                'name' => 'THR Demo — Probation (pro rata)',
                'hire_date' => '2025-12-09',
                'base_salary' => 4_000_000,
                'fixed_allowance' => 400_000,
                'employment_status' => 'probation',
            ],
        ];

        foreach ($rows as $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make('StrongPass1'),
                ]
            );

            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'team' => 'Engineering',
                    'designation' => 'Staff',
                    'employment_status' => $row['employment_status'],
                    'hire_date' => $row['hire_date'],
                    'base_salary' => $row['base_salary'],
                    'fixed_allowance' => $row['fixed_allowance'],
                    'bank_name' => 'Bank Central Asia',
                    'bank_account_no' => sprintf('5271%08d', (int) $user->id),
                    'bank_ifsc_code' => 'BCA001',
                    'bank_branch' => 'Jakarta',
                ]
            );
        }
    }
}
