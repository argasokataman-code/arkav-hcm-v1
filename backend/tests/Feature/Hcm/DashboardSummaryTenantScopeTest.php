<?php

namespace Tests\Feature\Hcm;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSummaryTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test dashboard summary only returns company-scoped data for a user.
     *
     * @return void
     */
    public function test_dashboard_summary_is_company_scoped()
    {
        // Arrange: Find or create user
        $user = User::where('email', 'wismilak@mail.com')->first();
        $this->assertNotNull($user, 'User wismilak@mail.com not found');
        $companyId = $user->employeeProfile->company_id ?? null;
        $this->assertNotNull($companyId, 'User has no company_id');

        // Act: Hit dashboard summary endpoint as this user
        $response = $this->actingAs($user)->getJson('/v1/hcm/dashboard-summary');
        $response->assertStatus(200);
        $data = $response->json('data.executive');

        // Assert: Data only contains employees from the same company
        // (Assume totalEmployees, activeEmployees, inactiveEmployees exposed)
        $this->assertIsArray($data);
        $this->assertArrayHasKey('totalEmployees', $data);
        $this->assertArrayHasKey('activeEmployees', $data);
        $this->assertArrayHasKey('inactiveEmployees', $data);

        // Optionally: Check that the numbers match the DB for this company
        $dbTotal = \App\Models\EmployeeProfile::where('company_id', $companyId)->count();
        $dbActive = \App\Models\EmployeeProfile::where('company_id', $companyId)
            ->whereIn('employment_status', ['active', 'probation'])->count();
        $dbInactive = \App\Models\EmployeeProfile::where('company_id', $companyId)
            ->where('employment_status', 'inactive')->count();

        $this->assertEquals($dbTotal, $data['totalEmployees'], 'Total employee mismatch');
        $this->assertEquals($dbActive, $data['activeEmployees'], 'Active employee mismatch');
        $this->assertEquals($dbInactive, $data['inactiveEmployees'], 'Inactive employee mismatch');
    }
}
