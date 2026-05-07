<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAdminTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, string $name): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $resp = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $resp->json('data.accessToken');
    }

    public function test_attendance_admin_index_joins_records_for_scoped_users_even_when_record_company_differs(): void
    {
        $companyA = Company::factory()->create(['timezone' => 'UTC']);
        $companyB = Company::factory()->create(['timezone' => 'UTC']);

        $token = $this->login('qa.login@example.com', 'Admin');
        $admin = User::query()->where('email', 'qa.login@example.com')->firstOrFail();

        // Ensure admin has membership in both companies (request will be scoped via X-Company-Id)
        CompanyUser::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $admin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $admin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        // Create a target user who exists in both companies.
        $u = User::factory()->create();
        CompanyUser::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $u->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $u->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $date = now()->toDateString();

        // Attendance record exists, but for company B.
        AttendanceRecord::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $u->id,
            'work_date' => $date,
            'status' => 'present',
            'check_in_at' => now()->setTime(10, 2, 0),
        ]);

        $respA = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyA->id,
        ])->getJson('/v1/hcm/attendance/admin?date='.$date.'&perPage=100');

        $respA->assertOk()->assertJsonPath('success', true);
        $rowA = collect($respA->json('data'))->firstWhere('userId', $u->id);
        $this->assertNotNull($rowA);
        $this->assertSame('10:02', (string) ($rowA['checkInTime24'] ?? ''), 'Expected company A scoped user to join record regardless of record company_id.');

        // Guardrail: user outside active company A must still never leak into company A response.
        $outsideUser = User::factory()->create();
        CompanyUser::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $outsideUser->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        AttendanceRecord::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $outsideUser->id,
            'work_date' => $date,
            'status' => 'present',
            'check_in_at' => now()->setTime(9, 15, 0),
        ]);

        $rowOutsideA = collect($respA->json('data'))->firstWhere('userId', $outsideUser->id);
        $this->assertNull($rowOutsideA, 'Expected company A response to exclude users without active membership in company A.');

        // Owner (admin) must never appear in the attendance list regardless of membership.
        $rowOwnerA = collect($respA->json('data'))->firstWhere('userId', $admin->id);
        $this->assertNull($rowOwnerA, 'Expected company A response to exclude the owner from attendance admin listing.');

        $respB = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyB->id,
        ])->getJson('/v1/hcm/attendance/admin?date='.$date.'&perPage=100');

        $respB->assertOk()->assertJsonPath('success', true);
        $rowB = collect($respB->json('data'))->firstWhere('userId', $u->id);
        $this->assertNotNull($rowB);
        $this->assertSame('10:02', (string) ($rowB['checkInTime24'] ?? ''), 'Expected company B record to be joined.');
    }
}

