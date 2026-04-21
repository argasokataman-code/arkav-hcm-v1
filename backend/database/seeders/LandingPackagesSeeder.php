<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\PackageFeature;
use Illuminate\Database\Seeder;

class LandingPackagesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => 'trial',
                'name' => 'Trial (30 Hari)',
                'description' => 'Paket khusus trial untuk evaluasi. Otomatis aktif 30 hari lalu diminta upgrade.',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'billing_unit' => 'company',
                'status' => 'active',
                'color' => '#6B7280',
                'sort_order' => 0,
            ],
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'Cocok untuk tim kecil: employee, absensi, cuti, dan laporan dasar.',
                'monthly_price' => 199000,
                'yearly_price' => 1990000,
                'billing_unit' => 'company',
                'status' => 'active',
                'color' => '#2D7FF9',
                'sort_order' => 10,
            ],
            [
                'code' => 'growth',
                'name' => 'Growth',
                'description' => 'Untuk tim yang mulai serius: tambah payroll run + kontrol proses lebih rapi.',
                'monthly_price' => 399000,
                'yearly_price' => 3990000,
                'billing_unit' => 'company',
                'status' => 'active',
                'color' => '#00A76F',
                'sort_order' => 20,
            ],
            [
                'code' => 'business',
                'name' => 'Business',
                'description' => 'Untuk perusahaan menengah: laporan lebih lengkap + workflow lebih matang.',
                'monthly_price' => 699000,
                'yearly_price' => 6990000,
                'billing_unit' => 'company',
                'status' => 'active',
                'color' => '#FF9800',
                'sort_order' => 30,
            ],
            [
                'code' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'Untuk kebutuhan enterprise: akses penuh, kontrol, dan kesiapan compliance.',
                'monthly_price' => 1299000,
                'yearly_price' => 12990000,
                'billing_unit' => 'company',
                'status' => 'active',
                'color' => '#6C4CF1',
                'sort_order' => 40,
            ],
        ];

        foreach ($rows as $row) {
            $package = Package::query()->updateOrCreate(
                ['code' => $row['code']],
                $row
            );

            $featureTemplate = [
                'employee_management' => 'Employee Management',
                'attendance' => 'Attendance',
                'leave_management' => 'Leave Management',
                'payroll' => 'Payroll',
                'performance' => 'Performance',
                'training' => 'Training',
                'goal_tracking' => 'Goal Tracking',
                'asset_management' => 'Asset Management',
                'api_access' => 'API Access',
                'priority_support' => 'Priority Support',
                'tickets' => 'Tickets',
            ];

            // null = unlimited, 0 = not included, >0 = limit
            $limitsByPackage = [
                // Trial limits intentionally smaller than Starter (sales follow-up friendly)
                'trial' => [20, 1, 1, 0, 0, 0, 0, 0, 0, 0, 1],
                'starter' => [50, 1, 1, 0, 0, 0, 0, 0, 0, 0, 1],
                'growth' => [150, 1, 1, 1, 0, 0, 0, 0, 1, 0, 1],
                'business' => [500, 1, 1, 1, 1, 1, 1, 0, 1, 0, 1],
                'enterprise' => [null, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
            ];

            $i = 0;
            foreach ($featureTemplate as $featureCode => $featureName) {
                PackageFeature::query()->updateOrCreate(
                    [
                        'package_uuid' => $package->uuid,
                        'feature_code' => $featureCode,
                    ],
                    [
                        'feature_name' => $featureName,
                        'limit' => $limitsByPackage[$row['code']][$i],
                    ]
                );
                $i++;
            }
        }

        $this->command?->info('Landing packages seeded (trial, starter, growth, business, enterprise).');
    }
}

