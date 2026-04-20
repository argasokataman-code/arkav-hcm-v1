<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HcmRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Platform roles (no company_id)
        $platformRoles = [
            [
                'code' => 'super_admin',
                'name' => 'Super Administrator',
                'description' => 'Global system administrator with full access',
                'is_system' => true,
                'company_id' => null,
            ],
            [
                'code' => 'internal_support',
                'name' => 'Internal Support',
                'description' => 'Internal support staff with elevated access',
                'is_system' => true,
                'company_id' => null,
            ],
        ];

        foreach ($platformRoles as $role) {
            DB::table('hcm_roles')->updateOrInsert(
                ['code' => $role['code'], 'company_id' => null],
                array_merge($role, ['uuid' => (string) \Illuminate\Support\Str::uuid()])
            );
        }

        // Note: Tenant roles will be created per company during initialization
        // Examples: HR Manager, Payroll Admin, Employee, etc.
    }
}