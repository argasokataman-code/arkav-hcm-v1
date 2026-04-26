<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\HcmRole;
use App\Models\HcmPermission;
use App\Services\HcmRbacService;
use Illuminate\Console\Command;

class InitializeTenantRbacCommand extends Command
{
    protected $signature = 'hcm:initialize-tenant-rbac {company_id : The company ID to initialize}';

    protected $description = 'Initialize RBAC setup for a tenant company';

    protected HcmRbacService $rbacService;

    public function __construct(HcmRbacService $rbacService)
    {
        parent::__construct();
        $this->rbacService = $rbacService;
    }

    public function handle(): void
    {
        $companyId = (int) $this->argument('company_id');

        $company = Company::find($companyId);
        if (!$company) {
            $this->error("Company with ID {$companyId} not found.");
            return;
        }

        $this->info("Initializing RBAC for company: {$company->name} (ID: {$companyId})");

        // Create default tenant roles
        $defaultRoles = [
            [
                'code' => 'hr_manager',
                'name' => 'HR Manager',
                'permissions' => [
                    'employee.view', 'employee.create', 'employee.edit',
                    'leave.view', 'leave.approve',
                    'attendance.view', 'attendance.admin',
                    'performance.view', 'performance.manage',
                    'training.view', 'training.manage',
                    'user_management.view', 'user_management.manage',
                    'team.manage',
                    'dashboard.view', 'report.view',
                ],
            ],
            [
                'code' => 'payroll_admin',
                'name' => 'Payroll Administrator',
                'permissions' => [
                    'payroll.view', 'payroll.run', 'payroll.approve',
                    'employee.view',
                    'dashboard.view', 'report.view',
                ],
            ],
            [
                'code' => 'employee',
                'name' => 'Employee',
                'permissions' => [
                    'employee.view', // self
                    'leave.view', 'leave.create',
                    'attendance.view',
                    'training.view',
                    'dashboard.view',
                ],
            ],
        ];

        foreach ($defaultRoles as $roleData) {
            // Check if role already exists
            $existingRole = HcmRole::where('company_id', $companyId)
                ->where('code', $roleData['code'])
                ->first();

            if ($existingRole) {
                $this->info("Role {$roleData['name']} already exists, skipping...");
                continue;
            }

            $this->info("Creating role: {$roleData['name']}");

            $role = HcmRole::create([
                'company_id' => $companyId,
                'code' => $roleData['code'],
                'name' => $roleData['name'],
                'description' => "Default {$roleData['name']} role for {$company->name}",
                'status' => 'active',
                'is_system' => true,
                'created_by' => 1, // System user
            ]);

            // Sync permissions
            $this->rbacService->syncRolePermissions($role, $roleData['permissions'], $companyId);
        }

        $this->info('RBAC initialization completed successfully.');
    }
}