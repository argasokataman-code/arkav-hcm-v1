<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmTaxGovernanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function employeeTokenForCompany(Company $company, string $email): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Tax Employee',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        CompanyUser::query()->firstOrCreate(
            ['user_id' => $user->id, 'company_id' => $company->id],
            ['role' => 'member', 'status' => 'active']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
            'companyCode' => $company->code,
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function elevateToGlobalAdmin(string $email): void
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $user->is_super_admin = true;
        $user->save();
    }

    private function complianceHeaders(array $admin): array
    {
        return $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $admin['token'],
        ], $admin['company_id']);
    }

    public function test_employee_cannot_access_platform_tax_compliance_endpoints(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-admin-runtime@example.com']);
        $employeeToken = $this->employeeTokenForCompany($admin['company'], 'tax-employee-runtime@example.com');

        $headers = $this->withCompanyContext([
            'Authorization' => 'Bearer ' . $employeeToken,
        ], $admin['company_id']);

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/policies?per_page=5')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/tax-governance/platform-tax-compliance/policies', [
                'subscription_tax_rate' => 11,
                'addon_markup_rate' => 0,
                'billing_month' => '2026-05',
                'effective_from' => '2026-05-01',
                'status' => 'active',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_tenant_admin_without_global_scope_cannot_access_platform_tax_compliance_endpoints(): void
    {
        $tenantAdmin = $this->createHcmAdminWithCompany(['email' => 'tax-tenant-admin-runtime@example.com']);

        $this->withHeaders($this->complianceHeaders($tenantAdmin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/policies?per_page=5')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($this->complianceHeaders($tenantAdmin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_global_admin_can_create_and_list_platform_tax_compliance_policies(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-global-admin-runtime@example.com']);
        $this->elevateToGlobalAdmin('tax-global-admin-runtime@example.com');

        $notes = json_encode([
            'transaction_tax' => [
                'tax_rate' => 11,
                'tax_name' => 'PPN',
                'description' => 'Runtime platform tax compliance policy',
            ],
        ]);

        $createResponse = $this->withHeaders($this->complianceHeaders($admin))
            ->postJson('/v1/hcm/tax-governance/platform-tax-compliance/policies', [
                'subscription_tax_rate' => 11,
                'addon_markup_rate' => 0,
                'billing_cycle_type' => 'yearly',
                'billing_month' => '2026-05',
                'effective_from' => '2026-05-01',
                'status' => 'active',
                'notes' => $notes,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.billing_month', '2026-05')
            ->assertJsonPath('data.billing_cycle_type', 'yearly')
            ->assertJsonPath('data.subscription_tax_rate', 11);

        $this->assertGreaterThanOrEqual(1, (int) $createResponse->json('data.affected_company_count'));

        $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/policies?billing_month=2026-05&per_page=5')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.view_mode', 'global')
            ->assertJsonPath('data.view_context', 'government_tax_compliance')
            ->assertJsonPath('data.items_global.0.billing_month', '2026-05')
            ->assertJsonPath('data.items_global.0.billing_cycle_type', 'yearly')
            ->assertJsonPath('data.items_global.0.subscription_tax_rate', 11)
            ->assertJsonPath('data.items_global.0.transaction_tax_rate', 11)
            ->assertJsonPath('data.items_global.0.source', 'government_tax_compliance_policy')
            ->assertJsonPath('data.items_global.0.is_current_active_rule', true);
    }

    public function test_platform_tax_compliance_policy_requires_complete_global_payload(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-global-admin-validation@example.com']);
        $this->elevateToGlobalAdmin('tax-global-admin-validation@example.com');

        $this->withHeaders($this->complianceHeaders($admin))
            ->postJson('/v1/hcm/tax-governance/platform-tax-compliance/policies', [
                'subscription_tax_rate' => 11,
                'billing_month' => '2026-05',
                'effective_from' => '2026-05-01',
                'status' => 'active',
            ])
            ->assertStatus(422);
    }

    public function test_platform_tax_compliance_report_returns_zero_tax_due_without_policy(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-global-admin-report-zero@example.com']);
        $this->elevateToGlobalAdmin('tax-global-admin-report-zero@example.com');

        $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.policy_configured', false)
            ->assertJsonPath('data.summary_global.total_tax_due', 0)
            ->assertJsonPath('data.summary_global.total_collected_tax_liability', 0);
    }

    public function test_platform_tax_compliance_report_ignores_draft_policy_for_configuration(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-global-admin-draft-only@example.com']);
        $this->elevateToGlobalAdmin('tax-global-admin-draft-only@example.com');

        $this->withHeaders($this->complianceHeaders($admin))
            ->postJson('/v1/hcm/tax-governance/platform-tax-compliance/policies', [
                'subscription_tax_rate' => 11,
                'addon_markup_rate' => 0,
                'billing_cycle_type' => 'monthly',
                'billing_month' => '2026-05',
                'effective_from' => '2026-05-01',
                'status' => 'draft',
                'notes' => json_encode([
                    'transaction_tax' => [
                        'tax_rate' => 11,
                        'tax_name' => 'PPN',
                    ],
                ]),
            ])
            ->assertStatus(201);

        $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/reports?month=2026-05')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.policy_configured', false)
            ->assertJsonPath('data.summary_global.total_tax_due', 0)
            ->assertJsonPath('data.summary_global.total_collected_tax_liability', 0);
    }

    public function test_platform_tax_compliance_policy_list_includes_full_global_history_beyond_page_window(): void
    {
        $admin = $this->createHcmAdminWithCompany(['email' => 'tax-global-admin-history-window@example.com']);
        $this->elevateToGlobalAdmin('tax-global-admin-history-window@example.com');

        $baselineResponse = $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/policies?per_page=20')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $baselineTotal = (int) ($baselineResponse->json('data.meta.items_global_total') ?? 0);

        for ($i = 1; $i <= 25; $i++) {
            $month = date('Y-m', strtotime('2024-01-01 +' . ($i - 1) . ' month'));
            $effectiveFrom = $month . '-01';

            $this->withHeaders($this->complianceHeaders($admin))
                ->postJson('/v1/hcm/tax-governance/platform-tax-compliance/policies', [
                    'subscription_tax_rate' => 11,
                    'addon_markup_rate' => 0,
                    'billing_cycle_type' => 'monthly',
                    'billing_month' => $month,
                    'effective_from' => $effectiveFrom,
                    'status' => 'active',
                    'notes' => json_encode([
                        'transaction_tax' => [
                            'tax_rate' => 11,
                            'tax_name' => 'PPN',
                            'description' => 'history-' . $i,
                        ],
                    ]),
                ])
                ->assertStatus(201);
        }

        $response = $this->withHeaders($this->complianceHeaders($admin))
            ->getJson('/v1/hcm/tax-governance/platform-tax-compliance/policies?per_page=20')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.items_global_total', $baselineTotal + 25);

        $this->assertCount($baselineTotal + 25, $response->json('data.items_global'));
    }
}
