<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class PerformanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $email, string $name, ?string $designation = null): array
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => $name,
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = \App\Models\User::query()->where('email', $email)->firstOrFail();
        if ($designation !== null) {
            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['designation' => $designation]
            );
        }

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        return [
            'user' => $user,
            'token' => (string) $login->json('data.accessToken'),
        ];
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
            ['designation' => 'Staff', 'manager_user_id' => $manager['user']->id]
        );

        $hAdmin = ['Authorization' => 'Bearer '.$admin['token']];
        $hMgr = ['Authorization' => 'Bearer '.$manager['token']];
        $hEmp = ['Authorization' => 'Bearer '.$employee['token']];

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
}

