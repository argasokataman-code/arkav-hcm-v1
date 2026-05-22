<?php

namespace Database\Seeders;

use App\Models\PackageAddon;
use Illuminate\Database\Seeder;

class PackageAddonsSeeder extends Seeder
{
    public function run(): void
    {
        $addons = [
            [
                'code' => 'extra_users',
                'name' => 'Extra Users',
                'description' => 'Tambah pengguna tambahan per bulan',
                'price_per_unit' => 25000,
                'unit_name' => 'user / bulan',
                'status' => 'active',
            ],
            [
                'code' => 'extra_companies',
                'name' => 'Extra Companies',
                'description' => 'Kelola perusahaan tambahan dalam satu akun',
                'price_per_unit' => 150000,
                'unit_name' => 'company / bulan',
                'status' => 'active',
            ],
            [
                'code' => 'api_integration',
                'name' => 'API Integration Pack',
                'description' => '1 Juta API calls per bulan',
                'price_per_unit' => 100000,
                'unit_name' => '1M calls / bulan',
                'status' => 'active',
            ],
            [
                'code' => 'storage_pack',
                'name' => 'Storage Pack',
                'description' => 'Penyimpanan dokumen tambahan',
                'price_per_unit' => 15000,
                'unit_name' => 'GB / bulan',
                'status' => 'active',
            ],
            [
                'code' => 'advanced_reports',
                'name' => 'Advanced Reports',
                'description' => 'Laporan analytics dan insights mendalam',
                'price_per_unit' => 75000,
                'unit_name' => 'paket / bulan',
                'status' => 'active',
            ],
        ];

        foreach ($addons as $addon) {
            PackageAddon::query()->updateOrCreate(
                ['code' => $addon['code']],
                $addon
            );
        }
    }
}
