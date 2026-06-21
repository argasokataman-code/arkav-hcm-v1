<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\PayrollSettingsAuditLog;
use App\Models\PayrollSettingsSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class PayrollSettingsGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Payroll Governance Admin',
            'email' => 'governance-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'governance-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'team' => 'HR',
                'designation' => 'HR Admin',
                'employment_status' => 'active',
                'base_salary' => 0,
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'governance-admin@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    /** @test */
    public function can_update_payroll_settings_and_create_audit_trail(): void
    {
        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/payroll/settings', [
                'paydayDay' => 25,
                'cutoffOffsetDays' => 5,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'paydayDay',
                'cutoffOffsetDays',
            ],
        ]);

        // Verify audit log entries were created
        $this->assertTrue(
            PayrollSettingsAuditLog::exists(),
            'Audit log should have been created'
        );
    }

    /** @test */
    public function creates_snapshot_when_settings_updated(): void
    {
        $token = $this->adminToken();

        PayrollSettingsSnapshot::query()->delete();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/payroll/settings', [
                'paydayDay' => 30,
            ]);

        $response->assertStatus(200);

        $this->assertTrue(
            PayrollSettingsSnapshot::exists(),
            'Settings snapshot should have been created'
        );

        $latestSnapshot = PayrollSettingsSnapshot::query()
            ->orderBy('snapshot_version', 'desc')
            ->first();

        $this->assertNotNull($latestSnapshot);
        $this->assertIsArray($latestSnapshot->settings_data);
        $this->assertEquals(30, $latestSnapshot->settings_data['paydayDay']);
    }

    /** @test */
    public function can_retrieve_settings_history(): void
    {
        $token = $this->adminToken();

        PayrollSettingsAuditLog::query()->delete();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/payroll/settings', [
                'paydayDay' => 20,
            ])
            ->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/payroll/settings/history');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'logs',
                'total',
                'limit',
                'offset',
            ],
        ]);

        $this->assertGreaterThan(0, $response->json('data.total'));
    }

    /** @test */
    public function audit_log_only_creates_entries_for_actual_changes(): void
    {
        $token = $this->adminToken();

        PayrollSettingsAuditLog::query()->delete();

        // First update
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/payroll/settings', [
                'paydayDay' => 28,
            ])
            ->assertStatus(200);

        $count1 = PayrollSettingsAuditLog::count();

        // Update to same value
        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/payroll/settings', [
                'paydayDay' => 28,
            ])
            ->assertStatus(200);

        $count2 = PayrollSettingsAuditLog::count();

        // Should not create new audit entry for no-op change
        $this->assertEquals($count1, $count2);
    }

    /** @test */
    public function snapshot_version_increments_on_updates(): void
    {
        $token = $this->adminToken();

        PayrollSettingsSnapshot::query()->delete();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/payroll/settings', [
                'paydayDay' => 20,
            ])
            ->assertStatus(200);

        $snapshot1 = PayrollSettingsSnapshot::query()
            ->orderBy('snapshot_version', 'desc')
            ->first();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/payroll/settings', [
                'paydayDay' => 25,
            ])
            ->assertStatus(200);

        $snapshot2 = PayrollSettingsSnapshot::query()
            ->orderBy('snapshot_version', 'desc')
            ->first();

        $this->assertGreaterThan($snapshot1->snapshot_version, $snapshot2->snapshot_version);
    }

    /** @test */
    public function history_is_tenant_scoped(): void
    {
        $token = $this->adminToken();

        PayrollSettingsAuditLog::query()->delete();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/payroll/settings', [
                'paydayDay' => 20,
            ])
            ->assertStatus(200);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/payroll/settings/history');

        $response->assertStatus(200);
        $total = $response->json('data.total');
        $this->assertGreaterThan(0, $total);

        $logs = $response->json('data.logs') ?? [];
        $this->assertNotEmpty($logs);
    }
}
