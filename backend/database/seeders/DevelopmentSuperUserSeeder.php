<?php

namespace Database\Seeders;

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

        if (! Schema::hasTable('employee_profiles')) {
            return $superUser;
        }

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $superUser->id],
            [
                'team' => $team,
                'designation' => $designation,
            ]
        );

        return $superUser;
    }
}
