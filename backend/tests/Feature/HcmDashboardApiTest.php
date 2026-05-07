<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearerToken(string $email, string $designation): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Dashboard User',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['designation' => $designation, 'employment_status' => 'active']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_admin_dashboard_summary_endpoint_returns_expected_structure(): void
    {
        $token = $this->bearerToken('admin-dashboard@example.com', 'HR Admin');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'executive' => ['activeEmployees', 'probationEmployees', 'pkwtDueIn30Days', 'attendanceToday', 'pendingLeaveRequests', 'payrollActiveMonth'],
                    'payrollCommandCenter' => ['periodStatus', 'latestRunStatus', 'latestRunPaymentStatus'],
                    'approvalInbox' => ['pendingLeaveRequest', 'pendingOvertimeRequest'],
                    'workforceAndAlerts' => ['joinerThisMonth', 'resignationThisMonth', 'promotionThisMonth', 'attendanceAnomaly'],
                ],
            ]);
    }

    public function test_employee_dashboard_summary_endpoint_returns_expected_structure(): void
    {
        $token = $this->bearerToken('employee-dashboard-api@example.com', 'Staff');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/v1/hcm/employee-dashboard-summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'profile' => ['name', 'designation', 'profilePhotoUrl'],
                    'attendanceToday' => ['punchState', 'canPunch', 'summaryTotalWorking'],
                    'attendanceStats' => ['todayHours', 'weekHours', 'monthHours'],
                    'leave' => ['total', 'pending', 'approved', 'declined'],
                    'payroll' => ['latestPeriod', 'latestNetPay', 'paymentStatus'],
                    'ui' => ['referenceDate'],
                ],
            ]);
    }

    public function test_admin_dashboard_summary_export_defaults_to_xlsx_with_csv_fallback(): void
    {
        $token = $this->bearerToken('admin-dashboard-export@example.com', 'HR Admin');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/v1/hcm/dashboard-summary/export')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $csvResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/v1/hcm/dashboard-summary/export?format=csv');

        $csvResponse->assertOk();
        $csvResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $csvResponse->streamedContent();
        $this->assertStringContainsString('Section,Metric,Value', $content);
        $this->assertStringContainsString('Executive,"Total Employees",', $content);
    }
}
