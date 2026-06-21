<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\PerformanceCycle;
use App\Models\PerformanceIndicatorTemplate;
use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class PerformanceApiTest extends TestCase
{
    use RefreshDatabase;

    private ?Company $company = null;

    private function login(string $email, string $name, ?string $designation = null): array
    {
        $company = $this->performanceCompany();

        if ($designation !== null && str_contains(strtolower($designation), 'hr admin')) {
            $result = $this->createHcmAdminWithCompany([
                'name' => $name,
                'email' => $email,
                'password' => 'StrongPass1',
            ], $company);

            $user = User::query()->where('email', $email)->firstOrFail();
            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['company_id' => $company->id, 'designation' => $designation]
            );

            return [
                'user' => $user,
                'token' => $result['token'],
            ];
        }

        $this->postJson('/v1/identity/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        CompanyUser::query()->updateOrCreate(
            ['user_id' => $user->id, 'company_id' => $company->id],
            ['role' => 'employee', 'status' => 'active']
        );

        if ($designation !== null) {
            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['company_id' => $company->id, 'designation' => $designation]
            );
        }

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
            'companyCode' => $company->code,
        ])->assertOk();

        return [
            'user' => $user,
            'token' => (string) $login->json('data.accessToken'),
        ];
    }

    private function performanceCompany(): Company
    {
        if ($this->company instanceof Company) {
            return $this->company;
        }

        $this->company = Company::query()->create([
            'name' => 'Performance Test Company',
            'code' => 'PERFTEST',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        return $this->company;
    }

    private function adminToken(): string
    {
        return $this->login('perf-admin@example.com', 'Perf Admin', 'HR Admin')['token'];
    }

    public function test_end_to_end_employee_manager_admin_workflow(): void
    {
        $admin = $this->login('perf-admin@example.com', 'Perf Admin', 'HR Admin');
        $manager = $this->login('perf-manager@example.com', 'Perf Manager', 'Manager');
        $employee = $this->login('perf-employee@example.com', 'Perf Employee', 'Staff');

        // Link employee -> manager.
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee['user']->id],
            ['company_id' => $this->performanceCompany()->id, 'designation' => 'Staff', 'manager_user_id' => $manager['user']->id]
        );

        $hAdmin = $this->withCompanyContext(['Authorization' => 'Bearer '.$admin['token']], $this->performanceCompany());
        $hMgr = $this->withCompanyContext(['Authorization' => 'Bearer '.$manager['token']], $this->performanceCompany());
        $hEmp = $this->withCompanyContext(['Authorization' => 'Bearer '.$employee['token']], $this->performanceCompany());

        // Admin creates template + items.
        $tpl = $this->withHeaders($hAdmin)->postJson('/v1/hcm/performance/indicator-templates', [
            'name' => 'ENG IC',
            'department' => 'Engineering',
            'designation' => 'Software Engineer',
            'isActive' => true,
        ])->assertStatus(201)->assertJsonPath('success', true);
        $templateId = (int) $tpl->json('data.id');

        $kpiItem = $this->withHeaders($hAdmin)->postJson("/v1/hcm/performance/indicator-templates/{$templateId}/items", [
            'section' => 'kpi',
            'title' => 'Delivery',
            'weight' => 70,
            'sortOrder' => 1,
        ])->assertStatus(201);
        $behaviorItem = $this->withHeaders($hAdmin)->postJson("/v1/hcm/performance/indicator-templates/{$templateId}/items", [
            'section' => 'behavioral',
            'title' => 'Collaboration',
            'sortOrder' => 2,
            'ratingScaleMin' => 1,
            'ratingScaleMax' => 5,
        ])->assertStatus(201);

        // Admin creates cycle + activate.
        $cycle = $this->withHeaders($hAdmin)->postJson('/v1/hcm/performance/cycles', [
            'name' => '2026 H1',
            'periodStart' => '2026-01-01',
            'periodEnd' => '2026-06-30',
        ])->assertStatus(201);
        $cycleId = (int) $cycle->json('data.id');
        $this->withHeaders($hAdmin)->postJson("/v1/hcm/performance/cycles/{$cycleId}/activate")
            ->assertOk()->assertJsonPath('success', true);

        // Admin creates review for employee.
        $reviewCreate = $this->withHeaders($hAdmin)->postJson('/v1/hcm/performance/reviews', [
            'cycleId' => $cycleId,
            'userId' => $employee['user']->id,
            'templateId' => $templateId,
        ])->assertStatus(201)->assertJsonPath('success', true);
        $reviewId = (int) $reviewCreate->json('data.id');

        // Employee can view & edit draft.
        $showEmp = $this->withHeaders($hEmp)->getJson("/v1/hcm/performance/reviews/{$reviewId}")
            ->assertOk()->assertJsonPath('data.status', 'draft');
        $items = $showEmp->json('data.items');
        $this->assertCount(2, $items);
        $itemKpiId = (int) collect($items)->firstWhere('section', 'kpi')['id'];
        $itemBehId = (int) collect($items)->firstWhere('section', 'behavioral')['id'];

        $this->withHeaders($hEmp)->putJson("/v1/hcm/performance/reviews/{$reviewId}", [
            'selfNote' => 'Self note',
            'scores' => [
                ['itemId' => $itemKpiId, 'score' => 80, 'comment' => 'ok'],
                ['itemId' => $itemBehId, 'score' => 4, 'comment' => 'good'],
            ],
        ])->assertOk()->assertJsonPath('success', true);

        // Employee submits.
        $this->withHeaders($hEmp)->postJson("/v1/hcm/performance/reviews/{$reviewId}/submit")
            ->assertOk()->assertJsonPath('success', true);

        // Manager sees it in team scope and can update.
        $this->withHeaders($hMgr)->getJson('/v1/hcm/performance/reviews?scope=team&perPage=50')
            ->assertOk()->assertJsonPath('success', true);
        $this->withHeaders($hMgr)->putJson("/v1/hcm/performance/reviews/{$reviewId}/manager", [
            'managerNote' => 'Manager note',
            'scores' => [
                ['itemId' => $itemKpiId, 'score' => 85, 'comment' => 'better'],
                ['itemId' => $itemBehId, 'score' => 5, 'comment' => 'great'],
            ],
        ])->assertOk()->assertJsonPath('success', true);
        $this->withHeaders($hMgr)->postJson("/v1/hcm/performance/reviews/{$reviewId}/manager-complete")
            ->assertOk()->assertJsonPath('success', true);

        // Admin final scoring + finalize.
        $this->withHeaders($hAdmin)->putJson("/v1/hcm/performance/reviews/{$reviewId}/final", [
            'finalNote' => 'Final note',
            'scores' => [
                ['itemId' => $itemKpiId, 'score' => 90, 'comment' => 'final kpi'],
                ['itemId' => $itemBehId, 'score' => 5, 'comment' => 'final beh'],
            ],
        ])->assertOk()->assertJsonPath('success', true);
        $this->withHeaders($hAdmin)->postJson("/v1/hcm/performance/reviews/{$reviewId}/finalize")
            ->assertOk()->assertJsonPath('success', true);

        $this->withHeaders($hEmp)->getJson("/v1/hcm/performance/reviews/{$reviewId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'finalized');
    }

    public function test_manager_is_forbidden_for_admin_only_endpoints(): void
    {
        $manager = $this->login('perf-manager2@example.com', 'Perf Manager Two', 'Manager');
        $hMgr = ['Authorization' => 'Bearer '.$manager['token']];

        $this->withHeaders($hMgr)->getJson('/v1/hcm/performance/cycles')->assertStatus(403);
        $this->withHeaders($hMgr)->getJson('/v1/hcm/performance/indicator-templates')->assertStatus(403);
        $this->withHeaders($hMgr)->postJson('/v1/hcm/performance/reviews', [
            'cycleId' => 1,
            'userId' => 1,
            'templateId' => 1,
        ])->assertStatus(403);
    }

    public function test_performance_review_show_returns_404_when_not_found(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/performance/reviews/999999')
            ->assertNotFound();
    }

    public function test_review_leave_frequency_counts_only_same_company_approved_leaves(): void
    {
        $company = Company::factory()->create(['code' => 'perf_leave_company']);
        $admin = $this->createHcmAdminWithCompany([
            'email' => 'perf-leave-admin@example.com',
            'name' => 'Perf Leave Admin',
        ], $company);
        $employee = $this->login('perf-leave-employee@example.com', 'Perf Leave Employee', 'Staff');
        $otherCompany = Company::factory()->create(['code' => 'perf_leave_other_company']);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee['user']->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$admin['token'],
            'X-Company-Code' => $company->code,
        ];

        $template = PerformanceIndicatorTemplate::query()->create([
            'company_id' => $company->id,
            'name' => 'OPS IC',
            'department' => 'Operations',
            'designation' => 'Staff',
            'is_active' => true,
        ]);

        $cycle = PerformanceCycle::query()->create([
            'company_id' => $company->id,
            'name' => '2026 Annual',
            'period_start' => '2026-01-01',
            'period_end' => '2026-06-30',
            'status' => 'active',
        ]);

        $review = PerformanceReview::query()->create([
            'company_id' => $company->id,
            'cycle_id' => $cycle->id,
            'user_id' => $employee['user']->id,
            'manager_user_id' => null,
            'template_id' => $template->id,
            'status' => 'draft',
        ]);

        LeaveRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee['user']->id,
            'leave_type' => 'Annual Leave',
            'date_from' => '2026-02-10',
            'date_to' => '2026-02-11',
            'days' => 2,
            'status' => 'approved',
            'notes' => null,
        ]);
        LeaveRequest::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $employee['user']->id,
            'leave_type' => 'Sick Leave',
            'date_from' => '2026-03-10',
            'date_to' => '2026-03-12',
            'days' => 3,
            'status' => 'approved',
            'notes' => null,
        ]);

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/performance/reviews/'.$review->id)
            ->assertOk()
            ->assertJsonPath('data.leaveFrequency.totalApproveDays', 2)
            ->assertJsonPath('data.leaveFrequency.leaveCount', 1)
            ->assertJsonPath('data.leaveFrequency.leavesByType.Annual Leave', 2);
    }
}
