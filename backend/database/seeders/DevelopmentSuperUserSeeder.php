<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DevelopmentSuperUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        $this->seedSuperUser(
            email: (string) config('hcm.admin_email', 'qa.login@example.com'),
            password: (string) config('hcm.admin_password', 'StrongPass1'),
            name: 'Super User 1',
            team: 'HR',
            designation: 'Super Admin'
        );

        $this->seedSuperUser(
            email: (string) config('hcm.secondary_admin_email', 'qa.hcm@example.com'),
            password: (string) config('hcm.secondary_admin_password', 'StrongPass1'),
            name: 'Super User 2',
            team: 'HCM',
            designation: 'HCM Admin'
        );
    }

    private function seedSuperUser(string $email, string $password, string $name, string $team, string $designation): User
    {
        $superUser = User::query()->updateOrCreate(
            ['email' => strtolower(trim($email))],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        $companyId = $this->ensureDefaultCompanyMembership($superUser);

        if (! Schema::hasTable('employee_profiles')) {
            return $superUser;
        }

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $superUser->id],
            [
                'company_id' => $companyId,
                'team' => $team,
                'designation' => $designation,
            ]
        );

        return $superUser;
    }

    private function ensureDefaultCompanyMembership(User $user): ?int
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('company_users')) {
            return null;
        }

        $defaultCompany = Company::query()->firstOrCreate(
            ['code' => 'default_company'],
            [
                'name' => 'Default Company',
                'legal_name' => 'Default Company',
                'status' => 'active',
                'owner_user_id' => $user->id,
                'timezone' => (string) config('app.timezone', 'UTC'),
                'currency' => 'IDR',
                'country_code' => 'ID',
            ]
        );

        CompanyUser::query()->firstOrCreate(
            [
                'company_id' => $defaultCompany->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'invited_by_user_id' => null,
            ]
        );

        return (int) $defaultCompany->id;
    }
}
