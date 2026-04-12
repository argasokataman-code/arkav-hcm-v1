<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\HcmShift;
use App\Models\Designation;
use App\Models\EmployeeProfile;
use App\Models\Policy;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $qaUser = User::query()->updateOrCreate(
            ['email' => 'qa.login@example.com'],
            [
                'name' => 'QA Login User',
                'password' => Hash::make('StrongPass1'),
            ]
        );

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $qaUser->id],
            [
                'team' => 'HR',
                'designation' => 'Super Admin',
                'phone' => '+62 812-0000-0001',
                'address' => 'Jakarta, Indonesia',
                'bio' => 'Backend and integration focused engineer in HCM module.',
                'bank_name' => 'Bank Central Asia',
                'bank_account_no' => '1234567890',
                'bank_ifsc_code' => 'BCA001',
                'bank_branch' => 'Jakarta Main',
                'emergency_contacts' => [
                    ['label' => 'Primary', 'name' => 'Family Contact', 'relation' => 'Sibling', 'phone' => '+62 811-2222-3333'],
                ],
                'education_items' => [
                    ['school' => 'State University', 'major' => 'Computer Science', 'period' => '2018-2022'],
                ],
                'experience_items' => [
                    ['company' => 'Arcav', 'role' => 'Software Engineer', 'period' => '2022-Present'],
                ],
            ]
        );

        $today = Carbon::now(config('app.timezone'))->toDateString();
        AttendanceRecord::query()->updateOrCreate(
            [
                'user_id' => $qaUser->id,
                'work_date' => $today,
            ],
            [
                'status' => 'present',
                'check_in_at' => Carbon::parse($today.' 08:55:00', config('app.timezone')),
                'check_out_at' => null,
                'break_minutes' => 0,
                'late_minutes' => 0,
            ]
        );

        $hr = Department::query()->updateOrCreate(
            ['code' => 'HR'],
            ['name' => 'Human Resources', 'is_active' => true]
        );
        $eng = Department::query()->updateOrCreate(
            ['code' => 'ENG'],
            ['name' => 'Engineering', 'is_active' => true]
        );
        $mkt = Department::query()->updateOrCreate(
            ['code' => 'MKT'],
            ['name' => 'Marketing', 'is_active' => false]
        );

        Designation::query()->updateOrCreate(
            ['code' => 'SOFTWARE_ENGINEER'],
            ['name' => 'Software Engineer', 'department_id' => $eng->id, 'is_active' => true]
        );
        Designation::query()->updateOrCreate(
            ['code' => 'QA_ENGINEER'],
            ['name' => 'QA Engineer', 'department_id' => $eng->id, 'is_active' => true]
        );
        Designation::query()->updateOrCreate(
            ['code' => 'HR_MANAGER'],
            ['name' => 'HR Manager', 'department_id' => $hr->id, 'is_active' => true]
        );

        Policy::query()->updateOrCreate(
            ['name' => 'Employee Attendance'],
            [
                'department_id' => null,
                'description' => 'Guidelines regarding employee attendance and absence.',
                'effective_date' => '2026-03-01',
            ]
        );
        Policy::query()->updateOrCreate(
            ['name' => 'Leave Approval'],
            [
                'department_id' => $hr->id,
                'description' => 'Standard leave request and approval policy.',
                'effective_date' => '2026-03-12',
            ]
        );
        Policy::query()->updateOrCreate(
            ['name' => 'Information Security'],
            [
                'department_id' => $mkt->id,
                'description' => 'Data handling and credential security standards.',
                'effective_date' => '2026-03-20',
            ]
        );

        HcmShift::query()->updateOrCreate(
            ['code' => 'office_standard'],
            [
                'name' => 'Office Standard',
                'start_time' => '09:00',
                'end_time' => '18:00',
                'description' => 'Shift reguler kantor',
                'is_active' => true,
                'sort_order' => 10,
            ]
        );
        HcmShift::query()->updateOrCreate(
            ['code' => 'late_shift'],
            [
                'name' => 'Late Shift',
                'start_time' => '10:00',
                'end_time' => '19:00',
                'description' => null,
                'is_active' => true,
                'sort_order' => 20,
            ]
        );

        // Seed Performance demo data (Phase 1).
        $this->call(PerformanceSeeder::class);

        // Karyawan demo masa kerja bervariasi untuk uji THR (cut-off contoh: 2026-04-09).
        $this->call(ThrDemoEmployeesSeeder::class);

        // Rekening bank untuk profil yang masih kosong (karyawan luar demo / seed lama).
        $this->call(EmployeeProfileBankBackfillSeeder::class);
    }
}
