<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceRecapApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Company $company;
    private User $admin;
    private User $emp1;
    private User $emp2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['timezone' => 'Asia/Jakarta']);

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Recap Admin',
            'email' => 'recap-admin@test.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $resp = $this->postJson('/v1/identity/auth/login', [
            'email' => 'recap-admin@test.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $this->token = (string) $resp->json('data.accessToken');
        $this->admin = User::query()->where('email', 'recap-admin@test.com')->firstOrFail();

        CompanyUser::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        // HCM admin RBAC (required by hcm.api.feature middleware)
        $role = HcmRole::query()->create([
            'company_id' => $this->company->id,
            'code' => 'HCM_ADMIN',
            'name' => 'HCM Admin',
            'status' => 'active',
            'is_system' => true,
        ]);
        $perm = HcmPermission::query()->updateOrCreate(
            ['code' => 'hcm.admin'],
            ['module' => 'hcm', 'resource' => 'system', 'action' => 'admin', 'name' => 'HCM Admin', 'is_active' => true]
        );
        DB::table('hcm_role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'company_id' => $this->company->id,
            'company_uuid' => $this->company->uuid,
            'uuid' => (string) Str::uuid(),
        ]);
        HcmUserRole::query()->create([
            'user_id' => $this->admin->id,
            'company_id' => $this->company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        // Active subscription with attendance feature
        $pkg = Package::query()->create([
            'code' => 'recap-test-pkg',
            'name' => 'Recap Test Package',
            'monthly_price' => 0,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);
        PackageFeature::query()->create([
            'package_uuid' => $pkg->uuid,
            'feature_code' => 'attendance',
            'feature_name' => 'Attendance',
            'limit' => null,
        ]);
        Subscription::query()->create([
            'company_id' => $this->company->id,
            'package_uuid' => $pkg->uuid,
            'plan_code' => $pkg->code,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        // Create 2 employees
        $this->emp1 = User::factory()->create(['name' => 'Alice']);
        $this->emp2 = User::factory()->create(['name' => 'Bob']);

        foreach ([$this->emp1, $this->emp2] as $emp) {
            CompanyUser::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $emp->id,
                'role' => 'employee',
                'status' => 'active',
            ]);
        }
    }

    public function test_recap_requires_auth(): void
    {
        $this->getJson('/v1/hcm/attendance/recap?period=weekly')
            ->assertStatus(401);
    }

    public function test_recap_weekly_returns_employees_with_zero_counts_when_no_records(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->withHeader('X-Company-Id', (string) $this->company->id)
            ->getJson('/v1/hcm/attendance/recap?period=weekly')
            ->assertOk();

        $resp->assertJson(['success' => true]);
        $this->assertCount(2, $resp->json('data.items'));
        $meta = $resp->json('data.meta');
        $this->assertEquals('weekly', $meta['period']);
        $this->assertEquals(2, $meta['totalEmployees']);
        $this->assertEquals(0, $meta['totalPresent']);
        $this->assertEquals(0, $meta['totalAbsent']);
        $this->assertEquals(0, $meta['attendanceRate']);
    }

    public function test_recap_weekly_counts_present_and_absent(): void
    {
        $monday = Carbon::now('Asia/Jakarta')->startOfWeek(Carbon::MONDAY);

        // Alice: present 4 days, absent 1 day this week
        for ($i = 0; $i < 4; $i++) {
            AttendanceRecord::query()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->emp1->id,
                'work_date' => $monday->copy()->addDays($i)->toDateString(),
                'status' => 'present',
                'check_in_at' => now(),
            ]);
        }
        AttendanceRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->emp1->id,
            'work_date' => $monday->copy()->addDays(4)->toDateString(),
            'status' => 'absent',
        ]);

        // Bob: absent 2 days
        AttendanceRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->emp2->id,
            'work_date' => $monday->copy()->addDays(1)->toDateString(),
            'status' => 'absent',
        ]);
        AttendanceRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->emp2->id,
            'work_date' => $monday->copy()->addDays(3)->toDateString(),
            'status' => 'absent',
        ]);

        $resp = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->withHeader('X-Company-Id', (string) $this->company->id)
            ->getJson('/v1/hcm/attendance/recap?period=weekly')
            ->assertOk();

        $resp->assertJsonFragment(['success' => true]);
        $items = $resp->json('data.items');
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            if ($item['fullName'] === 'Alice') {
                $this->assertEquals(4, $item['totalPresent'], 'Alice hadir 4 hari');
                $this->assertEquals(1, $item['totalAbsent'], 'Alice bolos 1 hari');
                $this->assertCount(1, $item['absentDates']);
            }
            if ($item['fullName'] === 'Bob') {
                $this->assertEquals(0, $item['totalPresent'], 'Bob hadir 0');
                $this->assertEquals(2, $item['totalAbsent'], 'Bob bolos 2 hari');
                $this->assertCount(2, $item['absentDates']);
            }
        }

        $meta = $resp->json('data.meta');
        $this->assertEquals('weekly', $meta['period']);
        $this->assertIsInt($meta['totalPresent']);
        $this->assertIsInt($meta['totalAbsent']);
        $this->assertIsInt($meta['attendanceRate']);
    }

    public function test_recap_monthly_returns_month_range(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->withHeader('X-Company-Id', (string) $this->company->id)
            ->getJson('/v1/hcm/attendance/recap?period=monthly')
            ->assertOk();

        $meta = $resp->json('data.meta');
        $this->assertEquals('monthly', $meta['period']);
        $this->assertNotNull($meta['startDate']);
        $this->assertNotNull($meta['endDate']);
    }

    public function test_recap_yearly_returns_year_range(): void
    {
        $resp = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->withHeader('X-Company-Id', (string) $this->company->id)
            ->getJson('/v1/hcm/attendance/recap?period=yearly')
            ->assertOk();

        $meta = $resp->json('data.meta');
        $this->assertEquals('yearly', $meta['period']);
    }

    public function test_recap_excludes_owners_from_employee_list(): void
    {
        // Owner should not appear in recap
        $resp = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->withHeader('X-Company-Id', (string) $this->company->id)
            ->getJson('/v1/hcm/attendance/recap?period=weekly')
            ->assertOk();

        $names = array_column($resp->json('data.items'), 'fullName');
        $this->assertNotContains('Recap Admin', $names);
    }
}
