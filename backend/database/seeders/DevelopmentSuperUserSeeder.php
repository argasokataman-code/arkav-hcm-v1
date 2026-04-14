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

        $adminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
        $adminPassword = (string) config('hcm.admin_password', 'StrongPass1');

        $superUser = User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Super User 1',
                'password' => Hash::make($adminPassword),
            ]
        );

        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $superUser->id],
            [
                'team' => 'HR',
                'designation' => 'Super Admin',
            ]
        );
    }
}
